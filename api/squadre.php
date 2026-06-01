<?php
/**
 * GDS Backend - api/squadre.php
 * Restituisce le squadre di uno sport specifico.
 *
 * Endpoint: GET api/squadre.php?id_sport=2
 *
 * Parametri GET:
 *   id_sport (int, obbligatorio) - ID dello sport
 */

// 1. Header JSON + CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

try {
    // 3. Validazione metodo
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        throw new Exception("Metodo non consentito. Usa GET.", 405);
    }

    // 4. Validazione parametri GET
    if (!isset($_GET["id_sport"]) || $_GET["id_sport"] === "") {
        throw new Exception("Parametro obbligatorio mancante: id_sport", 400);
    }

    $id_sport = intval($_GET["id_sport"]);

    if ($id_sport <= 0) {
        throw new Exception("id_sport deve essere un intero positivo.", 400);
    }

    // 5. Connessione al database
    $mysqli = require_once __DIR__ . '/../utils/conn.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // 6. Verifica che lo sport esista
    $stmtSport = $mysqli->prepare("SELECT id_sport, nome FROM sport WHERE id_sport = ?");
    $stmtSport->bind_param("i", $id_sport);
    $stmtSport->execute();
    $sport = $stmtSport->get_result()->fetch_assoc();
    $stmtSport->close();

    if (!$sport) {
        throw new Exception("Nessuno sport trovato con id_sport = $id_sport", 404);
    }

    // 7. Query squadre per lo sport richiesto
    $stmt = $mysqli->prepare(
        "SELECT s.id_squadra, s.nome, s.coefficiente_team, s.id_sport
         FROM squadre s
         WHERE s.id_sport = ?
         ORDER BY s.nome ASC"
    );
    $stmt->bind_param("i", $id_sport);
    $stmt->execute();
    $squadre = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Convert integer and float fields
    foreach ($squadre as &$row) {
        $row["id_squadra"] = (int)$row["id_squadra"];
        $row["id_sport"] = (int)$row["id_sport"];
        $row["coefficiente_team"] = (float)$row["coefficiente_team"];
    }

    if ($sport) {
        $sport["id_sport"] = (int)$sport["id_sport"];
    }

    // 8. Costruzione risposta
    $response["status"]  = "success";
    $response["message"] = "Squadre recuperate con successo";
    $response["data"]    = [
        "sport"   => $sport,
        "count"   => count($squadre),
        "squadre" => $squadre,
    ];
    http_response_code(200);

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