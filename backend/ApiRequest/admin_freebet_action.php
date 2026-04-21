<?php
/**
 * Admin Free Bet API (bónusz alapú)
 * POST JSON: action = give_freebet | give_freebet_all | get_history
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('ADMIN');
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/permission_helper.php';
require_once __DIR__ . '/../Auth/audit_helper.php';

if (!check_page_permission('freebet')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nincs jogosultság']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

/**
 * Biztosítja, hogy létezik egy "Admin Free Bet" BonusCode.
 * Ha még nincs, létrehozza. Visszaadja az id-t.
 */
function getAdminFreeBetBonusId($conn): int {
    $stmt = $conn->prepare("SELECT id FROM BonusCodes WHERE code = '__ADMIN_FREEBET__' LIMIT 1");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return (int)$row['id'];

    // Létrehozás
    $ins = $conn->prepare("
        INSERT INTO BonusCodes
            (code, name, description, bonus_type_id, bonus_amount, bonus_trigger,
             bet_reward_type, is_active, per_user_limit, admin_force_active, wagering_multiplier)
        VALUES ('__ADMIN_FREEBET__', 'Admin Free Bet', 'Admin által adott free bet', 7, 0, 'MANUAL',
                'FREE_BET', 0, 0, 1, 0)
    ");
    $ins->execute();
    $newId = (int)$conn->insert_id;
    $ins->close();
    return $newId;
}

/**
 * Free Bet bónusz adása egy felhasználónak.
 * UserBonuses bejegyzést hoz létre free_bet_amount-tal.
 */
function giveFreeBetBonus($conn, int $bonusCodeId, int $userId, float $amount, int $expireHours): int {
    $expiresAt = date('Y-m-d H:i:s', time() + $expireHours * 3600);
    $status = 'ACTIVE';
    $zero = 0.00;

    $stmt = $conn->prepare("
        INSERT INTO UserBonuses
            (user_id, bonus_id, status, granted_amount, free_bet_amount,
             bonus_money_amount, bonus_balance, wagering_required, wagering_progress, expires_at)
        VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, ?)
    ");
    $stmt->bind_param("iisdds", $userId, $bonusCodeId, $status, $amount, $amount, $expiresAt);
    $stmt->execute();
    $ubId = (int)$conn->insert_id;
    $stmt->close();
    return $ubId;
}

function createUserNotification(mysqli $conn, int $userId, string $title, string $message, string $type = 'bonus'): void {
    $stmt = $conn->prepare("INSERT INTO Notifications (user_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $userId, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

switch ($action) {

    // ─── Free Bet adása (bónuszként) ───
    case 'give_freebet':
        $userId = (int)($input['user_id'] ?? 0);
        $amount = (float)($input['amount'] ?? 0);
        $expireHours = (int)($input['expire_hours'] ?? 72);
        $reason = trim($input['reason'] ?? '');

        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Válassz felhasználót!']);
            exit;
        }
        if ($amount < 100 || $amount > 1000000) {
            echo json_encode(['success' => false, 'message' => 'Az összeg 100 és 1.000.000 Ft között legyen!']);
            exit;
        }
        if ($expireHours < 1) $expireHours = 72;
        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Adj meg indoklást!']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, username FROM Users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'A felhasználó nem található vagy inaktív.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $bonusCodeId = getAdminFreeBetBonusId($conn);
            giveFreeBetBonus($conn, $bonusCodeId, $userId, $amount, $expireHours);

            // Értesítés
            $expiresAt = date('Y. m. d. H:i', time() + $expireHours * 3600);
            $notifTitle = 'Free Bet jóváírás!';
            $notifMsg = number_format($amount, 0, ',', '.') . ' Ft Free Bet-et kaptál! Azonnal felhasználhatod fogadáshoz.'
                . "\nLejárat: " . $expiresAt
                . "\nIndok: " . $reason;
            $notifStmt = $conn->prepare("
                INSERT INTO Notifications (user_id, title, message, type, created_at)
                VALUES (?, ?, ?, 'BONUS', NOW())
            ");
            $notifStmt->bind_param("iss", $userId, $notifTitle, $notifMsg);
            $notifStmt->execute();
            $notifStmt->close();

            $conn->commit();

            log_activity($userId, 'bonus', 'Free Bet jóváírás: ' . number_format($amount, 0, ',', '.') . ' Ft. Lejárat: ' . $expiresAt . '. Indok: ' . $reason);

            log_audit('admin_freebet', 'user', $userId,
                "Free Bet: {$amount} Ft → {$user['username']} (#{$userId}), lejárat: {$expireHours}h. Indok: {$reason}"
            );

            echo json_encode([
                'success' => true,
                'message' => number_format($amount, 0, ',', '.') . ' Ft Free Bet adva: ' . $user['username']
            ]);
        } catch (\Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
        }
        break;

    // ─── Free Bet MINDENKINEK ───
    case 'give_freebet_all':
        $amount = (float)($input['amount'] ?? 0);
        $expireHours = (int)($input['expire_hours'] ?? 72);
        $reason = trim($input['reason'] ?? '');

        if ($amount < 100 || $amount > 1000000) {
            echo json_encode(['success' => false, 'message' => 'Az összeg 100 és 1.000.000 Ft között legyen!']);
            exit;
        }
        if ($expireHours < 1) $expireHours = 72;
        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Adj meg indoklást!']);
            exit;
        }

        $usersQ = $conn->query("SELECT id, username FROM Users WHERE is_active = 1");
        $allUsersList = [];
        while ($row = $usersQ->fetch_assoc()) {
            $allUsersList[] = $row;
        }

        if (empty($allUsersList)) {
            echo json_encode(['success' => false, 'message' => 'Nincs aktív felhasználó.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            $bonusCodeId = getAdminFreeBetBonusId($conn);

            $notifStmt = $conn->prepare("
                INSERT INTO Notifications (user_id, title, message, type, created_at)
                VALUES (?, ?, ?, 'BONUS', NOW())
            ");
            $expiresAt = date('Y. m. d. H:i', time() + $expireHours * 3600);
            $notifTitle = 'Free Bet jóváírás!';
            $notifMsg = number_format($amount, 0, ',', '.') . ' Ft Free Bet-et kaptál! Azonnal felhasználhatod fogadáshoz.'
                . "\nLejárat: " . $expiresAt
                . "\nIndok: " . $reason;
            $count = 0;

            $actDesc = 'Free Bet jóváírás: ' . number_format($amount, 0, ',', '.') . ' Ft. Lejárat: ' . $expiresAt . '. Indok: ' . $reason;
            foreach ($allUsersList as $u) {
                $uid = (int)$u['id'];
                giveFreeBetBonus($conn, $bonusCodeId, $uid, $amount, $expireHours);
                $notifStmt->bind_param("iss", $uid, $notifTitle, $notifMsg);
                $notifStmt->execute();
                log_activity($uid, 'bonus', $actDesc);
                $count++;
            }
            $notifStmt->close();
            $conn->commit();

            log_audit('admin_freebet_all', 'system', null,
                "Free Bet MINDENKINEK: {$amount} Ft × {$count} fő, lejárat: {$expireHours}h. Indok: {$reason}"
            );

            echo json_encode([
                'success' => true,
                'message' => number_format($amount, 0, ',', '.') . ' Ft Free Bet adva ' . $count . ' felhasználónak!'
            ]);
        } catch (\Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
        }
        break;

    // ─── Előzmények (UserBonuses alapján) ───
    case 'get_history':
        $page = max(1, (int)($input['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Admin Free Bet BonusCode id
        $bcStmt = $conn->prepare("SELECT id FROM BonusCodes WHERE code = '__ADMIN_FREEBET__' LIMIT 1");
        $bcStmt->execute();
        $bcRow = $bcStmt->get_result()->fetch_assoc();
        $bcStmt->close();

        if (!$bcRow) {
            echo json_encode(['success' => true, 'history' => [], 'total' => 0, 'page' => 1, 'pages' => 1]);
            break;
        }
        $bcId = (int)$bcRow['id'];

        $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM UserBonuses WHERE bonus_id = ?");
        $countStmt->bind_param("i", $bcId);
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['c'];
        $countStmt->close();

        $stmt = $conn->prepare("
            SELECT ub.id, ub.user_id, u.username, ub.granted_amount,
                   ub.free_bet_amount, ub.status, ub.expires_at, ub.used, ub.created_at
            FROM UserBonuses ub
            JOIN Users u ON ub.user_id = u.id
            WHERE ub.bonus_id = ?
            ORDER BY ub.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $bcId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        $batchCounts = [];
        while ($row = $result->fetch_assoc()) {
            $isExpired = !empty($row['expires_at']) && strtotime($row['expires_at']) <= time();
            $displayStatus = $row['status'];
            if ($displayStatus === 'ACTIVE' && $isExpired) $displayStatus = 'EXPIRED';
            if ((int)$row['used'] === 1 && $displayStatus === 'ACTIVE') $displayStatus = 'COMPLETED';

            // Batch számolás: hány bejegyzés van ugyanazzal a created_at + bonus_id + granted_amount
            $batchKey = $row['created_at'] . '_' . $row['granted_amount'];
            if (!isset($batchCounts[$batchKey])) {
                $bcntStmt = $conn->prepare("
                    SELECT COUNT(*) AS cnt FROM UserBonuses
                    WHERE bonus_id = ? AND granted_amount = ? AND created_at = ?
                ");
                $bcntStmt->bind_param("ids", $bcId, $row['granted_amount'], $row['created_at']);
                $bcntStmt->execute();
                $batchCounts[$batchKey] = (int)$bcntStmt->get_result()->fetch_assoc()['cnt'];
                $bcntStmt->close();
            }

            $history[] = [
                'id'             => (int)$row['id'],
                'user_id'        => (int)$row['user_id'],
                'username'       => $row['username'],
                'amount'         => (float)$row['granted_amount'],
                'free_bet_left'  => (float)$row['free_bet_amount'],
                'status'         => $displayStatus,
                'expires_at'     => $row['expires_at'],
                'created_at'     => $row['created_at'],
                'batch_count'    => $batchCounts[$batchKey]
            ];
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'history' => $history,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, ceil($total / $limit))
        ]);
        break;

    // ─── Free Bet elvétele ───
    case 'revoke_freebet':
        $ubId = (int)($input['id'] ?? 0);
        if ($ubId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Érvénytelen ID.']);
            exit;
        }

        // Lekérjük az adott bejegyzést
        $ubStmt = $conn->prepare("
            SELECT ub.id, ub.user_id, ub.bonus_id, ub.granted_amount, ub.created_at,
                   ub.status, ub.used, u.username
            FROM UserBonuses ub
            JOIN Users u ON ub.user_id = u.id
            WHERE ub.id = ?
        ");
        $ubStmt->bind_param("i", $ubId);
        $ubStmt->execute();
        $ubRow = $ubStmt->get_result()->fetch_assoc();
        $ubStmt->close();

        if (!$ubRow) {
            echo json_encode(['success' => false, 'message' => 'Free bet nem található.']);
            exit;
        }

        // Batch detektálás: azonos created_at + bonus_id + granted_amount = csoportos küldés volt
        $batchStmt = $conn->prepare("
            SELECT ub.id, ub.user_id, ub.status, ub.used, u.username
            FROM UserBonuses ub
            JOIN Users u ON ub.user_id = u.id
            WHERE ub.bonus_id = ?
              AND ub.granted_amount = ?
              AND ub.created_at = ?
        ");
        $batchStmt->bind_param("ids", $ubRow['bonus_id'], $ubRow['granted_amount'], $ubRow['created_at']);
        $batchStmt->execute();
        $batchResult = $batchStmt->get_result();
        $batchEntries = [];
        while ($r = $batchResult->fetch_assoc()) {
            $batchEntries[] = $r;
        }
        $batchStmt->close();

        $isBatch = count($batchEntries) > 1;

        $conn->begin_transaction();
        try {
            $revokeIds = $isBatch
                ? array_column($batchEntries, 'id')
                : [(int)$ubRow['id']];

            $placeholders = implode(',', array_fill(0, count($revokeIds), '?'));
            $types = str_repeat('i', count($revokeIds));

            // Státusz → EXPIRED, free_bet_amount → 0
            $revStmt = $conn->prepare("
                UPDATE UserBonuses
                SET status = 'EXPIRED', free_bet_amount = 0, used = 1, used_at = NOW()
                WHERE id IN ($placeholders) AND status = 'ACTIVE' AND used = 0
            ");
            $revStmt->bind_param($types, ...$revokeIds);
            $revStmt->execute();
            $affected = $revStmt->affected_rows;
            $revStmt->close();

            // Felhasználói tevékenységnapló
            $revokeDesc = 'Free Bet visszavonva: ' . number_format((float)$ubRow['granted_amount'], 0, ',', '.') . ' Ft.';
            if ($isBatch) {
                foreach ($batchEntries as $be) {
                    log_activity((int)$be['user_id'], 'bonus', $revokeDesc);
                    createUserNotification(
                        $conn,
                        (int)$be['user_id'],
                        'Free Bet visszavonva',
                        'Az admin visszavonta a Free Bet bónuszodat. Összeg: ' . number_format((float)$ubRow['granted_amount'], 0, ',', '.') . ' Ft.',
                        'BONUS'
                    );
                }
            } else {
                log_activity((int)$ubRow['user_id'], 'bonus', $revokeDesc);
                createUserNotification(
                    $conn,
                    (int)$ubRow['user_id'],
                    'Free Bet visszavonva',
                    'Az admin visszavonta a Free Bet bónuszodat. Összeg: ' . number_format((float)$ubRow['granted_amount'], 0, ',', '.') . ' Ft.',
                    'BONUS'
                );
            }

            $conn->commit();

            if ($isBatch) {
                $msg = $affected . ' db Free Bet visszavonva (csoportos küldésből ' . count($revokeIds) . ' fő).';
                log_audit('admin_freebet_revoke_batch', 'system', null, $msg);
            } else {
                $msg = 'Free Bet visszavonva: ' . $ubRow['username'] . ' (#' . $ubRow['user_id'] . ')';
                log_audit('admin_freebet_revoke', 'user', $ubRow['user_id'], $msg);
            }

            echo json_encode(['success' => true, 'message' => $msg]);
        } catch (\Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Adatbázis hiba: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ismeretlen action: ' . $action]);
}
