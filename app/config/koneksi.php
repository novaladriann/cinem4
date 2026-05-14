<?php
require_once __DIR__ . '/env.php';

$dbHost = (string) cinem4_env('DB_HOST', 'localhost');
$dbUser = (string) cinem4_env('DB_USERNAME', 'root');
$dbPass = (string) cinem4_env('DB_PASSWORD', '');
$dbName = (string) cinem4_env('DB_DATABASE', 'db_cinem4_1_');
$dbPort = (int) cinem4_env('DB_PORT', 3306);

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

if ($conn->connect_error) {
    die('Koneksi gagal: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
