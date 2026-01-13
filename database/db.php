<?php
$host = 'mysql-db02.remote:32636';
$user = 'FIRAOL ';
$pass = 'firaol@1995';
$db   = 'business_php';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
?>
