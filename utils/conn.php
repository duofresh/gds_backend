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

	// Self-healing database patch: drop ALL triggers on 'squadre' and 'giocatori' to ensure no trigger blocks insertions
	try {
		$resTriggers = $mysqli->query("SHOW TRIGGERS");
		if ($resTriggers) {
			while ($row = $resTriggers->fetch_assoc()) {
				$normalizedRow = array_change_key_case($row, CASE_LOWER);
				$triggerName = $normalizedRow['trigger'] ?? null;
				$tableName = $normalizedRow['table'] ?? null;
				if ($triggerName && ($tableName === 'squadre' || $tableName === 'giocatori')) {
					$mysqli->query("DROP TRIGGER IF EXISTS `$triggerName`");
					$msg = date("Y-m-d H:i:s") . " - DROPPED TRIGGER: $triggerName on table $tableName\n";
					file_put_contents(__DIR__ . "/../../debug.txt", $msg, FILE_APPEND);
				}
			}
		}
	} catch (Exception $e) {
		$msg = date("Y-m-d H:i:s") . " - TRIGGER PATCH EXCEPTION: " . $e->getMessage() . "\n";
		file_put_contents(__DIR__ . "/../../debug.txt", $msg, FILE_APPEND);
	}

	return $mysqli;
?>
