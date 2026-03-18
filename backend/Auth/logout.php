<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

// Session törlése
session_unset();
session_destroy();

// Ha be van jelentkezve, töröljük a remember_token-t az adatbázisból
if (isset($_SESSION['user_id'])) {
  $userId = (int)$_SESSION['user_id'];
  $stmt = $conn->prepare("UPDATE Users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $stmt->close();
}

// Cookie törlése
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

echo json_encode(['success' => true]);
?>