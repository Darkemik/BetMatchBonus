<?php
/**
 * Audit log helper.
 * Használat:
 *   require_once __DIR__ . '/../Auth/audit_helper.php';
 *   log_audit('user_approve', 'user', $userId, 'Felhasználó jóváhagyva: username');
 */

function log_audit(string $actionType, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../connect.php';
    }

    $adminId = $_SESSION['admin_id'] ?? 0;
    if ($adminId <= 0) return;

    $stmt = $conn->prepare("
        INSERT INTO AuditLogs (admin_id, action_type, target_type, target_id, details)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issis", $adminId, $actionType, $targetType, $targetId, $details);
    $stmt->execute();
    $stmt->close();
}
