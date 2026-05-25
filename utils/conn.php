<?php
	$db_host = '127.0.0.1';
	$db_name = 'gds';
	$db_user = 'root';
	$db_pass = '';

	$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

	$mysqli -> set_charset("utf8mb4");

	return $mysqli;
?>
