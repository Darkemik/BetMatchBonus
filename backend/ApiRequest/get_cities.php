<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

// Lekérjük az összes várost, rendezve név szerint
$query = "SELECT id, name FROM Cities WHERE is_active = 1 ORDER BY name ASC";
$result = $conn->query($query);

$cities = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cities[] = $row;
    }
}

echo json_encode(['success' => true, 'cities' => $cities]);
$conn->close();
?>
