<?php
require_once __DIR__ . '/../connect.php';
try {
    $conn->query("ALTER TABLE UserSessions ADD COLUMN location VARCHAR(100) DEFAULT NULL AFTER ip_address");
    echo "OK: location column added\n";
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "SKIP: column already exists\n";
    } else {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}
