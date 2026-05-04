<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit();
}

$host   = "127.0.0.1";
$dbname = "gds"; 
$user   = "root";           
$pass   = "";               

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => "Connessione al database fallita: " . $e->getMessage(),
        "data"    => null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

$response = [
    "status"  => "error",
    "message" => "Internal Server Error",
    "data"    => null,
];

try {

    $method      = $_SERVER["REQUEST_METHOD"];
    $input       = json_decode(file_get_contents("php://input"), true);
    $queryParams = $_GET;


    if ($method === "GET") {
        // GET (ebola)
        // ?id_giocatore=5        → singolo giocatore
        // ?id_squadra=3          → tutti i giocatori di una squadra
        // ?agonista=1            → filtra per agonista
        // (nessun parametro)     → lista completa

        if (!empty($queryParams["id_giocatore"])) {
            $stmt = $pdo->prepare("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                WHERE g.id_giocatore = :id
            ");
            $stmt->execute([":id" => (int) $queryParams["id_giocatore"]]);
            $giocatore = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$giocatore) {
                throw new Exception("Giocatore non trovato", 404);
            }

            $response["status"]  = "success";
            $response["message"] = "Giocatore recuperato con successo";
            $response["data"]    = $giocatore;

        } elseif (!empty($queryParams["id_squadra"])) {
            $stmt = $pdo->prepare("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                WHERE g.id_squadra = :id_squadra
                ORDER BY g.cognome, g.nome
            ");
            $stmt->execute([":id_squadra" => (int) $queryParams["id_squadra"]]);
            $giocatori = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$giocatori) {
                throw new Exception("Squadra non trovata", 404);
            }


            $response["status"]  = "success";
            $response["message"] = "Giocatori della squadra recuperati con successo";
            $response["data"]    = $giocatori;

        } elseif (isset($queryParams["agonista"])) {
            $stmt = $pdo->prepare("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                WHERE g.agonista = :agonista
                ORDER BY g.cognome, g.nome
            ");
            $stmt->execute([":agonista" => (int) $queryParams["agonista"]]);
            $giocatori = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response["status"]  = "success";
            $response["message"] = "Lista giocatori filtrata per agonista";
            $response["data"]    = $giocatori;

        } else {
            $stmt = $pdo->query("
                SELECT g.id_giocatore, g.nome, g.cognome, g.eta, g.agonista,
                       s.id_squadra, s.nome AS nome_squadra
                FROM giocatori g
                LEFT JOIN squadre s ON g.id_squadra = s.id_squadra
                ORDER BY g.cognome, g.nome
            ");
            $giocatori = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response["status"]  = "success";
            $response["message"] = "Lista giocatori recuperata con successo";
            $response["data"]    = $giocatori;
        }

        http_response_code(200);

    } elseif ($method === "POST") {
        // POST (Elmo's world)
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

        // Validazione agonista (deve essere 0 o 1) (skibidi)
        if (isset($input["agonista"]) && !in_array($input["agonista"], [0, 1], true)) {
            throw new Exception("Il campo 'agonista' deve essere 0 (No) o 1 (Sì)", 400);
        }

        // Verifica che la squadra esista {[(se fornita)]}
        if (!empty($input["id_squadra"])) {
            $check = $pdo->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = :id");
            $check->execute([":id" => (int) $input["id_squadra"]]);
            if (!$check->fetch()) {
                throw new Exception("La squadra con id " . $input["id_squadra"] . " non esiste", 400);
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO giocatori (nome, cognome, eta, agonista, id_squadra)
            VALUES (:nome, :cognome, :eta, :agonista, :id_squadra)
        ");
        $stmt->execute([
            ":nome"       => $input["nome"],
            ":cognome"    => $input["cognome"],
            ":eta"        => $input["eta"]        ?? null,
            ":agonista"   => $input["agonista"]   ?? 0,
            ":id_squadra" => $input["id_squadra"] ?? null,
        ]);

        $nuovoId = $pdo->lastInsertId();

        $response["status"]  = "success";
        $response["message"] = "Giocatore creato con successo";
        $response["data"]    = [
            "id_giocatore" => (int) $nuovoId,
            "nome"         => $input["nome"],
            "cognome"      => $input["cognome"],
            "eta"          => $input["eta"]        ?? null,
            "agonista"     => $input["agonista"]   ?? 0,
            "id_squadra"   => $input["id_squadra"] ?? null,
        ];

        http_response_code(201);

    } elseif ($method === "PUT") {
        // PUT (Francisco)
        // Body JSON richiesto:
        //   { "id_giocatore": 5, "nome": "Cristiano", "cognome": "Messi",
        //     "eta": 30, "agonista": 0, "id_squadra": 2 }

        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }
        if (empty($input["id_giocatore"])) {
            throw new Exception("Il campo 'id_giocatore' è obbligatorio per l'aggiornamento", 400);
        }

        // Verifica che il giocatore esista
        $check = $pdo->prepare("SELECT id_giocatore FROM giocatori WHERE id_giocatore = :id");
        $check->execute([":id" => (int) $input["id_giocatore"]]);
        if (!$check->fetch()) {
            throw new Exception("Giocatore non trovato", 404);
        }

        // Validazioni opzionali
        if (isset($input["eta"]) && (!is_numeric($input["eta"]) || $input["eta"] < 0)) {
            throw new Exception("Il campo 'eta' deve essere un numero positivo", 400);
        }
        if (isset($input["agonista"]) && !in_array($input["agonista"], [0, 1], true)) {
            throw new Exception("Il campo 'agonista' deve essere 0 (No) o 1 (Sì)", 400);
        }
        if (!empty($input["id_squadra"])) {
            $check2 = $pdo->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = :id");
            $check2->execute([":id" => (int) $input["id_squadra"]]);
            if (!$check2->fetch()) {
                throw new Exception("La squadra con id " . $input["id_squadra"] . " non esiste", 400);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE giocatori
            SET nome       = COALESCE(:nome,       nome),
                cognome    = COALESCE(:cognome,    cognome),
                eta        = COALESCE(:eta,        eta),
                agonista   = COALESCE(:agonista,   agonista),
                id_squadra = COALESCE(:id_squadra, id_squadra)
            WHERE id_giocatore = :id_giocatore
        ");
        $stmt->execute([
            ":nome"          => $input["nome"]       ?? null,
            ":cognome"       => $input["cognome"]     ?? null,
            ":eta"           => $input["eta"]         ?? null,
            ":agonista"      => $input["agonista"]    ?? null,
            ":id_squadra"    => $input["id_squadra"]  ?? null,
            ":id_giocatore"  => (int) $input["id_giocatore"],
        ]);

        $response["status"]  = "success";
        $response["message"] = "Giocatore aggiornato con successo";
        $response["data"]    = ["id_giocatore" => (int) $input["id_giocatore"]];

        http_response_code(200);

    } elseif ($method === "DELETE") {
        // DELETE (Gorlock)
        // ?id_giocatore=5

        if (empty($queryParams["id_giocatore"])) {
            throw new Exception("Parametro 'id_giocatore' obbligatorio per la cancellazione", 400);
        }

        // Verifica che il giocatore esista
        $check = $pdo->prepare("SELECT id_giocatore FROM giocatori WHERE id_giocatore = :id");
        $check->execute([":id" => (int) $queryParams["id_giocatore"]]);
        if (!$check->fetch()) {
            throw new Exception("Giocatore non trovato", 404);
        }

        $stmt = $pdo->prepare("DELETE FROM giocatori WHERE id_giocatore = :id");
        $stmt->execute([":id" => (int) $queryParams["id_giocatore"]]);

        $response["status"]  = "success";
        $response["message"] = "Giocatore eliminato con successo";
        $response["data"]    = [
            "id_giocatore" => (int) $queryParams["id_giocatore"],
            "deleted"      => true,
        ];

        http_response_code(200);

    } else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }

} catch (Exception $e) {
    // GESTIONE ERRORI
    $code = $e->getCode();
    if ($code < 400 || $code > 599) {
        $code = 500;
    }

    $response["status"]  = "error";
    $response["message"] = $e->getMessage();
    $response["data"]    = null;

    http_response_code($code);
}

// OUTPUT FINALE (pancrazio)
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);