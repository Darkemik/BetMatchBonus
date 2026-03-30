<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/..//connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
  exit;
}

$email = trim($_POST['email'] ?? '');
$username = trim($_POST['username'] ?? '');

if ($email === '' || $username === '') {
  echo json_encode(['success' => false, 'message' => 'E-mail cím és felhasználónév megadása kötelező!']);
  exit;
}

// Felhasználó keresése e-mail és felhasználónév alapján
$stmt = $conn->prepare("SELECT id, email, username FROM Users WHERE email = ? AND username = ? LIMIT 1");
$stmt->bind_param("ss", $email, $username);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'Az e-mail cím és felhasználónév kombinációja nem talált meg.']);
  exit;
}

// Jelszó reset token generálása
$resetToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $resetToken);
$expiry = time() + (60 * 60); // 1 óra érvényesség

// Token mentese az adatbazisba
$stmt = $conn->prepare("UPDATE Users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
$stmt->bind_param("si", $token, $user['id']);
$stmt->execute();
$stmt->close();

// TODO: Éles környezetben PHPMailer-rel email küldés
// mail($user['email'], 'Jelszó visszaállítás', "Token: $token");

echo json_encode([
    'success'     => true,
    'message'     => 'Demo mód – jelszó-visszaállító token generálva. Éles környezetben email-ben érkezne.',
    'reset_token' => $token  // CSAK demo/teszt célra! Élesben töröld!
]);
?>
