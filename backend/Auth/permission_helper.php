<?php
/**
 * Jogosultság ellenőrzés a RolePermissions tábla alapján.
 * Használat: require + check_page_permission('dashboard');
 * SUPERADMIN mindig hozzáfér mindenhez. Staff oldal mindig SUPERADMIN only.
 */

function check_page_permission($pageKey) {
    if (!isset($_SESSION['admin_role'])) return false;

    // SUPERADMIN mindig hozzáfér
    if ($_SESSION['admin_role'] === 'SUPERADMIN') return true;

    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../connect.php';
    }

    $roleName = $_SESSION['admin_role'];

    $stmt = $conn->prepare("
        SELECT rp.can_access
        FROM RolePermissions rp
        JOIN Roles r ON rp.role_id = r.id
        WHERE r.name = ? AND rp.page_key = ?
    ");
    $stmt->bind_param("ss", $roleName, $pageKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (bool)$row['can_access'] : false;
}

/**
 * Oldal guard: ha nincs jogosultság, die() üzenettel.
 */
function page_permission_guard($pageKey) {
    if (!check_page_permission($pageKey)) {
        die('Nincs jogosultságod ehhez az oldalhoz.');
    }
}

/**
 * Sidebar-hoz: az aktuális role összes jogosultsága.
 * Visszaad: ['dashboard' => true, 'tickets' => true, 'bonuses' => false, ...]
 */
function get_role_permissions($roleName = null) {
    if (!$roleName) $roleName = $_SESSION['admin_role'] ?? '';

    // SUPERADMIN mindenhez hozzáfér
    if ($roleName === 'SUPERADMIN') {
        return [
            'dashboard' => true, 'registrations' => true, 'tickets' => true,
            'bonuses' => true, 'deposits' => true, 'withdrawals' => true,
            'statistics' => true, 'notifications' => true
        ];
    }

    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../connect.php';
    }

    $stmt = $conn->prepare("
        SELECT rp.page_key, rp.can_access
        FROM RolePermissions rp
        JOIN Roles r ON rp.role_id = r.id
        WHERE r.name = ?
    ");
    $stmt->bind_param("s", $roleName);
    $stmt->execute();
    $result = $stmt->get_result();

    $perms = [];
    while ($row = $result->fetch_assoc()) {
        $perms[$row['page_key']] = (bool)$row['can_access'];
    }
    $stmt->close();

    return $perms;
}
