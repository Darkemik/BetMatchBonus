<?php
/**
 * Admin Balance API (RESTful)
 * GET                     — felhasználók egyenleg listája + BalanceHistory
 * POST                    — manuális egyenleg módosítás (jóváírás/levonás)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('MOD');
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/permission_helper.php';
require_once __DIR__ . '/../Auth/audit_helper.php';

if (!check_page_permission('balances')) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs jogosultság']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET — Lista / történet ───
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'users';

    // Összes felhasználó egyenlegei
    if ($action === 'users') {
        $search = trim($_GET['search'] ?? '');
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $where = '';
        $params = [];
        $types  = '';

        if ($search !== '') {
            $like = "%$search%";
            $where = "WHERE (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.id = ?)";
            $params = [$like, $like, $like, (int)$search];
            $types  = 'sssi';
        }

        // Total count
        $countSql = "SELECT COUNT(*) AS c FROM Users u $where";
        if ($types) {
            $cs = $conn->prepare($countSql);
            $cs->bind_param($types, ...$params);
            $cs->execute();
            $total = (int)$cs->get_result()->fetch_assoc()['c'];
            $cs->close();
        } else {
            $total = (int)$conn->query($countSql)->fetch_assoc()['c'];
        }

        $sql = "SELECT u.id, u.username, u.email, u.full_name, u.balance, u.winnings_balance, u.bonus_balance,
                       u.is_active, u.is_verified,
                       w.balance AS wallet_balance, w.locked_amount,
                       (SELECT COUNT(*) FROM BalanceHistory bh WHERE bh.user_id = u.id) AS history_count
                FROM Users u
                LEFT JOIN Wallets w ON w.user_id = u.id
                $where
                ORDER BY u.balance DESC, u.id DESC
                LIMIT $limit OFFSET $offset";

        if ($types) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id'               => (int)$row['id'],
                'username'         => $row['username'],
                'email'            => $row['email'],
                'full_name'        => $row['full_name'],
                'balance'          => (float)$row['balance'],
                'winnings_balance' => (float)$row['winnings_balance'],
                'bonus_balance'    => (float)$row['bonus_balance'],
                'wallet_balance'   => (float)($row['wallet_balance'] ?? 0),
                'locked_amount'    => (float)($row['locked_amount'] ?? 0),
                'is_active'        => (int)$row['is_active'],
                'is_verified'      => (int)$row['is_verified'],
                'history_count'    => (int)$row['history_count']
            ];
        }
        if (isset($stmt)) $stmt->close();

        // Összesítő statisztikák
        $stats = $conn->query("
            SELECT 
                COUNT(*) AS total_users,
                SUM(balance) AS total_balance,
                SUM(winnings_balance) AS total_winnings,
                SUM(bonus_balance) AS total_bonus
            FROM Users WHERE is_active = 1
        ")->fetch_assoc();

        echo json_encode([
            'users' => $users,
            'total' => $total,
            'page'  => $page,
            'pages' => max(1, ceil($total / $limit)),
            'stats' => [
                'total_users'    => (int)$stats['total_users'],
                'total_balance'  => (float)$stats['total_balance'],
                'total_winnings' => (float)$stats['total_winnings'],
                'total_bonus'    => (float)$stats['total_bonus']
            ]
        ]);
        exit;
    }

    // Egy felhasználó egyenleg-története
    if ($action === 'history') {
        $userId = (int)($_GET['user_id'] ?? 0);
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 30;
        $offset = ($page - 1) * $limit;

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Hiányzó user_id']);
            exit;
        }

        $cs = $conn->prepare("SELECT COUNT(*) AS c FROM BalanceHistory WHERE user_id = ?");
        $cs->bind_param("i", $userId);
        $cs->execute();
        $total = (int)$cs->get_result()->fetch_assoc()['c'];
        $cs->close();

        $stmt = $conn->prepare("
            SELECT bh.id, bh.previous_balance, bh.new_balance, bh.change_amount, bh.reason, bh.created_at,
                   t.transaction_id AS tx_ref
            FROM BalanceHistory bh
            LEFT JOIN Transactions t ON t.id = bh.transaction_id
            WHERE bh.user_id = ?
            ORDER BY bh.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id'               => (int)$row['id'],
                'previous_balance' => (float)$row['previous_balance'],
                'new_balance'      => (float)$row['new_balance'],
                'change_amount'    => (float)$row['change_amount'],
                'reason'           => $row['reason'],
                'tx_ref'           => $row['tx_ref'],
                'created_at'       => $row['created_at']
            ];
        }
        $stmt->close();

        echo json_encode([
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => max(1, ceil($total / $limit))
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Ismeretlen action']);
    exit;
}

// ─── POST — Manuális egyenleg módosítás ───
if ($method === 'POST') {
    $input   = json_decode(file_get_contents('php://input'), true);
    $userId  = (int)($input['user_id'] ?? 0);
    $amount  = (float)($input['amount'] ?? 0);
    $type    = trim($input['type'] ?? '');   // 'credit' | 'debit'
    $reason  = trim($input['reason'] ?? '');

    if ($userId <= 0 || $amount <= 0 || !in_array($type, ['credit', 'debit']) || $reason === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Kötelező mezők: user_id, amount (>0), type (credit/debit), reason']);
        exit;
    }

    // Felhasználó lekérése
    $stmt = $conn->prepare("SELECT id, username, balance FROM Users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'Felhasználó nem található']);
        exit;
    }

    $previousBalance = (float)$user['balance'];
    $changeAmount    = ($type === 'credit') ? $amount : -$amount;
    $newBalance      = $previousBalance + $changeAmount;

    if ($newBalance < 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Az egyenleg nem mehet 0 alá. Jelenlegi: ' . number_format($previousBalance, 0, ',', ' ') . ' Ft']);
        exit;
    }

    $conn->begin_transaction();
    try {
        // Users.balance frissítés
        $stmt = $conn->prepare("UPDATE Users SET balance = ? WHERE id = ?");
        $stmt->bind_param("di", $newBalance, $userId);
        $stmt->execute();
        $stmt->close();

        // Wallets.balance frissítés
        $stmt = $conn->prepare("UPDATE Wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->bind_param("di", $changeAmount, $userId);
        $stmt->execute();
        $stmt->close();

        // BalanceHistory bejegyzés
        $reasonFull = ($type === 'credit' ? 'Admin jóváírás' : 'Admin levonás') . ': ' . $reason;
        $stmt = $conn->prepare("
            INSERT INTO BalanceHistory (user_id, previous_balance, new_balance, change_amount, reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iddds", $userId, $previousBalance, $newBalance, $changeAmount, $reasonFull);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['error' => 'Hiba: ' . $e->getMessage()]);
        exit;
    }

    $label = $type === 'credit' ? 'jóváírás' : 'levonás';
    log_audit('balance_adjust', 'user', $userId,
        "Egyenleg $label: {$user['username']} — " . number_format($amount, 0, ',', ' ') . " Ft ($reason). "
        . number_format($previousBalance, 0, ',', ' ') . " → " . number_format($newBalance, 0, ',', ' ') . " Ft");

    http_response_code(201);
    echo json_encode([
        'success'          => true,
        'previous_balance' => $previousBalance,
        'new_balance'      => $newBalance,
        'change_amount'    => $changeAmount,
        'message'          => ucfirst($label) . ' sikeres: ' . number_format($amount, 0, ',', ' ') . ' Ft'
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed. Használható: GET, POST']);
