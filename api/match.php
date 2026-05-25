<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
        if (isset($queryParams["id_torneo"])) {
            $id_torneo = filter_var(
                $queryParams["id_torneo"],
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );

            if ($id_torneo === false) {
                throw new Exception("id_torneo non valido", 400);
            }

            if (isset($queryParams["id_match"]) && isset($queryParams["turno"])) {
                // Singolo match
                $id_match = filter_var(
                    $queryParams["id_match"],
                    FILTER_VALIDATE_INT,
                    ["options" => ["min_range" => 1]]
                );
                $turno = filter_var(
                    $queryParams["turno"],
                    FILTER_VALIDATE_INT,
                    ["options" => ["min_range" => 1]]
                );

                if ($id_match === false) {
                    throw new Exception("id_match non valido", 400);
                }

                if ($turno === false) {
                    throw new Exception("turno non valido", 400);
                }

                $stmt = $mysqli->prepare("
                    SELECT m.*, s1.nome AS nome_squadra1, s2.nome AS nome_squadra2, sv.nome AS nome_vincitore
                    FROM match_torneo m
                    LEFT JOIN squadre s1 ON m.id_squadra1 = s1.id_squadra
                    LEFT JOIN squadre s2 ON m.id_squadra2 = s2.id_squadra
                    LEFT JOIN squadre sv ON m.id_vincitore = sv.id_squadra
                    WHERE m.id_torneo = ? AND m.id_match = ? AND m.turno = ?
                ");
                $stmt->bind_param("iii", $id_torneo, $id_match, $turno);
                $stmt->execute();
                $match = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$match) {
                    throw new Exception("Match non trovato", 404);
                }

                $response["status"]  = "success";
                $response["message"] = "Match recuperato con successo";
                $response["data"]    = $match;

            } else {
                // Tutti i match di un torneo
                $stmt = $mysqli->prepare("
                    SELECT m.*, s1.nome AS nome_squadra1, s2.nome AS nome_squadra2, sv.nome AS nome_vincitore
                    FROM match_torneo m
                    LEFT JOIN squadre s1 ON m.id_squadra1 = s1.id_squadra
                    LEFT JOIN squadre s2 ON m.id_squadra2 = s2.id_squadra
                    LEFT JOIN squadre sv ON m.id_vincitore = sv.id_squadra
                    WHERE m.id_torneo = ?
                    ORDER BY m.turno, m.id_match
                ");
                $stmt->bind_param("i", $id_torneo);
                $stmt->execute();
                $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $response["status"]  = "success";
                $response["message"] = "Lista match del torneo recuperata con successo";
                $response["data"]    = $matches;
            }
        } else {
            // Tutti i match in generale
            $result = $mysqli->query("
                SELECT m.*, s1.nome AS nome_squadra1, s2.nome AS nome_squadra2, sv.nome AS nome_vincitore
                FROM match_torneo m
                LEFT JOIN squadre s1 ON m.id_squadra1 = s1.id_squadra
                LEFT JOIN squadre s2 ON m.id_squadra2 = s2.id_squadra
                LEFT JOIN squadre sv ON m.id_vincitore = sv.id_squadra
                ORDER BY m.id_torneo, m.turno, m.id_match
            ");
            $matches = $result->fetch_all(MYSQLI_ASSOC);

            $response["status"]  = "success";
            $response["message"] = "Lista di tutti i match recuperata con successo";
            $response["data"]    = $matches;
        }

        http_response_code(200);

    } elseif ($method === "POST") {
        if (empty($input)) {
            throw new Exception("Body JSON mancante o non valido", 400);
        }

        if (!isset($input["id_torneo"], $input["id_match"], $input["turno"], $input["id_squadra1"])) {
            throw new Exception("Campi obbligatori mancanti: id_torneo, id_match, turno, id_squadra1", 400);
        }

        $id_torneo = filter_var(
            $input["id_torneo"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        $id_match = filter_var(
            $input["id_match"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        $turno = filter_var(
            $input["turno"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        $id_squadra1 = filter_var(
            $input["id_squadra1"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        if ($id_torneo === false || $id_match === false || $turno === false || $id_squadra1 === false) {
            throw new Exception("id_torneo, id_match, turno e id_squadra1 devono essere interi maggiori di 0", 400);
        }

        $id_squadra2 = null;
        if (array_key_exists("id_squadra2", $input) && $input["id_squadra2"] !== null) {
            $id_squadra2 = filter_var(
                $input["id_squadra2"],
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );
            if ($id_squadra2 === false) {
                throw new Exception("id_squadra2 deve essere un intero maggiore di 0", 400);
            }
        }

        $data_match  = $input["data_match"] ?? null;
        $stato_match = $input["stato_match"] ?? 'PROGRAMMATO';
        $punteggio1  = 0;
        if (array_key_exists("punteggio1", $input) && $input["punteggio1"] !== null) {
            $punteggio1 = filter_var(
                $input["punteggio1"],
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 0]]
            );
            if ($punteggio1 === false) {
                throw new Exception("punteggio1 deve essere un intero maggiore o uguale a 0", 400);
            }
        }

        $punteggio2  = 0;
        if (array_key_exists("punteggio2", $input) && $input["punteggio2"] !== null) {
            $punteggio2 = filter_var(
                $input["punteggio2"],
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 0]]
            );
            if ($punteggio2 === false) {
                throw new Exception("punteggio2 deve essere un intero maggiore o uguale a 0", 400);
            }
        }

        $id_vincitore = null;
        if (array_key_exists("id_vincitore", $input) && $input["id_vincitore"] !== null) {
            $id_vincitore = filter_var(
                $input["id_vincitore"],
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );
            if ($id_vincitore === false) {
                throw new Exception("id_vincitore deve essere un intero maggiore di 0", 400);
            }
        }

        $stmt = $mysqli->prepare("
            INSERT INTO match_torneo (id_torneo, id_match, turno, id_squadra1, id_squadra2, data_match, stato_match, punteggio1, punteggio2, id_vincitore)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiiiissiii", $id_torneo, $id_match, $turno, $id_squadra1, $id_squadra2, $data_match, $stato_match, $punteggio1, $punteggio2, $id_vincitore);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Match creato con successo";
        $response["data"]    = [
            "id_torneo" => $id_torneo,
            "id_match"  => $id_match,
            "turno"     => $turno
        ];
        http_response_code(201);

    } elseif ($method === "PUT") {
        if (empty($input)) {
            throw new Exception("Body JSON mancante", 400);
        }

        if (!isset($input["id_torneo"], $input["id_match"], $input["turno"])) {
            throw new Exception("Chiavi primarie mancanti per aggiornare un match: id_torneo, id_match, turno", 400);
        }

        $id_torneo = filter_var($input["id_torneo"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $id_match  = filter_var($input["id_match"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $turno     = filter_var($input["turno"], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

        if ($id_torneo === false || $id_match === false || $turno === false) {
            throw new Exception("id_torneo, id_match e turno devono essere interi positivi", 400);
        }

        // Verifica esistenza match prima dell'aggiornamento
        $check = $mysqli->prepare("SELECT 1 FROM match_torneo WHERE id_torneo = ? AND id_match = ? AND turno = ?");
        $check->bind_param("iii", $id_torneo, $id_match, $turno);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $check->close();
            throw new Exception("Match non trovato", 404);
        }
        $check->close();

        $validateOptionalPositiveInt = function ($fieldName) use ($input) {
            if (!array_key_exists($fieldName, $input)) {
                return null;
            }

            $value = filter_var(
                $input[$fieldName],
                FILTER_VALIDATE_INT,
                ["options" => ["min_range" => 1]]
            );

            if ($value === false) {
                throw new Exception("Campo non valido: " . $fieldName, 400);
            }

            return $value;
        };

        $punteggio1    = isset($input["punteggio1"]) ? (int) $input["punteggio1"] : null;
        $punteggio2    = isset($input["punteggio2"]) ? (int) $input["punteggio2"] : null;
        $stato_match   = $input["stato_match"]   ?? null;
        $id_vincitore  = $validateOptionalPositiveInt("id_vincitore");
        $data_match    = $input["data_match"]    ?? null;
        $id_squadra1   = $validateOptionalPositiveInt("id_squadra1");
        $id_squadra2   = $validateOptionalPositiveInt("id_squadra2");

        $stmt = $mysqli->prepare("
            UPDATE match_torneo
            SET punteggio1   = COALESCE(?, punteggio1),
                punteggio2   = COALESCE(?, punteggio2),
                stato_match  = COALESCE(?, stato_match),
                id_vincitore = COALESCE(?, id_vincitore),
                data_match   = COALESCE(?, data_match),
                id_squadra1  = COALESCE(?, id_squadra1),
                id_squadra2  = COALESCE(?, id_squadra2)
            WHERE id_torneo = ? AND id_match = ? AND turno = ?
        ");
        $stmt->bind_param("iisisiiiii", $punteggio1, $punteggio2, $stato_match, $id_vincitore, $data_match, $id_squadra1, $id_squadra2, $id_torneo, $id_match, $turno);
        $stmt->execute();
        $stmt->close();

        $response["status"]  = "success";
        $response["message"] = "Match aggiornato con successo";
        http_response_code(200);

    } elseif ($method === "DELETE") {
        if (!isset($queryParams["id_torneo"]) || !isset($queryParams["id_match"]) || !isset($queryParams["turno"])) {
            throw new Exception("Parametri 'id_torneo', 'id_match' e 'turno' obbligatori per la cancellazione", 400);
        }

        $id_torneo = filter_var(
            $queryParams["id_torneo"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        $id_match = filter_var(
            $queryParams["id_match"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );
        $turno = filter_var(
            $queryParams["turno"],
            FILTER_VALIDATE_INT,
            ["options" => ["min_range" => 1]]
        );

        if ($id_torneo === false || $id_match === false || $turno === false) {
            throw new Exception("I parametri 'id_torneo', 'id_match' e 'turno' devono essere interi maggiori di 0", 400);
        }

        $stmt = $mysqli->prepare("DELETE FROM match_torneo WHERE id_torneo = ? AND id_match = ? AND turno = ?");
        $stmt->bind_param("iii", $id_torneo, $id_match, $turno);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows === 0) {
            throw new Exception("Match non trovato", 404);
        }

        $response["status"]  = "success";
        $response["message"] = "Match eliminato con successo";
        http_response_code(200);

    } else {
        throw new Exception("Metodo HTTP non supportato: $method", 405);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    $response["status"]  = "error";
    $response["message"] = "Errore di connessione o esecuzione query sul Database";
    $response["data"]    = null;
    error_log("DB error in api/match.php: " . $e->getMessage());

} catch (Exception $e) {
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 500);
    $response["status"]  = "error";
    $response["message"] = $e->getMessage();
    $response["data"]    = null;
} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
