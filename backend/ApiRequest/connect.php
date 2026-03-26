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

// Automatikus Hétköznapi Bónusz aktiválás/inaktiválás (hétfő-péntek között aktív, hétvégén inaktív)
$conn->query("UPDATE BonusCodes SET is_active = IF(DAYOFWEEK(CURRENT_DATE) IN (2,3,4,5,6), 1, 0) WHERE code = 'BONUSZHETKOZNAP5K'");