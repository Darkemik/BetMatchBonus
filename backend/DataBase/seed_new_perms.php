<?php
require_once __DIR__ . '/../connect.php';

$pages = ['statistics', 'notifications'];
$rolePerms = [
    1 => 0,  // MOD: no access
    2 => 1,  // ADMIN: access
    3 => 1   // SUPERADMIN: access
];

foreach ($pages as $page) {
    foreach ($rolePerms as $roleId => $access) {
        $stmt = $conn->prepare('INSERT IGNORE INTO RolePermissions (role_id, page_key, can_access) VALUES (?, ?, ?)');
        $stmt->bind_param('isi', $roleId, $page, $access);
        $stmt->execute();
        echo "Role $roleId -> $page = $access: " . ($stmt->affected_rows > 0 ? 'INSERTED' : 'EXISTS') . "\n";
        $stmt->close();
    }
}

echo "Done!\n";
