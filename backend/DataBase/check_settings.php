<?php
require_once __DIR__ . '/../connect.php';

$r = $conn->query("SELECT setting_key, setting_value, label, description FROM SystemSettings WHERE category = 'security' ORDER BY setting_key");
while ($row = $r->fetch_assoc()) {
    echo $row['setting_key'] . " = " . $row['setting_value'] . " | label: " . $row['label'] . " | desc: " . $row['description'] . "\n";
}
