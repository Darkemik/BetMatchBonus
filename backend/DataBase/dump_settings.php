<?php
require_once __DIR__ . '/../connect.php';
$r = $conn->query("SELECT setting_key, setting_value, category, label, description, input_type FROM SystemSettings ORDER BY category, setting_key");
while ($row = $r->fetch_assoc()) {
    echo $row['setting_key'] . " | " . $row['setting_value'] . " | " . $row['category'] . " | " . $row['label'] . " | " . $row['description'] . " | " . $row['input_type'] . "\n";
}
