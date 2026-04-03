<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';

function getAvailableFreeBet(mysqli $conn, int $userId): array {
  $stmt = $conn->prepare(" 
    SELECT ub.id, COALESCE(ub.free_bet_amount, 0) AS free_bet_amount
    FROM UserBonuses ub
    INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
    WHERE ub.user_id = ?
      AND ub.status = 'ACTIVE'
      AND ub.used = 0
      AND UPPER(COALESCE(bc.bet_reward_type, '')) = 'FREE_BET'
      AND COALESCE(ub.free_bet_amount, 0) > 0
      AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
    ORDER BY ub.free_bet_amount DESC, ub.id ASC
    LIMIT 1
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  return [
    'id' => (int)($row['id'] ?? 0),
    'amount' => (float)($row['free_bet_amount'] ?? 0.0)
  ];
}

// Először ellenőrizze a session-t
if (isset($_SESSION['user_id'])) {
  $userId = (int)$_SESSION['user_id'];
  if (!isset($_SESSION['session_bet_total'])) {
    $_SESSION['session_bet_total'] = 0.0;
  }
  if (!isset($_SESSION['login_started_at'])) {
    $_SESSION['login_started_at'] = time();
  }
  
  $stmt = $conn->prepare("SELECT id, username, email, full_name, balance FROM Users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
  
  if ($user) {
    $freeBet = getAvailableFreeBet($conn, $userId);
    $user['session_bet_total'] = (float)($_SESSION['session_bet_total'] ?? 0.0);
    $user['login_started_at'] = (int)($_SESSION['login_started_at'] ?? time());
    $user['available_free_bet_id'] = $freeBet['id'];
    $user['available_free_bet_amount'] = $freeBet['amount'];
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
    if (!isset($_SESSION['session_bet_total'])) {
      $_SESSION['session_bet_total'] = 0.0;
    }
    if (!isset($_SESSION['login_started_at'])) {
      $_SESSION['login_started_at'] = time();
    }
    
    $freeBet = getAvailableFreeBet($conn, (int)$user['id']);
    echo json_encode(['loggedIn' => true, 'user' => [
      'id' => $user['id'],
      'username' => $user['username'],
      'email' => $user['email'],
      'full_name' => $user['full_name'],
      'balance' => $user['balance'],
      'session_bet_total' => (float)($_SESSION['session_bet_total'] ?? 0.0),
      'login_started_at' => (int)($_SESSION['login_started_at'] ?? time()),
      'available_free_bet_id' => $freeBet['id'],
      'available_free_bet_amount' => $freeBet['amount']
    ]]);
    exit;
  } else {
    // Érvénytelen cookie, törlés
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
  }
}

echo json_encode(['loggedIn' => false]);
?>