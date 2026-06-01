<?php
/**
 * GDS Backend - api/squadre.php
 * Gestione delle squadre: recupero (GET), creazione (POST), aggiornamento (PUT), eliminazione (DELETE).
 */

// 1. Header JSON + CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// 2. Inizializzazione risposta
$response = [
    "status"  => "error",
    "message" => "Internal Server Error",
    "data"    => null,
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 3. Analisi richiesta
    $method      = $_SERVER["REQUEST_METHOD"];
    $input       = json_decode(file_get_contents("php://input"), true);
    $queryParams = $_GET;

    // 4. Controllo autenticazione per i metodi di scrittura
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

    // 5. Connessione al database
    $mysqli = require_once __DIR__ . '/../utils/conn.php';

    if ($method === "GET") {
        // --- GET: Recupero squadre ---
        // ?id_sport=2 (obbligatorio nel codice originale, lo manteniamo come opzione principale)
        // ?id_squadra=5 (singola squadra)

        if (!empty($queryParams["id_squadra"])) {
            $id_squadra = (int) $queryParams["id_squadra"];
            $stmt = $mysqli->prepare("
                SELECT id_squadra, nome, coefficiente_team, id_sport
                FROM squadre
                WHERE id_squadra = ?
            ");
            $stmt->bind_param("i", $id_squadra);
            $stmt->execute();
            $squadra = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$squadra) {
                throw new Exception("Squadra non trovata", 404);
            }

            $squadra["id_squadra"] = (int)$squadra["id_squadra"];
            $squadra["id_sport"] = (int)$squadra["id_sport"];
            $squadra["coefficiente_team"] = (float)$squadra["coefficiente_team"];

            $response["status"]  = "success";
            $response["message"] = "Squadra recuperata con successo";
            $response["data"]    = $squadra;

        } elseif (!empty($queryParams["id_sport"])) {
            $id_sport = intval($queryParams["id_sport"]);
            if ($id_sport <= 0) {
                throw new Exception("id_sport deve essere un intero positivo.", 400);
            }

            // Verifica che lo sport esista
            $stmtSport = $mysqli->prepare("SELECT id_sport, nome FROM sport WHERE id_sport = ?");
            $stmtSport->bind_param("i", $id_sport);
            $stmtSport->execute();
            $sport = $stmtSport->get_result()->fetch_assoc();
            $stmtSport->close();

            if (!$sport) {
                throw new Exception("Nessuno sport trovato con id_sport = $id_sport", 404);
            }

            // Query squadre per lo sport richiesto
            $stmt = $mysqli->prepare("
                SELECT id_squadra, nome, coefficiente_team, id_sport
                FROM squadre
                WHERE id_sport = ?
                ORDER BY nome ASC
            ");
            $stmt->bind_param("i", $id_sport);
            $stmt->execute();
            $squadre = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($squadre as &$row) {
                $row["id_squadra"] = (int)$row["id_squadra"];
                $row["id_sport"] = (int)$row["id_sport"];
                $row["coefficiente_team"] = (float)$row["coefficiente_team"];
            }

            $response["status"]  = "success";
            $response["message"] = "Squadre recuperate con successo";
            $response["data"]    = [
                "sport"   => $sport,
                "count"   => count($squadre),
                "squadre" => $squadre,
            ];
        } else {
            // Lista completa squadre se non ci sono filtri
            $result = $mysqli->query("SELECT id_squadra, nome, coefficiente_team, id_sport FROM squadre ORDER BY nome ASC");
            $squadre = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($squadre as &$row) {
                $row["id_squadra"] = (int)$row["id_squadra"];
                $row["id_sport"] = (int)$row["id_sport"];
                $row["coefficiente_team"] = (float)$row["coefficiente_team"];
            }
            $response["status"]  = "success";
            $response["message"] = "Lista squadre recuperata con successo";
            $response["data"]    = $squadre;
        }

        http_response_code(200);

    } elseif ($method === "POST") {
        // --- POST: Creazione squadra ---
        // Body JSON: { "nome": "Squadra A", "id_sport": 1, "coefficiente_team": 1.5 }

        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }

        if (empty($input["nome"])) {
            throw new Exception("Il campo 'nome' è obbligatorio", 400);
        }
        if (empty($input["id_sport"])) {
            throw new Exception("Il campo 'id_sport' è obbligatorio", 400);
        }

        $nome              = $input["nome"];
        $id_sport          = (int) $input["id_sport"];
        $coefficiente_team = isset($input["coefficiente_team"]) ? (float) $input["coefficiente_team"] : 0.0;

        // Verifica che lo sport esista
        $check = $mysqli->prepare("SELECT id_sport FROM sport WHERE id_sport = ?");
        $check->bind_param("i", $id_sport);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Lo sport con id $id_sport non esiste", 400);
        }
        $check->close();

        $stmt = $mysqli->prepare("
            INSERT INTO squadre (nome, id_sport, coefficiente_team)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("sid", $nome, $id_sport, $coefficiente_team);
        $stmt->execute();

        $nuovoId = $mysqli->insert_id;
        $stmt->close();

        // Recupera la squadra creata
        $stmtSelect = $mysqli->prepare("SELECT id_squadra, nome, id_sport, coefficiente_team FROM squadre WHERE id_squadra = ?");
        $stmtSelect->bind_param("i", $nuovoId);
        $stmtSelect->execute();
        $squadraNuova = $stmtSelect->get_result()->fetch_assoc();
        $stmtSelect->close();

        if ($squadraNuova) {
            $squadraNuova["id_squadra"] = (int)$squadraNuova["id_squadra"];
            $squadraNuova["id_sport"] = (int)$squadraNuova["id_sport"];
            $squadraNuova["coefficiente_team"] = (float)$squadraNuova["coefficiente_team"];
        }

        $response["status"]  = "success";
        $response["message"] = "Squadra creata con successo";
        $response["data"]    = $squadraNuova;

        http_response_code(201);

    } elseif ($method === "PUT") {
        // --- PUT: Aggiornamento squadra ---
        // Body JSON: { "id_squadra": 5, "nome": "Nuovo Nome", ... }

        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }
        if (empty($input["id_squadra"])) {
            throw new Exception("Il campo 'id_squadra' è obbligatorio per l'aggiornamento", 400);
        }

        $id_squadra = (int) $input["id_squadra"];

        // Verifica esistenza
        $check = $mysqli->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = ?");
        $check->bind_param("i", $id_squadra);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Squadra non trovata", 404);
        }
        $check->close();

        $nome              = $input["nome"]              ?? null;
        $id_sport          = isset($input["id_sport"])    ? (int) $input["id_sport"]    : null;
        $coefficiente_team = isset($input["coefficiente_team"]) ? (float) $input["coefficiente_team"] : null;

        if ($id_sport !== null) {
            $checkS = $mysqli->prepare("SELECT id_sport FROM sport WHERE id_sport = ?");
            $checkS->bind_param("i", $id_sport);
            $checkS->execute();
            if (!$checkS->get_result()->fetch_assoc()) {
                $checkS->close();
                throw new Exception("Lo sport con id $id_sport non esiste", 400);
            }
            $checkS->close();
        }

        $stmt = $mysqli->prepare("
            UPDATE squadre
            SET nome = COALESCE(?, nome),
                id_sport = COALESCE(?, id_sport),
                coefficiente_team = COALESCE(?, coefficiente_team)
            WHERE id_squadra = ?
        ");
        $stmt->bind_param("sidi", $nome, $id_sport, $coefficiente_team, $id_squadra);
        $stmt->execute();
        $stmt->close();

        // Recupera aggiornata
        $stmtSelect = $mysqli->prepare("SELECT id_squadra, nome, id_sport, coefficiente_team FROM squadre WHERE id_squadra = ?");
        $stmtSelect->bind_param("i", $id_squadra);
        $stmtSelect->execute();
        $squadraAggiornata = $stmtSelect->get_result()->fetch_assoc();
        $stmtSelect->close();

        if ($squadraAggiornata) {
            $squadraAggiornata["id_squadra"] = (int)$squadraAggiornata["id_squadra"];
            $squadraAggiornata["id_sport"] = (int)$squadraAggiornata["id_sport"];
            $squadraAggiornata["coefficiente_team"] = (float)$squadraAggiornata["coefficiente_team"];
        }

        $response["status"]  = "success";
        $response["message"] = "Squadra aggiornata con successo";
        $response["data"]    = $squadraAggiornata;
        http_response_code(200);

    } elseif ($method === "DELETE") {
        // --- DELETE: Eliminazione squadra ---
        // ?id_squadra=5

        if (empty($queryParams["id_squadra"])) {
            throw new Exception("Parametro 'id_squadra' obbligatorio per la cancellazione", 400);
        }

        $id_squadra = (int) $queryParams["id_squadra"];

        $check = $mysqli->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = ?");
        $check->bind_param("i", $id_squadra);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Squadra non trovata", 404);
        }
        $check->close();

        $stmt = $mysqli->prepare("DELETE FROM squadre WHERE id_squadra = ?");
        $stmt->bind_param("i", $id_squadra);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Squadra eliminata con successo";
        $response["data"]    = ["id_squadra" => $id_squadra, "deleted" => true];
        http_response_code(200);

    } else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    $response["status"]  = "error";
    $response["message"] = "Errore database: " . $e->getMessage();
} catch (Exception $e) {
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    $response["status"]  = "error";
    $response["message"] = $e->getMessage();
} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

// 9. Output JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit();