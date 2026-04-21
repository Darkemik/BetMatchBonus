<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../recaptcha_verify.php';
require_once __DIR__ . '/../Auth/settings_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);
  exit;
}

function extractClientIp(): ?string {
  $candidates = [];

  if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $candidates[] = trim((string)$_SERVER['HTTP_CF_CONNECTING_IP']);
  }

  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
    foreach ($parts as $part) {
      $candidates[] = trim($part);
    }
  }

  if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $candidates[] = trim((string)$_SERVER['HTTP_X_REAL_IP']);
  }

  if (!empty($_SERVER['REMOTE_ADDR'])) {
    $candidates[] = trim((string)$_SERVER['REMOTE_ADDR']);
  }

  foreach ($candidates as $ip) {
    if ($ip === '') {
      continue;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
      return $ip;
    }
  }

  return null;
}

function isLocalOrPrivateIp(?string $ip): bool {
  if ($ip === null || $ip === '' || strtolower($ip) === 'localhost' || $ip === '::1' || $ip === '127.0.0.1') {
    return true;
  }

  if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    return true;
  }

  return false;
}

function resolveLocationFromUserData(mysqli $conn, array $user): ?string {
  if ((int)($user['data_verified'] ?? 0) !== 1) {
    return null;
  }

  $city = trim((string)($user['city'] ?? ''));
  $postalCode = trim((string)($user['postal_code'] ?? ''));
  $country = trim((string)($user['country'] ?? ''));

  if ($city === '' && $postalCode !== '') {
    $stmtPostal = $conn->prepare("SELECT city FROM PostalCodes WHERE postal_code = ? LIMIT 1");
    if ($stmtPostal) {
      $stmtPostal->bind_param("s", $postalCode);
      $stmtPostal->execute();
      $resPostal = $stmtPostal->get_result();
      $rowPostal = $resPostal ? $resPostal->fetch_assoc() : null;
      $stmtPostal->close();
      if ($rowPostal && !empty($rowPostal['city'])) {
        $city = trim((string)$rowPostal['city']);
      }
    }
  }

  if ($city === '') {
    return null;
  }

  $countryPart = $country !== '' ? $country : 'HU';
  return mb_substr(trim($city . ', ' . $countryPart, ', '), 0, 120);
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

$stmt = $conn->prepare("SELECT id, username, email, password_hash, full_name, birth_date, is_active, is_verified,
                               data_verified,
                               city, postal_code, country,
                               reset_token, reset_token_expiry,
                               force_logout_at,
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

$maxAttempts = get_setting_int('max_login_attempts', 3);

// Ha admin reset indult (token aktív + force_logout_at beállítva), a régi jelszóval nem lehet belépni.
if ($user && !empty($user['reset_token']) && !empty($user['reset_token_expiry']) && strtotime((string)$user['reset_token_expiry']) > time() && !empty($user['force_logout_at'])) {
  echo json_encode(['success' => false, 'message' => 'Az admin jelszó-helyreállítást kért ehhez a fiókhoz. Kérjük, nézd meg az emailed és állíts be új jelszót!']);
  exit;
}

if (!$user || !password_verify($password, $user['password_hash'])) {
  // Sikertelen bejelentkezés — számláló növelése
  if ($user) {
    $newAttempts = (int)$user['failed_login_attempts'] + 1;
    if ($newAttempts >= $maxAttempts) {
      // Zárolás
      $lockoutMinutes = get_setting_int('login_lockout_minutes', 60);
      $lockUntil = date('Y-m-d H:i:s', time() + $lockoutMinutes * 60);
      $stmtLock = $conn->prepare("UPDATE Users SET failed_login_attempts = ?, login_locked_until = ? WHERE id = ?");
      $stmtLock->bind_param("isi", $newAttempts, $lockUntil, $user['id']);
      $stmtLock->execute();
      $stmtLock->close();
      echo json_encode(['success' => false, 'message' => 'Túl sok sikertelen próbálkozás! A fiókod ' . $lockoutMinutes . ' percre zárolva lett.']);
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
  if ((int)$user['is_verified'] === 1) {
    // Korábban jóváhagyott, de admin letiltotta
    echo json_encode(['success' => false, 'message' => 'A fiókod felfüggesztésre került. Ha kérdésed van, kérjük vedd fel velünk a kapcsolatot!']);
  } else {
    // Még nem hagyta jóvá az admin
    echo json_encode(['success' => false, 'message' => 'A regisztrációd még jóváhagyásra vár. Kérjük, várd meg, amíg az adminisztrátorok ellenőrzik az adataidat!']);
  }
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
  
  $ip = extractClientIp();
  $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
  $clientLocation = trim((string)($_POST['client_location'] ?? ''));
  if ($clientLocation !== '') {
    $clientLocation = mb_substr($clientLocation, 0, 120);
  }
  $clientBrowser = trim($_POST['client_browser'] ?? '');

  // Ha a kliens böngésző nevet küldött, beleírjuk a user-agent elejére jelzésként
  if ($clientBrowser !== '' && $clientBrowser !== 'Unknown') {
    $ua = '[' . mb_substr($clientBrowser, 0, 30) . '] ' . ($ua ?? '');
    $ua = mb_substr($ua, 0, 255);
  }

  // Session limit ellenőrzés (0 = korlátlan)
  $maxSessions = get_setting_int('max_concurrent_sessions', 5);
  if ($maxSessions > 0) {
    // Lejárt sessionök takarítása
    $conn->query("UPDATE UserSessions SET is_active = 0 WHERE user_id = " . (int)$user['id'] . " AND is_active = 1 AND expires_at <= NOW()");

    $stmtCnt = $conn->prepare("SELECT COUNT(*) AS cnt FROM UserSessions WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()");
    $stmtCnt->bind_param("i", $user['id']);
    $stmtCnt->execute();
    $activeCnt = (int)$stmtCnt->get_result()->fetch_assoc()['cnt'];
    $stmtCnt->close();

    // Ha a limit elérve, a legrégebbi session(öke)t deaktiváljuk
    $toRemove = $activeCnt - ($maxSessions - 1); // -1 mert az új session-t is hozzáadjuk
    if ($toRemove > 0) {
      $stmtOld = $conn->prepare("SELECT id FROM UserSessions WHERE user_id = ? AND is_active = 1 AND expires_at > NOW() ORDER BY last_active_at ASC, created_at ASC LIMIT ?");
      $stmtOld->bind_param("ii", $user['id'], $toRemove);
      $stmtOld->execute();
      $resOld = $stmtOld->get_result();
      $idsToDeactivate = [];
      while ($r = $resOld->fetch_assoc()) {
        $idsToDeactivate[] = (int)$r['id'];
      }
      $stmtOld->close();
      if (!empty($idsToDeactivate)) {
        $idList = implode(',', $idsToDeactivate);
        $conn->query("UPDATE UserSessions SET is_active = 0 WHERE id IN ($idList)");
      }
    }
  }

  // Token mentése a UserSessions táblába (multi-device)
  // Helyszín meghatározása IP alapján
  $location = null;
  $profileLocation = resolveLocationFromUserData($conn, $user);
  $isLocal = isLocalOrPrivateIp($ip);
  if ($isLocal) {
    if ($clientLocation !== '') {
      $location = $clientLocation;
    } elseif ($profileLocation !== null) {
      $location = $profileLocation;
    } else {
      $location = 'Helyi gép (localhost)';
    }
  } else {
    $geoCtx = stream_context_create(['http' => ['timeout' => 2]]);
    $geoJson = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,city,country,countryCode', false, $geoCtx);
    if ($geoJson) {
      $geo = json_decode($geoJson, true);
      if (isset($geo['status']) && $geo['status'] === 'success') {
        $city = $geo['city'] ?? '';
        $cc = $geo['countryCode'] ?? '';
        $location = trim($city . ', ' . $cc, ', ');
      }
    }
    if (!$location && $clientLocation !== '') {
      $location = $clientLocation;
    }
    if (!$location && $profileLocation !== null) {
      $location = $profileLocation;
    }
  }

  $stmt = $conn->prepare("INSERT INTO UserSessions (user_id, token, expires_at, is_active, ip_address, location, user_agent, last_active_at)
                          VALUES (?, ?, FROM_UNIXTIME(?), 1, ?, ?, ?, NOW())");
  $stmt->bind_param("isisss", $user['id'], $tokenHash, $tokenExpiry, $ip, $location, $ua);
  $stmt->execute();
  $stmt->close();
  
  // Cookie beállítása (örökre megmarad)
  setcookie('remember_token', $rememberToken, $cookieExpiry, '/', '', false, true);
} else {
  // Ha nincs bejelölve, cookie törlése (régi sessionöket nem bántjuk, más eszközökön maradhatnak)
  setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

require_once __DIR__ . '/audit_helper.php';
log_activity((int)$user['id'], 'login', 'Sikeres bejelentkezés.');

echo json_encode(['success' => true, 'message' => 'Sikeres bejelentkezés!']);
?>