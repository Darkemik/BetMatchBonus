<?php
require_once __DIR__ . '/../connect.php';

// Show table structure
$r = $conn->query("DESCRIBE SystemSettings");
echo "=== TABLE STRUCTURE ===\n";
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " | " . $row['Type'] . "\n";
}

// Show security settings
echo "\n=== SECURITY SETTINGS ===\n";
$r = $conn->query("SELECT * FROM SystemSettings WHERE category='security'");
while ($row = $r->fetch_assoc()) {
    echo $row['setting_key'] . " = [" . $row['setting_value'] . "]\n";
}
