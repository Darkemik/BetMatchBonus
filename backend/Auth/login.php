<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
  exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['rememberMe']) && $_POST['rememberMe'] === '1';

if ($login === '' || $password === '') {
  echo json_encode(['success' => false, 'message' => 'Minden mező kitöltése kötelező!']);
  exit;
}

$stmt = $conn->prepare("SELECT id, username, email, password_hash, full_name, birth_date, is_active
                        FROM Users
                        WHERE username = ? OR email = ?
                        LIMIT 1");
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
  echo json_encode(['success' => false, 'message' => 'Hibás felhasználónév/email vagy jelszó.']);
  exit;
}

if ((int)$user['is_active'] !== 1) {
  echo json_encode(['success' => false, 'message' => 'A fiók inaktív.']);
  exit;
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];

// Cookie beállítás, ha "Remember Me" be van jelölve
if ($rememberMe) {
  $rememberToken = bin2hex(random_bytes(32));
  $tokenHash = hash('sha256', $rememberToken);
  $expiry = time() + (30 * 24 * 60 * 60); // 30 nap
  
  // Token mentése az adatbázisba
  $stmt = $conn->prepare("UPDATE Users SET remember_token = ?, remember_expiry = FROM_UNIXTIME(?) WHERE id = ?");
  $stmt->bind_param("sii", $tokenHash, $expiry, $user['id']);
  $stmt->execute();
  $stmt->close();
  
  // Cookie beállítása
  setcookie('remember_token', $rememberToken, $expiry, '/', '', false, true);
}

echo json_encode(['success' => true, 'message' => 'Sikeres bejelentkezés!']);
?>