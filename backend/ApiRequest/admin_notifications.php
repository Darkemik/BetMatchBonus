<?php
/**
 * Admin Notifications API
 * POST action=send        — tömeges értesítés küldése
 * POST action=delete      — értesítés törlése
 * GET  action=list        — küldött értesítések listája
 * GET  action=stats       — statisztikák
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Auth/admin_guard.php';
admin_guard('MOD');
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/permission_helper.php';
require_once __DIR__ . '/../Auth/audit_helper.php';

if (!check_page_permission('notifications')) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs jogosultság']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET ───
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'stats') {
        $total = $conn->query("SELECT COUNT(*) AS c FROM Notifications WHERE type = 'ANNOUNCEMENT'")->fetch_assoc()['c'];
        $users = $conn->query("SELECT COUNT(*) AS c FROM Users WHERE is_active = 1")->fetch_assoc()['c'];

        // Distinct broadcast messages (grouped by title + created_at within 5s)
        $broadcasts = $conn->query("
            SELECT COUNT(DISTINCT title) AS c FROM (
                SELECT title, MIN(created_at) AS first_sent
                FROM Notifications WHERE type = 'ANNOUNCEMENT'
                GROUP BY title, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
            ) sub
        ")->fetch_assoc()['c'];

        $unread = $conn->query("SELECT COUNT(*) AS c FROM Notifications WHERE type='ANNOUNCEMENT' AND is_read=0")->fetch_assoc()['c'];

        echo json_encode([
            'total_sent' => (int)$total,
            'active_users' => (int)$users,
            'broadcasts' => (int)$broadcasts,
            'unread' => (int)$unread
        ]);
        exit;
    }

    // List: sent broadcasts grouped
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    // Distinct announcements grouped by title + minute
    $countQ = $conn->query("
        SELECT COUNT(*) AS c FROM (
            SELECT title, MIN(created_at) AS ts
            FROM Notifications WHERE type = 'ANNOUNCEMENT'
            GROUP BY title, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
        ) sub
    ");
    $totalCount = (int)$countQ->fetch_assoc()['c'];

    $q = $conn->query("
        SELECT title, message,
               MIN(created_at) AS sent_at,
               COUNT(*) AS recipient_count,
               SUM(is_read) AS read_count
        FROM Notifications
        WHERE type = 'ANNOUNCEMENT'
        GROUP BY title, message, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
        ORDER BY sent_at DESC
        LIMIT $limit OFFSET $offset
    ");

    $items = [];
    while ($row = $q->fetch_assoc()) {
        $items[] = [
            'title' => $row['title'],
            'message' => $row['message'],
            'sent_at' => $row['sent_at'],
            'recipient_count' => (int)$row['recipient_count'],
            'read_count' => (int)$row['read_count']
        ];
    }

    echo json_encode([
        'items' => $items,
        'total' => $totalCount,
        'page' => $page,
        'pages' => max(1, ceil($totalCount / $limit))
    ]);
    exit;
}

// ─── POST ───
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// ─── Tömeges küldés ───
if ($action === 'send') {
    $title   = trim($input['title'] ?? '');
    $message = trim($input['message'] ?? '');
    $target  = $input['target'] ?? 'all'; // 'all' | 'active' | 'verified'

    if ($title === '' || $message === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Cím és üzenet megadása kötelező']);
        exit;
    }

    if (mb_strlen($title) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'A cím maximum 100 karakter lehet']);
        exit;
    }

    // Target users
    $where = '';
    switch ($target) {
        case 'active':   $where = ' WHERE is_active = 1'; break;
        case 'verified': $where = ' WHERE is_active = 1 AND is_verified = 1 AND data_verified = 1'; break;
        default:         $where = ''; break; // all
    }

    $users = $conn->query("SELECT id FROM Users" . $where);
    $count = 0;

    $stmt = $conn->prepare("
        INSERT INTO Notifications (user_id, title, message, type, related_type) 
        VALUES (?, ?, ?, 'ANNOUNCEMENT', 'admin')
    ");

    while ($row = $users->fetch_assoc()) {
        $uid = $row['id'];
        $stmt->bind_param("iss", $uid, $title, $message);
        $stmt->execute();
        $count++;
    }
    $stmt->close();

    $targetLabels = ['all' => 'összes', 'active' => 'aktív', 'verified' => 'hitelesített'];
    log_audit('notification_send', 'notification', null,
        "Tömeges értesítés: \"$title\" — $count címzett (" . ($targetLabels[$target] ?? $target) . ")");

    echo json_encode(['success' => true, 'sent' => $count, 'message' => "$count felhasználónak elküldve"]);
    exit;
}

// ─── Törlés ───
if ($action === 'delete') {
    $title  = trim($input['title'] ?? '');
    $sentAt = trim($input['sent_at'] ?? '');

    if ($title === '' || $sentAt === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Hiányzó paraméterek']);
        exit;
    }

    $stmt = $conn->prepare("
        DELETE FROM Notifications 
        WHERE type = 'ANNOUNCEMENT' AND title = ? 
        AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MINUTE)
    ");
    $stmt->bind_param("sss", $title, $sentAt, $sentAt);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();

    log_audit('notification_delete', 'notification', null,
        "Értesítés törölve: \"$title\" — $deleted db");

    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Ismeretlen művelet']);
