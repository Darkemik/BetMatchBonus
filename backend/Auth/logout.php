<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';

// Először olvassuk ki a user ID-t, MIELŐTT törölnénk a session-t
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId > 0) {
    require_once __DIR__ . '/audit_helper.php';
    log_activity($userId, 'logout', 'Kijelentkezés.');
}
// Session törlése
session_unset();
session_destroy();

// Ha be volt jelentkezve, deaktiváljuk az aktuális session tokent a UserSessions táblában
if ($userId > 0 && isset($_COOKIE['remember_token'])) {
  $tokenHash = hash('sha256', $_COOKIE['remember_token']);
  $stmt = $conn->prepare("UPDATE UserSessions SET is_active = 0 WHERE user_id = ? AND token = ?");
  $stmt->bind_param("is", $userId, $tokenHash);
  $stmt->execute();
  $stmt->close();
}

// Cookie törlése
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

echo json_encode(['success' => true]);
?>