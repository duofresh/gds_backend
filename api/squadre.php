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
    $host   = "localhost";
    $dbname = "giornatadellosport";
    $user   = "root";
    $pass   = "";

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 6. Verifica che lo sport esista
    $stmtSport = $pdo->prepare("SELECT id_sport, nome FROM sport WHERE id_sport = :id_sport");
    $stmtSport->execute([":id_sport" => $id_sport]);
    $sport = $stmtSport->fetch(PDO::FETCH_ASSOC);

    if (!$sport) {
        throw new Exception("Nessuno sport trovato con id_sport = $id_sport", 404);
    }

    // 7. Query squadre per lo sport richiesto
    $stmt = $pdo->prepare(
        "SELECT s.id_squadra, s.nome, s.coefficiente_team, s.id_sport
         FROM squadre s
         WHERE s.id_sport = :id_sport
         ORDER BY s.nome ASC"
    );
    $stmt->execute([":id_sport" => $id_sport]);
    $squadre = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Costruzione risposta
    $response["status"]  = "success";
    $response["message"] = "Squadre recuperate con successo";
    $response["data"]    = [
        "sport"   => $sport,
        "count"   => count($squadre),
        "squadre" => $squadre,
    ];
    http_response_code(200);

} catch (PDOException $e) {
    http_response_code(500);
    $response["status"]  = "error";
    $response["message"] = "Errore database: " . $e->getMessage();
} catch (Exception $e) {
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    $response["status"]  = "error";
    $response["message"] = $e->getMessage();
}

// 9. Output JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit();