<?php
require_once __DIR__ . '/../connect.php';

// Add missing statistics & notifications permissions for all roles
$inserts = [
    [1, 'statistics', 0],
    [1, 'notifications', 0],
    [2, 'statistics', 1],
    [2, 'notifications', 1],
    [3, 'statistics', 1],
    [3, 'notifications', 1],
];

$stmt = $conn->prepare("INSERT INTO RolePermissions (role_id, page_key, can_access) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)");

foreach ($inserts as [$roleId, $pageKey, $canAccess]) {
    $stmt->bind_param("isi", $roleId, $pageKey, $canAccess);
    $stmt->execute();
    echo "Role $roleId - $pageKey: " . ($stmt->affected_rows > 0 ? "INSERTED" : "already exists") . "\n";
}
$stmt->close();
echo "Done!\n";
