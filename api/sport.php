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
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS"); // Metodi HTTP permessi
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

    // Accettiamo richieste GET, POST e DELETE
    if ($method !== 'GET' && $method !== 'POST' && $method !== 'DELETE') {
        throw new Exception("Method not allowed. Use GET, POST or DELETE.", 405);
    }

    // --- INIZIO LOGICA INTEGRATA CON REQUISITI ---

    // 4. Connessione al DB (Utilizzo MySQLi)
    $mysqli = require_once __DIR__ . '/../utils/conn.php';

    if ($method === 'GET') {
        // 5. Gestione input tramite $_GET e query dinamica
        if (isset($_GET['id'])) {
            // Estrazione di un singolo sport tramite ID
            $id = intval($_GET['id']);
            if ($id <= 0) {
                throw new Exception("L'id fornito non è in un formato valido", 400);
            }

            $query = "SELECT * FROM sport WHERE id_sport = ? LIMIT 1";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $result_set = $stmt->get_result();
            $result = $result_set->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            // Estrazione di tutti gli sport
            $query = "SELECT * FROM sport";
            $result_set = $mysqli->query($query);
            $result = $result_set->fetch_all(MYSQLI_ASSOC);
        }

        // Verifichiamo se l'array è vuoto (nessun record trovato)
        if (!$result) {
            throw new Exception("Nessun sport trovato", 404); // 404 Not Found
        }

        // Costruzione risposta per GET
        $response["status"] = "success";
        $response["message"] = "Dati estratti con successo";
        $response["data"] = $result;
        http_response_code(200); // 200 OK

    } elseif ($method === 'POST') {
        // Inserimento di un nuovo sport
        $inputData = json_decode(file_get_contents("php://input"), true);
        if (!$inputData || !isset($inputData['nome'])) {
            throw new Exception("Dati mancanti o formato JSON non valido. Richiesto campo 'nome'.", 400);
        }

        $nome = trim($inputData['nome']);
        $descrizione = isset($inputData['descrizione']) ? trim($inputData['descrizione']) : null;

        if (empty($nome)) {
             throw new Exception("Il campo 'nome' non può essere vuoto", 400);
        }

        $query = "INSERT INTO sport (nome, descrizione) VALUES (?, ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("ss", $nome, $descrizione);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $result = [
                "id_sport" => $newId,
                "nome" => $nome,
                "descrizione" => $descrizione
            ];
            
            // Costruzione risposta per POST
            $response["status"] = "success";
            $response["message"] = "Sport creato con successo";
            $response["data"] = $result;
            http_response_code(201); // 201 Created
        } else {
            throw new Exception("Errore durante l'inserimento dello sport", 500);
        }
        $stmt->close();
    } elseif ($method === 'DELETE') {
        // Eliminazione di uno sport
        if (!isset($_GET['id'])) {
            // Prova a leggere dal body JSON in caso l'ID non sia in query string
            $inputData = json_decode(file_get_contents("php://input"), true);
            if (isset($inputData['id_sport'])) {
                $id = intval($inputData['id_sport']);
            } elseif (isset($inputData['id'])) {
                $id = intval($inputData['id']);
            } else {
                throw new Exception("ID mancante per l'eliminazione. Fornire l'id nella query string o nel body JSON.", 400);
            }
        } else {
            $id = intval($_GET['id']);
        }

        if ($id <= 0) {
            throw new Exception("L'id fornito non è in un formato valido", 400);
        }

        $query = "DELETE FROM sport WHERE id_sport = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Costruzione risposta per DELETE
                $response["status"] = "success";
                $response["message"] = "Sport eliminato con successo";
                $response["data"] = null;
                http_response_code(200); // 200 OK
            } else {
                throw new Exception("Nessun sport trovato con l'id fornito", 404);
            }
        } else {
            throw new Exception("Errore durante l'eliminazione dello sport", 500);
        }
        $stmt->close();
    }

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
