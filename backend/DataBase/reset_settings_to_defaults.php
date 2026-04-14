<?php
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/seed_system_settings.php';

// Add default_value column if not exists
$conn->query("ALTER TABLE SystemSettings ADD COLUMN IF NOT EXISTS default_value TEXT DEFAULT NULL AFTER setting_value");

$defaults = get_system_settings_defaults();

$stmt = $conn->prepare("UPDATE SystemSettings SET setting_value = ?, default_value = ?, description = ?, category = ?, label = ?, input_type = ? WHERE setting_key = ?");

$count = 0;
foreach ($defaults as [$key, $val, $desc, $cat, $lbl, $type]) {
    $stmt->bind_param('sssssss', $val, $val, $desc, $cat, $lbl, $type, $key);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $count++;
}
$stmt->close();

echo "$count beállítás visszaállítva alapértékre.\n";

// Verify
$r = $conn->query("SELECT setting_key, setting_value, default_value FROM SystemSettings ORDER BY category, setting_key");
while ($row = $r->fetch_assoc()) {
    echo $row['setting_key'] . " = " . $row['setting_value'] . " (default: " . $row['default_value'] . ")\n";
}
