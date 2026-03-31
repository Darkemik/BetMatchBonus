<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
  exit;
}

$email = trim($_POST['email'] ?? '');
$birthdate = trim($_POST['birthdate'] ?? '');

if ($email === '' || $birthdate === '') {
  echo json_encode(['success' => false, 'message' => 'E-mail cím és születési dátum megadása kötelező!']);
  exit;
}

// Felhasználó keresése e-mail és születési dátum alapján
$stmt = $conn->prepare("SELECT id, email, username, birthdate FROM Users WHERE email = ? AND DATE(birthdate) = ? LIMIT 1");
$stmt->bind_param("ss", $email, $birthdate);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  echo json_encode(['success' => false, 'message' => 'Az e-mail cím és születési dátum kombinációja nem talált meg.']);
  exit;
}

// Jelenleg csak egy üzenetet adunk vissza
// TODO: PHPMailer vagy hasonló e-mail küldésre, amely tartalmazza a felhasználónevét
echo json_encode([
  'success' => true,
  'message' => 'E-mail sikeresen elküldve! Kérjük ellenőrizd a postafiókod. Az e-mailben meg fogod találni a felhasználónevedet.'
]);
?>
