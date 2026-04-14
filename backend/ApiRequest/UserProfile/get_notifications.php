<?php
/**
 * Felhasználói értesítések API
 * GET:              értesítések listája
 * GET ?count=1:     olvasatlan értesítések száma
 * POST mark_read:   értesítés olvasottnak jelölése
 * POST mark_all:    összes olvasottnak jelölése
 */
session_start();
require_once __DIR__ . '/../../Auth/check_session.php';
require_once __DIR__ . '/../../connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

/* ━━━━━ GET ━━━━━ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // Csak olvasatlan szám
    if (isset($_GET['count'])) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM Notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'unread_count' => (int)$row['cnt']]);
        exit;
    }

    // Értesítések listája (legfrissebb elöl, max 100)
    $stmt = $conn->prepare("
        SELECT id, title, message, type, is_read, created_at
        FROM Notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_read'] = (int)$row['is_read'];
        $notifications[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'notifications' => $notifications]);
    exit;
}

/* ━━━━━ POST ━━━━━ */
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Egy értesítés olvasottnak jelölése
if ($action === 'mark_read') {
    $notifId = (int)($input['id'] ?? 0);
    if ($notifId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Érvénytelen azonosító.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// Összes olvasottnak jelölése
if ($action === 'mark_all') {
    $stmt = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['success' => true, 'marked' => $affected]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
