<?php
	$db_host = '127.0.0.1';
	$db_name = 'giornatadellosport';
	$db_user = 'root';
	$db_pass = '';

	$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

	$mysqli -> set_charset("utf8mb4");

	// Self-healing database patch: ensure 'stato' column exists in 'tornei' table
	try {
		$checkTable = $mysqli->query("SHOW TABLES LIKE 'tornei'");
		if ($checkTable && $checkTable->num_rows > 0) {
			$checkCol = $mysqli->query("SHOW COLUMNS FROM tornei LIKE 'stato'");
			if ($checkCol && $checkCol->num_rows == 0) {
				$mysqli->query("ALTER TABLE tornei ADD COLUMN stato VARCHAR(50) DEFAULT 'programmato'");
			}
		}
	} catch (Exception $e) {
		// Ignore any bootstrap schema errors so as not to block standard operations
	}

	return $mysqli;
?>
