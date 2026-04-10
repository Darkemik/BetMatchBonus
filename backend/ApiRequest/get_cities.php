<?php
/**
 * GET_CITIES.PHP — Magyar városok autocomplete keresés
 * 
 * GET ?q=bud  →  [{"id":1,"name":"Budapest"}, ...]
 */
require_once dirname(__DIR__) . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

// Magyarország country_id lekérése
$countryStmt = $conn->prepare("SELECT id FROM Countries WHERE code = 'HUN' LIMIT 1");
$countryStmt->execute();
$countryResult = $countryStmt->get_result();
$country = $countryResult->fetch_assoc();
$countryStmt->close();

if (!$country) {
    echo json_encode([]);
    exit;
}

$search = $q . '%';
$stmt = $conn->prepare("
    SELECT id, name 
    FROM Cities 
    WHERE country_id = ? AND name LIKE ? AND is_active = 1
    ORDER BY name ASC
    LIMIT 15
");
$stmt->bind_param("is", $country['id'], $search);
$stmt->execute();
$res = $stmt->get_result();

$cities = [];
while ($row = $res->fetch_assoc()) {
    $cities[] = ['id' => (int)$row['id'], 'name' => $row['name']];
}
$stmt->close();

echo json_encode($cities, JSON_UNESCAPED_UNICODE);
