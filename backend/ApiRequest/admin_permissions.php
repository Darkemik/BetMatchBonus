<?php
/**
 * Szerepkör jogosultságok kezelése
 * GET: jogosultságok lekérdezése
 * POST: jogosultságok mentése
 */
session_start();
require_once __DIR__ . '/../Auth/admin_guard.php';

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs admin bejelentkezés.']);
    exit;
}
admin_guard('SUPERADMIN');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/audit_helper.php';

header('Content-Type: application/json');

/* ━━━━━ GET: összes jogosultság lekérdezése ━━━━━━━━━━━━━━━━━━━ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("
        SELECT r.id AS role_id, r.name AS role_name, r.description,
               rp.page_key, rp.can_access
        FROM Roles r
        LEFT JOIN RolePermissions rp ON r.id = rp.role_id
        WHERE r.id IN (1, 2)
        ORDER BY r.id, rp.page_key
    ");

    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $rid = (int)$row['role_id'];
        if (!isset($roles[$rid])) {
            $roles[$rid] = [
                'role_id' => $rid,
                'role_name' => $row['role_name'],
                'description' => $row['description'],
                'permissions' => []
            ];
        }
        if ($row['page_key']) {
            $roles[$rid]['permissions'][$row['page_key']] = (bool)$row['can_access'];
        }
    }

    echo json_encode(['success' => true, 'roles' => array_values($roles)]);
    exit;
}

/* ━━━━━ POST: jogosultságok mentése ━━━━━━━━━━━━━━━━━━━━━━━━━━ */
$input = json_decode(file_get_contents('php://input'), true);
$roleId = (int)($input['role_id'] ?? 0);
$permissions = $input['permissions'] ?? [];

if ($roleId <= 0 || $roleId > 2) {
    echo json_encode(['success' => false, 'message' => 'Csak MOD és ADMIN szerepkörök módosíthatók!']);
    exit;
}

$validPages = ['dashboard', 'registrations', 'tickets', 'bonuses', 'deposits', 'withdrawals', 'statistics', 'notifications'];

$conn->begin_transaction();
try {
    foreach ($validPages as $page) {
        $canAccess = isset($permissions[$page]) && $permissions[$page] ? 1 : 0;
        $stmt = $conn->prepare("
            INSERT INTO RolePermissions (role_id, page_key, can_access)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)
        ");
        $stmt->bind_param("isi", $roleId, $page, $canAccess);
        $stmt->execute();
        $stmt->close();
    }
    $conn->commit();
} catch (\Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Hiba: ' . $e->getMessage()]);
    exit;
}

// Role neve
$rStmt = $conn->prepare("SELECT name FROM Roles WHERE id = ?");
$rStmt->bind_param("i", $roleId);
$rStmt->execute();
$roleName = $rStmt->get_result()->fetch_assoc()['name'] ?? '';
$rStmt->close();

log_audit('perm_update', 'role', $roleId, $roleName . ' jogosultságok módosítva');
echo json_encode(['success' => true, 'message' => $roleName . ' jogosultságok mentve!']);
