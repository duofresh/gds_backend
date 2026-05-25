<?php
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

    // Accettiamo richieste GET e POST
    if ($method !== 'GET' && $method !== 'POST') {
        throw new Exception("Method not allowed. Use GET or POST.", 405);
    }

    // --- INIZIO LOGICA INTEGRATA CON REQUISITI ---

    // 4. Connessione al DB (Utilizzo MySQLi)
    $mysqli = require_once __DIR__ . '/../utils/conn.php';

    if ($method === 'GET') {
        // 5. Gestione input tramite $_GET e query dinamica
        if (isset($_GET['id'])) {
            // Estrazione di un singolo torneo tramite ID
            $id = intval($_GET['id']);
            if ($id <= 0) {
                throw new Exception("L'id fornito non è in un formato valido", 400);
            }

            $query = "SELECT * FROM tornei WHERE id_torneo = ? LIMIT 1";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            $result_set = $stmt->get_result();
            $result = $result_set->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } else {
            // Estrazione di tutti i tornei
            $query = "SELECT * FROM tornei";
            $result_set = $mysqli->query($query);
            $result = $result_set->fetch_all(MYSQLI_ASSOC);
        }

        // Verifichiamo se l'array è vuoto (nessun record trovato)
        if (!$result) {
            throw new Exception("Nessun torneo trovato", 404); // 404 Not Found
        }

        // Costruzione risposta per GET
        $response["status"] = "success";
        $response["message"] = "Dati estratti con successo";
        $response["data"] = $result;
        http_response_code(200); // 200 OK

    } elseif ($method === 'POST') {
        // Inserimento di un nuovo torneo
        $inputData = json_decode(file_get_contents("php://input"), true);
        if (!$inputData || !isset($inputData['nome'])) {
            throw new Exception("Dati mancanti o formato JSON non valido. Richiesto campo 'nome'.", 400);
        }
        if(!$inputData || !isset($inputData['anno'])){
            throw new Exception("Dati mancanti o formato JSON non valido. Richiesto campo 'anno'.", 400);
        } 
        if(!$inputData || !isset($inputData['stato'])){
            throw new Exception("Dati mancanti o formato JSON non valido. Richiesto campo 'stato'.", 400);
        }
        if(!$inputData || !isset($inputData['id_sport'])){
            throw new Exception("Dati mancanti o formato JSON non valido. Richiesto campo 'id_sport'.", 400);
        } 

        $nome = trim($inputData['nome']);
        $anno = (int) $inputData['anno'];
        $stato =  trim($inputData['stato']);
        $id_sport = (int) $inputData['id_sport'];

        if (empty($nome)) {
            throw new Exception("Il campo 'nome' non può essere vuoto", 400);
        }
        if (empty($anno)) {
            throw new Exception("Il campo 'anno' non può essere vuoto", 400);
        }
        if (empty($stato)) {
            throw new Exception("Il campo 'stato' non può essere vuoto", 400);
        }
        if (empty($id_sport)) {
            throw new Exception("Il campo 'id_sport' non può essere vuoto", 400);
        }

        $query = "INSERT INTO tornei (nome, anno, stato, id_sport) VALUES (?, ?, ?, ?)";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("sisi", $nome, $anno, $stato, $id_sport);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $result = [
                "id_torneo" => $newId,
                "nome" => $nome,
                "anno" => $anno,
                "stato" => $stato,
                "id_sport" => $id_sport
            ];
            
            // Costruzione risposta per POST
            $response["status"] = "success";
            $response["message"] = "Torneo creato con successo";
            $response["data"] = $result;
            http_response_code(201); // 201 Created
        } else {
            throw new Exception("Errore durante l'inserimento del torneo", 500);
        }
        $stmt->close();
    }
    elseif($method === 'PUT'){
        $inputData = json_decode(file_get_contents("php://input"), true);
        if (empty($inputData)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }
        if (empty($inputData["id_torneo"])) {
            throw new Exception("Il campo 'id_torneo' è obbligatorio per l'aggiornamento", 400);
        }
        if (!isset($inputData["stato"])) {
            throw new Exception("Il campo 'stato' è obbligatorio", 400);
        }

        $id_torneo = (int) $inputData["id_torneo"];
        $stato = trim($inputData['stato']);

        // Verifica che il torneo esista
        $check = $mysqli->prepare("SELECT id_torneo FROM tornei WHERE id_torneo = ?");
        $check->bind_param("i", $id_torneo);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Torneo non trovato", 404);
        }
        $check->close();

        $stato = trim($inputData['stato']);

        $stmt = $mysqli->prepare("
            UPDATE tornei
            SET stato = ?
            WHERE id_torneo = ?
        ");

        $stmt->bind_param("si", $stato, $id_torneo);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Torneo aggiornato con successo";
        $response["data"]    = ["id_torneo" => $id_torneo];

        http_response_code(200);
    }
    elseif($method === 'DELETE'){
        if (empty($queryParams["id_torneo"])){
            throw new Exception("paramtero 'id_torneo' obbligatorio per l'eliminazione");
        }

        $id_torneo = (int) $queryParams['id_torneo'];

        $check = $mysqli->prepare("SELECT id_torneo FROM tornei WHERE id_torneo = ?");
        $check->bind_param("i", $id_torneo);
        $check->execute();
        if(!$check->get_result()->fetch_assoc()){
            $check->close();
            throw new Exception("Torneo non trovato", 404);
        }
        $check->close();

        $stmt = $mysqli->prepare("DELETE FROM tornei WHERE id_torneo = ?");
        $stmt->bind_param("i", $id_torneo);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Torneo eliminato con successo";
        $response["data"]    = [
            "id_torneo" => $id_torneo,
            "deleted"      => true,
        ];

        http_response_code(200);
    }else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }
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
?>