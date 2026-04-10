<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../connect.php';

$postalCode = trim($_GET['postal_code'] ?? '');

if (empty($postalCode)) {
    echo json_encode(['success' => false, 'error' => 'Hiányzó irányítószám']);
    exit;
}

$stmt = $conn->prepare("SELECT DISTINCT city FROM PostalCodes WHERE postal_code = ? LIMIT 5");
$stmt->bind_param("s", $postalCode);
$stmt->execute();
$result = $stmt->get_result();
$cities = [];

while ($row = $result->fetch_assoc()) {
    $cities[] = $row['city'];
}
$stmt->close();

if (count($cities) > 0) {
    echo json_encode(['success' => true, 'city' => $cities[0], 'all_cities' => $cities]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ismeretlen irányítószám']);
}
