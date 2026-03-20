<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "betmatchbonusbeta";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Adatbázis kapcsolódási hiba: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");