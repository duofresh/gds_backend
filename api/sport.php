<?php
/**
 * GDS Backend - API Template degli endpoint
 *
 * Struttura standardizzata per gli endpoint dell'api,
 * header json, handling degli errori, e struttura consistente delle risposte.
 */

// 1. Header (Intestazione) - per comunicare in JSON
header("Access-Control-Allow-Origin: *"); // Policy CORS
header("Content-Type: application/json; charset=UTF-8"); // Tipo JSON
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); // Metodi HTTP permessi
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handling delle richieste OPTIONS (Preflight)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// 2. Inizializzazione risposta base
$response = [
    "status" => "error",
    "message" => "Internal Server Error",
    "data" => null,
];

// Abilita le eccezioni per MySQLi (fondamentale per usare il try/catch)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 3. Validazione metodo richiesta
    $method = $_SERVER["REQUEST_METHOD"];

    // In questo esempio accettiamo solo richieste GET
    if ($method !== 'GET') {
        throw new Exception("Method not allowed. Use GET.", 405);
    }

    // --- INIZIO LOGICA INTEGRATA CON REQUISITI ---

    // 4. Connessione al DB (Utilizzo MySQLi)
    $db_host = '127.0.0.1';
    $db_name = 'gds';
    $db_user = 'root'; 
    $db_pass = '';    

    // Creazione dell'istanza MySQLi
    $mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Imposta il charset per evitare problemi con caratteri speciali
    $mysqli->set_charset("utf8mb4");

    // 5. Gestione input tramite $_GET e controllo minimo (es. intval)
    if (!isset($_GET['id'])) {
        throw new Exception("Parametro 'id' mancante nella richiesta", 400); // 400 Bad Request
    }

    // Assicuriamoci che l'ID sia un numero intero valido
    $id = intval($_GET['id']);
    
    if ($id <= 0) {
        throw new Exception("L'id fornito non è in un formato valido", 400);
    }

    // 6. Esecuzione query corretta e sicura (Prepared Statement in MySQLi)
    // Nota: MySQLi usa "?" come segnaposto al posto di parametri nominati come ":id"
    $query = "SELECT * FROM sport WHERE id_sport = ? LIMIT 1";
    $stmt = $mysqli->prepare($query);
    
    // Associa il parametro all'ID. "i" sta per integer.
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // 7. Costruzione dell'array dei risultati
    $result_set = $stmt->get_result();
    $result = $result_set->fetch_all(MYSQLI_ASSOC);
    
    // Chiusura dello statement per liberare risorse
    $stmt->close();

    // Verifichiamo se l'array è vuoto (nessun record trovato)
    if (!$result) {
        throw new Exception("Nessun record trovato per l'ID specificato", 404); // 404 Not Found
    }

    // 8. Costruzione risposta nel caso di successo
    $response["status"] = "success";
    $response["message"] = "Endpoint raggiunto e dati estratti con successo";
    $response["data"] = $result; // L'array contenente i dati del DB
    
    http_response_code(200); // 200 OK

    // --- FINE LOGICA ---

} catch (mysqli_sql_exception $e) {
    // Gestione specifica degli errori del Database (MySQLi)
    http_response_code(500);
    $response["status"] = "error";
    // In produzione non stampare $e->getMessage() per motivi di sicurezza
    $response["message"] = "Errore di connessione o esecuzione query sul Database"; 
    $response["data"] = null;

} catch (Exception $e) {
    // 9. Standardizzazione errori generali
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);

    $response["status"] = "error";
    $response["message"] = $e->getMessage();
    $response["data"] = null;
} finally {
    // 10. Chiusura della connessione al database (se è stata aperta)
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

// 11. Output finale (JSON)
echo json_encode($response);
exit();