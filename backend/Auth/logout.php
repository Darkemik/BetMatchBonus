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

// Ha be volt jelentkezve, töröljük a remember_token-t az adatbázisból
if ($userId > 0) {
  $stmt = $conn->prepare("UPDATE Users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->close();
}

// Cookie törlése
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

echo json_encode(['success' => true]);
?>