<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
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

    $mysqli = require_once __DIR__ . '/../utils/conn.php';

    if ($method === "GET") {
        // GET /api/iscrizioni
        // GET /api/iscrizioni?id_torneo=3
        // GET /api/iscrizioni?id_squadra=2

        $sql = "
            SELECT i.id_torneo, i.id_squadra, i.seed, t.nome AS nome_torneo, s.nome AS nome_squadra
            FROM iscrizioni i
            JOIN tornei t ON i.id_torneo = t.id_torneo
            JOIN squadre s ON i.id_squadra = s.id_squadra
        ";
        
        $params = [];
        $types = "";
        $where = [];

        if (!empty($queryParams["id_torneo"])) {
            $where[] = "i.id_torneo = ?";
            $params[] = (int) $queryParams["id_torneo"];
            $types .= "i";
        }

        if (!empty($queryParams["id_squadra"])) {
            $where[] = "i.id_squadra = ?";
            $params[] = (int) $queryParams["id_squadra"];
            $types .= "i";
        }

        if (count($where) > 0) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        
        $sql .= " ORDER BY t.nome, i.seed ASC, s.nome";

        $stmt = $mysqli->prepare($sql);
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Convert integer fields
        foreach ($result as &$row) {
            $row["id_torneo"] = (int)$row["id_torneo"];
            $row["id_squadra"] = (int)$row["id_squadra"];
            $row["seed"] = $row["seed"] !== null ? (int)$row["seed"] : null;
        }

        $response["status"]  = "success";
        $response["message"] = "Iscrizioni recuperate con successo";
        $response["data"]    = $result;
        http_response_code(200);

    } elseif ($method === "POST") {
        // POST /api/iscrizioni
        
        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }

        $action = $input["action"] ?? $queryParams["action"] ?? "";

        // Sotto-azione: calcola seed torneo tramite stored procedure
        if ($action === "calcola_seed") {
            if (empty($input["id_torneo"])) {
                throw new Exception("Il campo 'id_torneo' è obbligatorio per il calcolo del seed", 400);
            }
            $id_torneo = (int) $input["id_torneo"];

            // Verifica che il torneo esista
            $checkTorneo = $mysqli->prepare("SELECT id_torneo FROM tornei WHERE id_torneo = ?");
            $checkTorneo->bind_param("i", $id_torneo);
            $checkTorneo->execute();
            if (!$checkTorneo->get_result()->fetch_assoc()) {
                $checkTorneo->close();
                throw new Exception("Il torneo con ID " . $id_torneo . " non esiste", 400);
            }
            $checkTorneo->close();

            // Chiamata Stored Procedure
            $stmt = $mysqli->prepare("CALL calcola_seed_torneo(?)");
            $stmt->bind_param("i", $id_torneo);
            $stmt->execute();
            $stmt->close();

            // Recupera le iscrizioni aggiornate
            $stmtSelect = $mysqli->prepare("
                SELECT i.id_torneo, i.id_squadra, i.seed, t.nome AS nome_torneo, s.nome AS nome_squadra
                FROM iscrizioni i
                JOIN tornei t ON i.id_torneo = t.id_torneo
                JOIN squadre s ON i.id_squadra = s.id_squadra
                WHERE i.id_torneo = ?
                ORDER BY i.seed ASC, s.nome
            ");
            $stmtSelect->bind_param("i", $id_torneo);
            $stmtSelect->execute();
            $updatedResult = $stmtSelect->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSelect->close();

            foreach ($updatedResult as &$row) {
                $row["id_torneo"] = (int)$row["id_torneo"];
                $row["id_squadra"] = (int)$row["id_squadra"];
                $row["seed"] = $row["seed"] !== null ? (int)$row["seed"] : null;
            }

            $response["status"]  = "success";
            $response["message"] = "Seed del torneo ricalcolati con successo";
            $response["data"]    = $updatedResult;
            http_response_code(200);

        } else {
            // Nuova iscrizione standard
            if (empty($input["id_torneo"])) {
                throw new Exception("Il campo 'id_torneo' è obbligatorio", 400);
            }
            if (empty($input["id_squadra"])) {
                throw new Exception("Il campo 'id_squadra' è obbligatorio", 400);
            }

            $id_torneo = (int) $input["id_torneo"];
            $id_squadra = (int) $input["id_squadra"];
            $seed = isset($input["seed"]) && $input["seed"] !== null ? (int) $input["seed"] : null;

            // Verifica che il torneo esista
            $checkTorneo = $mysqli->prepare("SELECT id_torneo FROM tornei WHERE id_torneo = ?");
            $checkTorneo->bind_param("i", $id_torneo);
            $checkTorneo->execute();
            if (!$checkTorneo->get_result()->fetch_assoc()) {
                $checkTorneo->close();
                throw new Exception("Il torneo con ID " . $id_torneo . " non esiste", 400);
            }
            $checkTorneo->close();

            // Verifica che la squadra esista
            $checkSquadra = $mysqli->prepare("SELECT id_squadra FROM squadre WHERE id_squadra = ?");
            $checkSquadra->bind_param("i", $id_squadra);
            $checkSquadra->execute();
            if (!$checkSquadra->get_result()->fetch_assoc()) {
                $checkSquadra->close();
                throw new Exception("La squadra con ID " . $id_squadra . " non esiste", 400);
            }
            $checkSquadra->close();

            // Verifica se l'iscrizione esiste già
            $checkExists = $mysqli->prepare("SELECT seed FROM iscrizioni WHERE id_torneo = ? AND id_squadra = ?");
            $checkExists->bind_param("ii", $id_torneo, $id_squadra);
            $checkExists->execute();
            if ($checkExists->get_result()->fetch_assoc()) {
                $checkExists->close();
                throw new Exception("La squadra " . $id_squadra . " è già iscritta a questo torneo", 400);
            }
            $checkExists->close();

            // Esegui inserimento
            $stmt = $mysqli->prepare("INSERT INTO iscrizioni (id_torneo, id_squadra, seed) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $id_torneo, $id_squadra, $seed);
            $stmt->execute();
            $stmt->close();

            $response["status"]  = "success";
            $response["message"] = "Iscrizione completata con successo";
            $response["data"]    = [
                "id_torneo"  => $id_torneo,
                "id_squadra" => $id_squadra,
                "seed"       => $seed
            ];
            http_response_code(201);
        }

    } elseif ($method === "DELETE") {
        // DELETE /api/iscrizioni?id_torneo=3&id_squadra=2

        $id_torneo = isset($queryParams["id_torneo"]) ? (int) $queryParams["id_torneo"] : (isset($input["id_torneo"]) ? (int) $input["id_torneo"] : 0);
        $id_squadra = isset($queryParams["id_squadra"]) ? (int) $queryParams["id_squadra"] : (isset($input["id_squadra"]) ? (int) $input["id_squadra"] : 0);

        if ($id_torneo <= 0 || $id_squadra <= 0) {
            throw new Exception("I parametri 'id_torneo' e 'id_squadra' sono obbligatori per cancellare l'iscrizione", 400);
        }

        // Verifica che l'iscrizione esista
        $check = $mysqli->prepare("SELECT 1 FROM iscrizioni WHERE id_torneo = ? AND id_squadra = ?");
        $check->bind_param("ii", $id_torneo, $id_squadra);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Iscrizione non trovata", 404);
        }
        $check->close();

        // Esegui cancellazione
        $stmt = $mysqli->prepare("DELETE FROM iscrizioni WHERE id_torneo = ? AND id_squadra = ?");
        $stmt->bind_param("ii", $id_torneo, $id_squadra);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Iscrizione eliminata con successo";
        $response["data"]    = [
            "id_torneo"  => $id_torneo,
            "id_squadra" => $id_squadra,
            "deleted"    => true
        ];
        http_response_code(200);

    } else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    $response["status"]  = "error";
    $response["message"] = "Errore di connessione o esecuzione query sul Database";
    $response["data"]    = null;
    error_log("DB error in api/iscrizioni.php: " . $e->getMessage());

} catch (Exception $e) {
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

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit();
