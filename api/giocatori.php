<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Gestione preflight OPTIONS (CORS)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit();
}

$response = [
    "status"  => "error",
    "message" => "Internal Server Error",
    "data"    => null,
];

try {

    $method = $_SERVER["REQUEST_METHOD"];

    $input = json_decode(file_get_contents("php://input"), true);
    $queryParams = $_GET;

    if ($method === "GET") {
        $id = $queryParams["id"] ?? null;

        if ($id !== null) {
            // TODO: sostituisci con query DB o logica
            $response["status"]  = "success";
            $response["message"] = "Record recuperato con successo";
            $response["data"]    = [
                "id"        => (int) $id,
                "esempio"   => "valore",
                "timestamp" => date("Y-m-d H:i:s"),
            ];
        } else {
            $response["status"]  = "success";
            $response["message"] = "Lista recuperata con successo";
            $response["data"]    = [
                ["id" => 1, "esempio" => "alpha"],
                ["id" => 2, "esempio" => "beta"],
            ];
        }

        http_response_code(200);

    } elseif ($method === "POST") {
        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }

        // Validazione campi obbligatori, da sistemare in base alle necessità
        if (empty($input["nome"])) {
            throw new Exception("Il campo 'nome' è obbligatorio", 400);
        }

        // TODO: logica di salvataggio (INSERT nel DB)
        $nuovoId = rand(100, 999); // placeholder – sostituisci con l'ID reale

        $response["status"]  = "success";
        $response["message"] = "Risorsa creata con successo";
        $response["data"]    = [
            "id"        => $nuovoId,
            "nome"      => $input["nome"],
            "timestamp" => date("Y-m-d H:i:s"),
        ];

        http_response_code(201);

    } elseif ($method === "PUT") {
        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }

        if (empty($input["id"])) {
            throw new Exception("Il campo 'id' è obbligatorio per l'aggiornamento", 400);
        }

        // TODO: logica di aggiornamento (UPDATE nel DB)

        $response["status"]  = "success";
        $response["message"] = "Risorsa aggiornata con successo";
        $response["data"]    = [
            "id"        => $input["id"],
            "aggiornato" => true,
            "timestamp" => date("Y-m-d H:i:s"),
        ];

        http_response_code(200);

    } elseif ($method === "DELETE") {
        $id = $queryParams["id"] ?? null;

        if ($id === null) {
            throw new Exception("Parametro 'id' obbligatorio per la cancellazione", 400);
        }

        // TODO: logica di eliminazione (DELETE nel DB)

        $response["status"]  = "success";
        $response["message"] = "Risorsa eliminata con successo";
        $response["data"]    = [
            "id"      => (int) $id,
            "deleted" => true,
        ];

        http_response_code(200);

    } else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }

} catch (Exception $e) {

    $code = $e->getCode();

    // codice HTTP valido (400-599)
    if ($code < 400 || $code > 599) {
        $code = 500;
    }

    $response["status"]  = "error";
    $response["message"] = $e->getMessage();
    $response["data"]    = null;

    http_response_code($code);
}
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);