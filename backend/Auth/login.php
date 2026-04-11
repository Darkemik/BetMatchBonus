<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../recaptcha_verify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
  exit;
}

// reCAPTCHA v3 ellenőrzés
$recaptchaToken = $_POST['recaptcha_token'] ?? '';
$recaptchaResult = verifyRecaptcha($recaptchaToken, 'login');
if (!$recaptchaResult['success']) {
  echo json_encode(['success' => false, 'message' => $recaptchaResult['error']]);
  exit;
}

$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = isset($_POST['rememberMe']) && $_POST['rememberMe'] === '1';

if ($login === '' || $password === '') {
  echo json_encode(['success' => false, 'message' => 'Minden mező kitöltése kötelező!']);
  exit;
}

$stmt = $conn->prepare("SELECT id, username, email, password_hash, full_name, birth_date, is_active,
                               failed_login_attempts, login_locked_until
                        FROM Users
                        WHERE username = ? OR email = ?
                        LIMIT 1");
$stmt->bind_param("ss", $login, $login);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

// Fiók zárolás ellenőrzése
if ($user && $user['login_locked_until'] !== null) {
  $lockedUntil = strtotime($user['login_locked_until']);
  if ($lockedUntil > time()) {
    $remaining = ceil(($lockedUntil - time()) / 60);
    echo json_encode(['success' => false, 'message' => "A fiókod ideiglenesen zárolva van. Próbáld újra {$remaining} perc múlva."]);
    exit;
  } else {
    // Zárolás lejárt — reset
    $stmtReset = $conn->prepare("UPDATE Users SET failed_login_attempts = 0, login_locked_until = NULL WHERE id = ?");
    $stmtReset->bind_param("i", $user['id']);
    $stmtReset->execute();
    $stmtReset->close();
    $user['failed_login_attempts'] = 0;
  }
}

$maxAttempts = 3;

if (!$user || !password_verify($password, $user['password_hash'])) {
  // Sikertelen bejelentkezés — számláló növelése
  if ($user) {
    $newAttempts = (int)$user['failed_login_attempts'] + 1;
    if ($newAttempts >= $maxAttempts) {
      // Zárolás 1 órára
      $lockUntil = date('Y-m-d H:i:s', time() + 3600);
      $stmtLock = $conn->prepare("UPDATE Users SET failed_login_attempts = ?, login_locked_until = ? WHERE id = ?");
      $stmtLock->bind_param("isi", $newAttempts, $lockUntil, $user['id']);
      $stmtLock->execute();
      $stmtLock->close();
      echo json_encode(['success' => false, 'message' => 'Túl sok sikertelen próbálkozás! A fiókod 1 órára zárolva lett.']);
      exit;
    } else {
      $stmtFail = $conn->prepare("UPDATE Users SET failed_login_attempts = ? WHERE id = ?");
      $stmtFail->bind_param("ii", $newAttempts, $user['id']);
      $stmtFail->execute();
      $stmtFail->close();
      $left = $maxAttempts - $newAttempts;
      echo json_encode(['success' => false, 'message' => "Hibás felhasználónév/email vagy jelszó. Még {$left} próbálkozásod van."]);
      exit;
    }
  }
  echo json_encode(['success' => false, 'message' => 'Hibás felhasználónév/email vagy jelszó.']);
  exit;
}

// Sikeres bejelentkezés — reset attempts
if ((int)$user['failed_login_attempts'] > 0) {
  $stmtReset = $conn->prepare("UPDATE Users SET failed_login_attempts = 0, login_locked_until = NULL WHERE id = ?");
  $stmtReset->bind_param("i", $user['id']);
  $stmtReset->execute();
  $stmtReset->close();
}

if ((int)$user['is_active'] !== 1) {
  echo json_encode(['success' => false, 'message' => 'A regisztrációd még jóváhagyásra vár. Kérjük, várd meg, amíg az adminisztrátorok ellenőrzik az adataidat!']);
  exit;
}

$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['last_activity'] = time();
$_SESSION['session_bet_total'] = 0.0;
$_SESSION['login_started_at'] = time();

// Wallet inicializáció - ha nincs wallet, akkor 0 Ft-tal létrehozzuk
$stmtCheckWallet = $conn->prepare("SELECT id FROM Wallets WHERE user_id = ?");
$stmtCheckWallet->bind_param("i", $user['id']);
$stmtCheckWallet->execute();
$walletResult = $stmtCheckWallet->get_result();

if ($walletResult->num_rows === 0) {
  // Nincs wallet - létrehozunk 0 Ft-tal
  $initialBalance = 0;
    $stmtCreateWallet = $conn->prepare("INSERT INTO Wallets (user_id, balance, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
    $stmtCreateWallet->bind_param("id", $user['id'], $initialBalance);
    $stmtCreateWallet->execute();
    $stmtCreateWallet->close();
}
$stmtCheckWallet->close();

// Cookie beállítás CSAK ha "Remember Me" be van jelölve
if ($rememberMe) {
  $rememberToken = bin2hex(random_bytes(32));
  $tokenHash = hash('sha256', $rememberToken);
  $tokenExpiry = time() + (10 * 60 * 60); // 10 óra — DB token lejárat
  $cookieExpiry = time() + (10 * 365 * 24 * 60 * 60); // ~10 év — cookie "örökre"
  
  // Token mentése az adatbázisba (10 óra érvényesség)
  $stmt = $conn->prepare("UPDATE Users SET remember_token = ?, remember_expiry = FROM_UNIXTIME(?) WHERE id = ?");
  $stmt->bind_param("sii", $tokenHash, $tokenExpiry, $user['id']);
  $stmt->execute();
  $stmt->close();
  
  // Cookie beállítása (örökre megmarad)
  setcookie('remember_token', $rememberToken, $cookieExpiry, '/', '', false, true);
} else {
  // Ha nincs bejelölve, töröljük a régi remember_token-t az adatbázisból és a cookie-t
  $stmt = $conn->prepare("UPDATE Users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
  $stmt->bind_param("i", $user['id']);
  $stmt->execute();
  $stmt->close();
  
  // Cookie törlése
  setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

echo json_encode(['success' => true, 'message' => 'Sikeres bejelentkezés!']);
?>