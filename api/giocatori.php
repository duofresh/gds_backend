<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

$response = [
    "status"  => "error",
    "message" => "Internal Server Error",
    "data"    => null,
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    $method      = $_SERVER["REQUEST_METHOD"];
    $input       = json_decode(file_get_contents("php://input"), true);
    $queryParams = $_GET;

    // Controllo autenticazione per i metodi di scrittura
    if (in_array($method, ["POST", "PUT", "DELETE"])) {
        $authHeader = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
        $apiToken   = getenv("API_WRITE_TOKEN") ?: "";
        if (
            empty($apiToken) ||
            !preg_match('/^Bearer\s+(.+)$/', $authHeader, $matches) ||
            !hash_equals($apiToken, $matches[1])
        ) {
            throw new Exception("Accesso non autorizzato", 401);
        }
    }

    $mysqli = require_once __DIR__ . '/../utils/conn.php';

    if ($method === "GET") {
        // GET
        // ?id_giocatore=5        → singolo giocatore
        // ?id_squadra=3          → tutti i giocatori di una squadra
        // ?agonista=1            → filtra per agonista
        // (nessun parametro)     → lista completa

        if (!empty($queryParams["id_giocatore"])) {
            $id = (int) $queryParams["id_giocatore"];
            $stmt = $mysqli->prepare("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                WHERE g.id_giocatore = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $giocatore = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$giocatore) {
                throw new Exception("Giocatore non trovato", 404);
            }

            $response["status"]  = "success";
            $response["message"] = "Giocatore recuperato con successo";
            $response["data"]    = $giocatore;

        } elseif (!empty($queryParams["id_squadra"])) {
            $id_squadra = (int) $queryParams["id_squadra"];

            // Verifica esistenza squadra prima di cercare i giocatori
            $check = $mysqli->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = ?");
            $check->bind_param("i", $id_squadra);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $check->close();
                throw new Exception("Squadra non trovata", 404);
            }
            $check->close();

            $stmt = $mysqli->prepare("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                WHERE g.id_squadra = ?
                ORDER BY g.cognome, g.nome
            ");
            $stmt->bind_param("i", $id_squadra);
            $stmt->execute();
            $giocatori = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response["status"]  = "success";
            $response["message"] = "Giocatori della squadra recuperati con successo";
            $response["data"]    = $giocatori;

        } elseif (isset($queryParams["agonista"])) {
            $agonista = (int) $queryParams["agonista"];
            $stmt = $mysqli->prepare("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                WHERE g.agonista = ?
                ORDER BY g.cognome, g.nome
            ");
            $stmt->bind_param("i", $agonista);
            $stmt->execute();
            $giocatori = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response["status"]  = "success";
            $response["message"] = "Lista giocatori filtrata per agonista";
            $response["data"]    = $giocatori;

        } else {
            $result    = $mysqli->query("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                ORDER BY g.cognome, g.nome
            ");
            $giocatori = $result->fetch_all(MYSQLI_ASSOC);

            $response["status"]  = "success";
            $response["message"] = "Lista giocatori recuperata con successo";
            $response["data"]    = $giocatori;
        }

        http_response_code(200);

    } elseif ($method === "POST") {
        // POST
        // Body JSON richiesto:
        //   { "nome": "Mario", "cognome": "Rossi", "eta": 25,
        //     "agonista": 1, "id_squadra": 3 }

        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }

        // Campi obbligatori
        if (empty($input["nome"])) {
            throw new Exception("Il campo 'nome' è obbligatorio", 400);
        }
        if (empty($input["cognome"])) {
            throw new Exception("Il campo 'cognome' è obbligatorio", 400);
        }

        if (isset($input["eta"]) && (!is_numeric($input["eta"]) || $input["eta"] < 0)) {
            throw new Exception("Il campo 'eta' deve essere un numero positivo", 400);
        }

        // Validazione agonista (deve essere 0 o 1)
        if (isset($input["agonista"]) && !in_array($input["agonista"], [0, 1], true)) {
            throw new Exception("Il campo 'agonista' deve essere 0 (No) o 1 (Sì)", 400);
        }

        // Verifica che la squadra esista (se fornita)
        if (!empty($input["id_squadra"])) {
            $id_squadra_check = (int) $input["id_squadra"];
            $check = $mysqli->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = ?");
            $check->bind_param("i", $id_squadra_check);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) {
                $check->close();
                throw new Exception("La squadra con id " . $input["id_squadra"] . " non esiste", 400);
            }
            $check->close();
        }

        $nome       = $input["nome"];
        $cognome    = $input["cognome"];
        $eta        = isset($input["eta"])        ? (int) $input["eta"]        : null;
        $agonista   = isset($input["agonista"])   ? (int) $input["agonista"]   : 0;
        $id_squadra = isset($input["id_squadra"]) ? (int) $input["id_squadra"] : null;

        $stmt = $mysqli->prepare("
            INSERT INTO giocatori (nome, cognome, eta, agonista, id_squadra)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssiii", $nome, $cognome, $eta, $agonista, $id_squadra);
        $stmt->execute();

        $nuovoId = $mysqli->insert_id;
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Giocatore creato con successo";
        $response["data"]    = [
            "id_giocatore" => (int) $nuovoId,
            "nome"         => $nome,
            "cognome"      => $cognome,
            "eta"          => $eta,
            "agonista"     => $agonista,
            "id_squadra"   => $id_squadra,
        ];

        http_response_code(201);

    } elseif ($method === "PUT") {
        // PUT
        // Body JSON richiesto:
        //   { "id_giocatore": 5, "nome": "Cristiano", "cognome": "Messi",
        //     "eta": 30, "agonista": 0, "id_squadra": 2 }

        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }
        if (empty($input["id_giocatore"])) {
            throw new Exception("Il campo 'id_giocatore' è obbligatorio per l'aggiornamento", 400);
        }

        $id_giocatore = (int) $input["id_giocatore"];

        // Verifica che il giocatore esista
        $check = $mysqli->prepare("SELECT id_giocatore FROM giocatori WHERE id_giocatore = ?");
        $check->bind_param("i", $id_giocatore);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Giocatore non trovato", 404);
        }
        $check->close();

        // Validazioni opzionali
        if (isset($input["eta"]) && (!is_numeric($input["eta"]) || $input["eta"] < 0)) {
            throw new Exception("Il campo 'eta' deve essere un numero positivo", 400);
        }
        if (isset($input["agonista"]) && !in_array($input["agonista"], [0, 1], true)) {
            throw new Exception("Il campo 'agonista' deve essere 0 (No) o 1 (Sì)", 400);
        }
        if (!empty($input["id_squadra"])) {
            $id_squadra_check = (int) $input["id_squadra"];
            $check2 = $mysqli->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = ?");
            $check2->bind_param("i", $id_squadra_check);
            $check2->execute();
            if (!$check2->get_result()->fetch_assoc()) {
                $check2->close();
                throw new Exception("La squadra con id " . $input["id_squadra"] . " non esiste", 400);
            }
            $check2->close();
        }

        $nome       = $input["nome"]       ?? null;
        $cognome    = $input["cognome"]     ?? null;
        $eta        = isset($input["eta"])        ? (int) $input["eta"]        : null;
        $agonista   = isset($input["agonista"])   ? (int) $input["agonista"]   : null;
        $id_squadra = isset($input["id_squadra"]) ? (int) $input["id_squadra"] : null;

        $stmt = $mysqli->prepare("
            UPDATE giocatori
            SET nome       = COALESCE(?, nome),
                cognome    = COALESCE(?, cognome),
                eta        = COALESCE(?, eta),
                agonista   = COALESCE(?, agonista),
                id_squadra = COALESCE(?, id_squadra)
            WHERE id_giocatore = ?
        ");
        $stmt->bind_param("ssiiii", $nome, $cognome, $eta, $agonista, $id_squadra, $id_giocatore);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Giocatore aggiornato con successo";
        $response["data"]    = ["id_giocatore" => $id_giocatore];

        http_response_code(200);

    } elseif ($method === "DELETE") {
        // DELETE
        // ?id_giocatore=5

        if (empty($queryParams["id_giocatore"])) {
            throw new Exception("Parametro 'id_giocatore' obbligatorio per la cancellazione", 400);
        }

        $id_giocatore = (int) $queryParams["id_giocatore"];

        // Verifica che il giocatore esista
        $check = $mysqli->prepare("SELECT id_giocatore FROM giocatori WHERE id_giocatore = ?");
        $check->bind_param("i", $id_giocatore);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Giocatore non trovato", 404);
        }
        $check->close();

        $stmt = $mysqli->prepare("DELETE FROM giocatori WHERE id_giocatore = ?");
        $stmt->bind_param("i", $id_giocatore);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Giocatore eliminato con successo";
        $response["data"]    = [
            "id_giocatore" => $id_giocatore,
            "deleted"      => true,
        ];

        http_response_code(200);

    } else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }

} catch (mysqli_sql_exception $e) {
    // Errori del Database: messaggio generico al client, dettagli solo nel log
    http_response_code(500);
    $response["status"]  = "error";
    $response["message"] = "Errore di connessione o esecuzione query sul Database";
    $response["data"]    = null;
    error_log("DB error in api/giocatori.php: " . $e->getMessage());

} catch (Exception $e) {
    // Errori di validazione e logica applicativa
    $code = $e->getCode();
    if ($code < 400 || $code > 599) {
        $code = 500;
    }

    $response["status"]  = "error";
    $response["message"] = $e->getMessage();
    $response["data"]    = null;

    http_response_code($code);

} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

// OUTPUT FINALE
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);