<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../Auth/settings_helper.php';

function getAvailableFreeBets(mysqli $conn, int $userId): array {
  $stmt = $conn->prepare(" 
    SELECT ub.id,
           COALESCE(bc.name, 'Ingyenes fogadás') AS bonus_name,
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
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $list = [];
  while ($row = $res->fetch_assoc()) {
    $list[] = [
      'id' => (int)($row['id'] ?? 0),
      'name' => (string)($row['bonus_name'] ?? 'Ingyenes fogadás'),
      'amount' => (float)($row['free_bet_amount'] ?? 0.0),
      'min_combo' => (int)($row['min_combo'] ?? 0),
      'min_odds' => (float)($row['min_odds'] ?? 0.0),
      'min_odds_per_event' => (float)($row['min_odds_per_event'] ?? 0.0)
    ];
  }
  $stmt->close();

  return $list;
}

function getAvailableFreeBet(mysqli $conn, int $userId): array {
  $list = getAvailableFreeBets($conn, $userId);
  if (!empty($list)) {
    return $list[0];
  }

  return [
    'id' => 0,
    'name' => 'Ingyenes fogadás',
    'amount' => 0.0,
    'min_combo' => 0,
    'min_odds' => 0.0,
    'min_odds_per_event' => 0.0
  ];
}

function getActiveBonusList(mysqli $conn, int $userId): array {
  $stmt = $conn->prepare("
    SELECT ub.id, bc.name AS bonus_name, ub.bonus_balance, ub.granted_amount,
           ub.wagering_progress, ub.wagering_required, ub.expires_at,
           bc.max_win_multiplier, bc.min_combo, bc.min_odds, bc.min_odds_per_event,
           bc.bet_reward_type
    FROM UserBonuses ub
    INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
    WHERE ub.user_id = ?
      AND ub.status = 'ACTIVE'
      AND ub.used = 0
      AND COALESCE(ub.bonus_balance, 0) > 0
      AND UPPER(COALESCE(bc.bet_reward_type, '')) != 'FREE_BET'
      AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
    ORDER BY ub.id ASC
  ");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $list = [];
  while ($row = $res->fetch_assoc()) {
    $list[] = [
      'id' => (int)$row['id'],
      'name' => $row['bonus_name'] ?? 'Bónusz',
      'balance' => (float)$row['bonus_balance'],
      'granted_amount' => (float)$row['granted_amount'],
      'wagering_progress' => (float)$row['wagering_progress'],
      'wagering_required' => (float)$row['wagering_required'],
      'max_win_multiplier' => (float)($row['max_win_multiplier'] ?? 5.0)
    ];
  }
  $stmt->close();
  return $list;
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

  // Teljes munkamenet időkorlát ellenőrzés (alapértelmezetten 60 perc)
  $sessionMaxSeconds = get_setting_int('session_max_duration_minutes', 60) * 60;
  $elapsed = time() - (int)$_SESSION['login_started_at'];
  if ($elapsed >= $sessionMaxSeconds) {
    // Az aktuális remember token deaktiválása a UserSessions-ben
    if (isset($_COOKIE['remember_token'])) {
      $expTokenHash = hash('sha256', $_COOKIE['remember_token']);
      $stmtExp = $conn->prepare("UPDATE UserSessions SET is_active = 0 WHERE token = ?");
      $stmtExp->bind_param("s", $expTokenHash);
      $stmtExp->execute();
      $stmtExp->close();
    }
    session_unset();
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    echo json_encode(['loggedIn' => false, 'expired' => true]);
    exit;
  }
  
  // UserSessions.is_active ellenőrzés (revoke / X gomb)
  if (isset($_COOKIE['remember_token'])) {
    $chkTokenHash = hash('sha256', $_COOKIE['remember_token']);
    $stmtChk = $conn->prepare("SELECT is_active FROM UserSessions WHERE token = ? AND user_id = ? LIMIT 1");
    $stmtChk->bind_param("si", $chkTokenHash, $userId);
    $stmtChk->execute();
    $chkRow = $stmtChk->get_result()->fetch_assoc();
    $stmtChk->close();
    if (!$chkRow || !(int)$chkRow['is_active']) {
      session_unset();
      session_destroy();
      setcookie('remember_token', '', time() - 3600, '/', '', false, true);
      echo json_encode(['loggedIn' => false, 'revoked' => true]);
      exit;
    }
  }

  $stmt = $conn->prepare("SELECT id, username, email, full_name, balance, bonus_balance, force_logout_at FROM Users WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();
  
  if ($user) {
    // Force-logout ellenőrzés (admin kijelentkeztette)
    if (!empty($user['force_logout_at'])) {
      $forceAt = strtotime($user['force_logout_at']);
      $loginAt = (int)($_SESSION['login_started_at'] ?? 0);
      if ($forceAt > $loginAt) {
        session_unset();
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        echo json_encode(['loggedIn' => false, 'forced' => true]);
        exit;
      }
    }

    $freeBets = getAvailableFreeBets($conn, $userId);
    $freeBet = !empty($freeBets) ? $freeBets[0] : ['id' => 0, 'amount' => 0.0, 'min_combo' => 0, 'min_odds' => 0.0, 'min_odds_per_event' => 0.0];
    $activeBonuses = getActiveBonusList($conn, $userId);
    $user['session_bet_total'] = (float)($_SESSION['session_bet_total'] ?? 0.0);
    $user['login_started_at'] = (int)($_SESSION['login_started_at'] ?? time());
    $user['session_remaining'] = $sessionMaxSeconds - $elapsed;
    $user['available_free_bet_id'] = $freeBet['id'];
    $user['available_free_bet_amount'] = $freeBet['amount'];
    $user['available_free_bet_min_combo'] = $freeBet['min_combo'];
    $user['available_free_bet_min_odds'] = $freeBet['min_odds'];
    $user['available_free_bet_min_odds_per_event'] = $freeBet['min_odds_per_event'];
    $user['available_free_bets'] = $freeBets;
    $user['active_bonuses'] = $activeBonuses;
    echo json_encode(['loggedIn' => true, 'user' => $user]);
    exit;
  }
}

// Ha nincs session, csak akkor próbálja meg a cookie-t használni ha van
if (isset($_COOKIE['remember_token'])) {
  $rememberToken = $_COOKIE['remember_token'];
  $tokenHash = hash('sha256', $rememberToken);
  
  // Multi-device: UserSessions táblából keresünk
  $stmt = $conn->prepare("SELECT us.id AS session_id, u.id, u.username, u.email, u.full_name, u.balance, u.bonus_balance
                          FROM UserSessions us
                          INNER JOIN Users u ON u.id = us.user_id
                          WHERE us.token = ? AND us.expires_at > NOW() AND us.is_active = 1 AND u.is_active = 1
                          LIMIT 1");
  $stmt->bind_param("s", $tokenHash);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();
  
  if ($row) {
    // Frissítjük a session utolsó aktivitását
    $stmtUpd = $conn->prepare("UPDATE UserSessions SET last_active_at = NOW() WHERE id = ?");
    $stmtUpd->bind_param("i", $row['session_id']);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Session alapítása a cookie alapján
    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['username'] = $row['username'];
    if (!isset($_SESSION['session_bet_total'])) {
      $_SESSION['session_bet_total'] = 0.0;
    }
    if (!isset($_SESSION['login_started_at'])) {
      $_SESSION['login_started_at'] = time();
    }
    
    $freeBets = getAvailableFreeBets($conn, (int)$row['id']);
    $freeBet = !empty($freeBets) ? $freeBets[0] : ['id' => 0, 'amount' => 0.0, 'min_combo' => 0, 'min_odds' => 0.0, 'min_odds_per_event' => 0.0];
    $activeBonuses = getActiveBonusList($conn, (int)$row['id']);
    $sessionRemaining = 3600 - (time() - (int)$_SESSION['login_started_at']);
    echo json_encode(['loggedIn' => true, 'user' => [
      'id' => $row['id'],
      'username' => $row['username'],
      'email' => $row['email'],
      'full_name' => $row['full_name'],
      'balance' => $row['balance'],
      'bonus_balance' => $row['bonus_balance'] ?? 0,
      'session_bet_total' => (float)($_SESSION['session_bet_total'] ?? 0.0),
      'login_started_at' => (int)($_SESSION['login_started_at'] ?? time()),
      'session_remaining' => $sessionRemaining,
      'available_free_bet_id' => $freeBet['id'],
      'available_free_bet_amount' => $freeBet['amount'],
      'available_free_bet_min_combo' => $freeBet['min_combo'],
      'available_free_bet_min_odds' => $freeBet['min_odds'],
      'available_free_bet_min_odds_per_event' => $freeBet['min_odds_per_event'],
      'available_free_bets' => $freeBets,
      'active_bonuses' => $activeBonuses
    ]]);
    exit;
  } else {
    // Érvénytelen cookie, törlés
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
  }
}

echo json_encode(['loggedIn' => false]);
?>