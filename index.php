<?php
// routing dinamico
$request = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
 

$base_path = "/gds_backend/";
if (strpos($request, $base_path) === 0) {
    $request = substr($request, strlen($base_path));
}
 
$request = "/" . ltrim($request, "/");
 
switch ($request) {
    case "/":
        echo "OK";
        break;
    case "/api/squadre":
        include "api/squadre.php";
        break;
    case "/api/giocatori":
        include "api/giocatori.php";
        break;
    case "/api/sport":
        include "api/sport.php";
        break;
    case "/api/tornei":
        include "api/tornei.php";
        break;
    case "/api/match":
    case "/api/match_torneo":
        include "api/match.php";
        break;
    case "/api/iscrizioni":
        include "api/iscrizioni.php";
        break;
    default:
        http_response_code(404);
        echo "404 - Not Found: " . htmlspecialchars($request);
        break;
}
?>