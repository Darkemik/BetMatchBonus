<?php
require_once __DIR__ . '/../connect.php';

// Check table exists
$r = $conn->query("SHOW TABLES LIKE 'Notifications'");
echo "Table exists: " . ($r->num_rows ? "YES" : "NO") . "\n";

// Show structure
$r2 = $conn->query("DESCRIBE Notifications");
if ($r2) {
    echo "\n=== STRUCTURE ===\n";
    while ($row = $r2->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Null'] . " | " . $row['Default'] . "\n";
    }
}

// Count rows
$r3 = $conn->query("SELECT COUNT(*) as cnt FROM Notifications");
$row = $r3->fetch_assoc();
echo "\nTotal rows: " . $row['cnt'] . "\n";

// Sample data
$r4 = $conn->query("SELECT * FROM Notifications ORDER BY created_at DESC LIMIT 5");
if ($r4 && $r4->num_rows > 0) {
    echo "\n=== SAMPLE DATA ===\n";
    while ($row = $r4->fetch_assoc()) {
        echo "id={$row['id']} | user_id={$row['user_id']} | type={$row['type']} | title={$row['title']} | is_read={$row['is_read']} | {$row['created_at']}\n";
    }
} else {
    echo "\nNo data in table.\n";
}
