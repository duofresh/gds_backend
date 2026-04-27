<?php
/**
 * Script per testare la connessione al database
 */

header('Content-Type: text/plain; charset=utf-8');

$host = '127.0.0.1'; // Può essere 'localhost' a seconda della configurazione XAMPP
$dbname = 'gds_backend'; // Cambia questo col nome reale del tuo database
$user = 'root'; // Utente di default XAMPP
$password = ''; // Password di default XAMPP (solitamente vuota)

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Tenta la connessione
    $pdo = new PDO($dsn, $user, $password, $options);
    
    echo "✅ Connessione al database riuscita con successo!\n";
    echo "Host: $host\n";
    echo "Database: $dbname\n";

} catch (PDOException $e) {
    echo "❌ Errore di connessione:\n";
    echo $e->getMessage() . "\n";
    echo "\nAssicurati che:\n";
    echo "1. XAMPP (Apache e MySQL) sia avviato.\n";
    echo "2. Il nome del database '$dbname' sia corretto.\n";
    echo "3. Le credenziali utente ('$user') e password siano corrette.\n";
}

?>
