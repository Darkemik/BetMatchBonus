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

// Wallet inicializáció - ha nincs wallet, akkor 50k-val létrehozzuk
$stmtCheckWallet = $conn->prepare("SELECT id FROM Wallets WHERE user_id = ?");
$stmtCheckWallet->bind_param("i", $user['id']);
$stmtCheckWallet->execute();
$walletResult = $stmtCheckWallet->get_result();

if ($walletResult->num_rows === 0) {
    // Nincs wallet - létrehozunk 50k-val
    $initialBalance = 50000;
    $stmtCreateWallet = $conn->prepare("INSERT INTO Wallets (user_id, balance, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    $stmtCreateWallet->bind_param("id", $user['id'], $initialBalance);
    $stmtCreateWallet->execute();
    $stmtCreateWallet->close();
}
$stmtCheckWallet->close();

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