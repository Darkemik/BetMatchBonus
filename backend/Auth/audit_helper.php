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

/**
 * Felhasználói tevékenységnapló bejegyzés.
 * Használat:
 *   log_activity($userId, 'bonus', 'Free Bet jóváírás: 1.000 Ft');
 */
function log_activity(int $userId, string $activityType, string $description): void {
    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../connect.php';
    }
    if ($userId <= 0) return;

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    $stmt = $conn->prepare("
        INSERT INTO ActivityLog (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issss", $userId, $activityType, $description, $ip, $ua);
    $stmt->execute();
    $stmt->close();
}

/**
 * Egyenleg-történet bejegyzés a BalanceHistory táblába.
 * Használat:
 *   log_balance_change($userId, $previousBalance, $newBalance, $changeAmount, 'Befizetés: Visa', $transactionId);
 */
function log_balance_change(int $userId, float $previousBalance, float $newBalance, float $changeAmount, string $reason, ?int $transactionId = null): void {
    global $conn;
    if (!$conn) {
        require_once __DIR__ . '/../connect.php';
    }
    if ($userId <= 0) return;

    $stmt = $conn->prepare("
        INSERT INTO BalanceHistory (user_id, transaction_id, previous_balance, new_balance, change_amount, reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiddds", $userId, $transactionId, $previousBalance, $newBalance, $changeAmount, $reason);
    $stmt->execute();
    $stmt->close();
}
