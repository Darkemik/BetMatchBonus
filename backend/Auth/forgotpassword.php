<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

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

// Token mentése az adatbázisba
$stmt = $conn->prepare("UPDATE Users SET reset_token = ?, reset_token_expiry = FROM_UNIXTIME(?) WHERE id = ?");
$stmt->bind_param("sii", $tokenHash, $expiry, $user['id']);
$stmt->execute();
$stmt->close();

// E-mail küldése (később implementálható PHPMailer-rel)
$resetLink = "http://localhost/BetMatchBonus/frontend/Auth/resetpassword.php?token=" . urlencode($resetToken);

// TODO: PHPMailer vagy hasonló e-mail küldésre
// mail($user['email'], 'Jelszó helyreállítás', "Kattints ide a jelszó helyreállításához: " . $resetLink);

// Jelenleg csak egy üzenetet adunk vissza
echo json_encode([
  'success' => true,
  'message' => 'E-mail sikeresen elküldve! Kérjük ellenőrizd a postafiókod.'
]);
?>
