<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';

// Először ellenőrizze a session-t
if (isset($_SESSION['user_id'])) {
  $userId = (int)$_SESSION['user_id'];
  
  $stmt = $conn->prepare("SELECT id, username, email, full_name, balance FROM Users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
  
  if ($user) {
    echo json_encode(['loggedIn' => true, 'user' => $user]);
    exit;
  }
}

// Ha nincs session, csak akkor próbálja meg a cookie-t használni ha van
if (isset($_COOKIE['remember_token'])) {
  $rememberToken = $_COOKIE['remember_token'];
  $tokenHash = hash('sha256', $rememberToken);
  
  $stmt = $conn->prepare("SELECT id, username, email, full_name, balance, remember_expiry FROM Users 
                          WHERE remember_token = ? AND remember_expiry > NOW() LIMIT 1");
  $stmt->bind_param("s", $tokenHash);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
  
  if ($user) {
    // Session alapítása a cookie alapján
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    
    echo json_encode(['loggedIn' => true, 'user' => [
      'id' => $user['id'],
      'username' => $user['username'],
      'email' => $user['email'],
      'full_name' => $user['full_name'],
      'balance' => $user['balance']
    ]]);
    exit;
  } else {
    // Érvénytelen cookie, törlés
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
  }
}

echo json_encode(['loggedIn' => false]);
?>