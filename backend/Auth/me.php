<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';

function getAvailableFreeBet(mysqli $conn, int $userId): array {
  $stmt = $conn->prepare(" 
    SELECT ub.id,
           COALESCE(ub.free_bet_amount, 0) AS free_bet_amount,
           COALESCE(bc.min_combo, 0) AS min_combo,
           COALESCE(bc.min_odds, 0) AS min_odds,
           COALESCE(bc.min_odds_per_event, 0) AS min_odds_per_event
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
    'amount' => (float)($row['free_bet_amount'] ?? 0.0),
    'min_combo' => (int)($row['min_combo'] ?? 0),
    'min_odds' => (float)($row['min_odds'] ?? 0.0),
    'min_odds_per_event' => (float)($row['min_odds_per_event'] ?? 0.0)
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

  // Inaktivitás frissítése (check_session.php 30 perces timeout-ot néz ezen)
  $_SESSION['last_activity'] = time();

  // 1 órás session limit ellenőrzés
  $sessionMaxSeconds = 3600; // 1 óra
  $elapsed = time() - (int)$_SESSION['login_started_at'];
  if ($elapsed >= $sessionMaxSeconds) {
    session_unset();
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    echo json_encode(['loggedIn' => false, 'expired' => true]);
    exit;
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
    $user['session_remaining'] = $sessionMaxSeconds - $elapsed;
    $user['available_free_bet_id'] = $freeBet['id'];
    $user['available_free_bet_amount'] = $freeBet['amount'];
    $user['available_free_bet_min_combo'] = $freeBet['min_combo'];
    $user['available_free_bet_min_odds'] = $freeBet['min_odds'];
    $user['available_free_bet_min_odds_per_event'] = $freeBet['min_odds_per_event'];
    echo json_encode(['loggedIn' => true, 'user' => $user]);
    exit;
  }
}

// Ha nincs session, csak akkor próbálja meg a cookie-t használni ha van
if (isset($_COOKIE['remember_token'])) {
  $rememberToken = $_COOKIE['remember_token'];
  $tokenHash = hash('sha256', $rememberToken);
  
  $stmt = $conn->prepare("SELECT id, username, email, full_name, balance, remember_expiry FROM Users 
                          WHERE remember_token = ? AND remember_expiry > NOW() AND is_active = 1 LIMIT 1");
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
    $sessionRemaining = 3600 - (time() - (int)$_SESSION['login_started_at']);
    echo json_encode(['loggedIn' => true, 'user' => [
      'id' => $user['id'],
      'username' => $user['username'],
      'email' => $user['email'],
      'full_name' => $user['full_name'],
      'balance' => $user['balance'],
      'session_bet_total' => (float)($_SESSION['session_bet_total'] ?? 0.0),
      'login_started_at' => (int)($_SESSION['login_started_at'] ?? time()),
      'session_remaining' => $sessionRemaining,
      'available_free_bet_id' => $freeBet['id'],
      'available_free_bet_amount' => $freeBet['amount'],
      'available_free_bet_min_combo' => $freeBet['min_combo'],
      'available_free_bet_min_odds' => $freeBet['min_odds'],
      'available_free_bet_min_odds_per_event' => $freeBet['min_odds_per_event']
    ]]);
    exit;
  } else {
    // Érvénytelen cookie, törlés
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
  }
}

echo json_encode(['loggedIn' => false]);
?>