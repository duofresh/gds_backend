<?php
/**
 * GDS Backend - API Template degli endpoint
 *
 * Struttura standardizzata per gli endpoint dell'api,
 * header json, handling degli errori, e struttura consistente delle risposte.
 */

// 1. Header (Intestazione) - per comunicare in JSON
header("Access-Control-Allow-Origin: *"); //Policy CORS
header("Content-Type: application/json; charset=UTF-8"); //tipo
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); //metodi hhtp
header("Access-Control-Max-Age: 3600");
header(
    "Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With",
);

// Handling delle richieste OPTIONS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// 2. INizializzazione risposta
$response = [
    "status" => "error",
    "message" => "Internal Server Error",
    "data" => null,
];

try {
    // 3. Validazione metodo richiesta
    $method = $_SERVER["REQUEST_METHOD"];

    // 4. Parse input
    // per corpo json:
    $input = json_decode(file_get_contents("php://input"), true);
    // per parametri query:
    $queryParams = $_GET;

    // --- LOGICA BASE VA QUI ---

    // Esempio; richiedi dati o formatta richiesta
    // if ($method === 'GET') {
    //     $response["status"] = "success";
    //     $response["message"] = "Data retrieved successfully";
    //     $response["data"] = ["example" => "value"];
    //     http_response_code(200);
    // } else {
    //     throw new Exception("Method not allowed", 405);
    // }

    // placeholder per implementazione effettiva
    $response["status"] = "success"; // COSTRUZIONE RISPOSTA NEL CASO DI SUCCESSO ↓
    $response["message"] = "Endpoint raggiunto con successo"; //
    $response["data"] = [
        "method" => $method,
        "timestamp" => date("Y-m-d H:i:s"),
    ];
    http_response_code(200); //

    // --- FINE LOGICA ---
} catch (Exception $e) {
    // 5. standardizzazione errori
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);

    $response["status"] = "error";
    $response["message"] = $e->getMessage();
}

// 6. output finale (json)
echo json_encode($response);
exit();
