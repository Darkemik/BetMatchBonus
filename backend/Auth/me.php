<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../ApiRequest/connect.php';

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['loggedIn' => false]);
  exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, username, email, full_name FROM Users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  session_destroy();
  echo json_encode(['loggedIn' => false]);
  exit;
}

echo json_encode(['loggedIn' => true, 'user' => $user]);