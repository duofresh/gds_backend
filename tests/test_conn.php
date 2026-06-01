<?php
/**
 * Script per testare la connessione al database
 */

header('Content-Type: text/plain; charset=utf-8');



mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Tenta la connessione tramite mysqli
    $mysqli = require_once __DIR__ . '/../utils/conn.php';
    
    echo "✅ Connessione al database riuscita con successo!\n";
    echo "Database: giornatadellosport\n";

    $mysqli->close();

} catch (mysqli_sql_exception $e) {
    echo "❌ Errore di connessione:\n";
    echo $e->getMessage() . "\n";
    echo "\nAssicurati che:\n";
    echo "1. XAMPP (Apache e MySQL) sia avviato.\n";
    echo "2. Il nome del database sia corretto.\n";
    echo "3. Le credenziali utente e password siano corrette.\n";
}

?>
