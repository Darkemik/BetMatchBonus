<?php
require_once __DIR__ . '/../connect.php';
$r = $conn->query('SELECT COUNT(*) as c FROM PostalCodes');
$row = $r->fetch_assoc();
echo "PostalCodes count: " . $row['c'] . "\n";
$r2 = $conn->query("SELECT * FROM PostalCodes WHERE postal_code = '1051' LIMIT 1");
$row2 = $r2->fetch_assoc();
echo "Test 1051: " . ($row2 ? $row2['city'] : 'NOT FOUND') . "\n";
