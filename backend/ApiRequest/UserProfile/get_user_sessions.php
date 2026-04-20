<?php
/**
 * Felhasználó aktív munkameneteinek lekérdezése és kezelése.
 * GET  — Aktív sessionök listázása
 * POST action=revoke&session_id=X — Egy adott session visszavonása
 * POST action=revoke_all — Összes session visszavonása (kijelentkezés mindenhonnan)
 */
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../../connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nincs bejelentkezve.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// --- GET: Sessionök listázása ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare("
        SELECT id, ip_address, location, user_agent, created_at, last_active_at, expires_at, is_active,
               (token = ?) AS is_current
        FROM UserSessions
        WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
        ORDER BY last_active_at DESC
    ");
    // Az aktuális cookie token hash-e, hogy megjelölhessük melyik az aktuális session
    $currentTokenHash = '';
    if (isset($_COOKIE['remember_token'])) {
        $currentTokenHash = hash('sha256', $_COOKIE['remember_token']);
    }
    $stmt->bind_param("si", $currentTokenHash, $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $sessions = [];
    while ($row = $res->fetch_assoc()) {
        $sessions[] = [
            'id'             => (int)$row['id'],
            'ip_address'     => $row['ip_address'],
            'location'       => $row['location'],
            'device'         => parseUserAgent($row['user_agent']),
            'user_agent_raw' => $row['user_agent'],
            'created_at'     => $row['created_at'],
            'last_active_at' => $row['last_active_at'],
            'expires_at'     => $row['expires_at'],
            'is_current'     => (bool)$row['is_current'],
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'sessions' => $sessions]);
    exit;
}

// --- POST: Session visszavonás ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'revoke') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Érvénytelen session ID.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE UserSessions SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $sessionId, $userId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Munkamenet visszavonva.']);
        exit;
    }

    if ($action === 'revoke_all') {
        // Az aktuális session kivételével mindent deaktiválunk
        $currentTokenHash = '';
        if (isset($_COOKIE['remember_token'])) {
            $currentTokenHash = hash('sha256', $_COOKIE['remember_token']);
        }
        $stmt = $conn->prepare("UPDATE UserSessions SET is_active = 0 WHERE user_id = ? AND token != ?");
        $stmt->bind_param("is", $userId, $currentTokenHash);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Minden más munkamenet visszavonva.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ismeretlen művelet.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Érvénytelen kérés.']);

// --- Segédfüggvények ---
function parseUserAgent(?string $ua): string {
    if (!$ua) return 'Ismeretlen eszköz';

    $device = 'Ismeretlen eszköz';
    // Böngésző felismerés (sorrend fontos: specifikusabbak előre!)
    $browser = 'Ismeretlen böngésző';

    // Kliens oldali felismerés prefix: [Brave], [Edge], stb.
    if (preg_match('/^\[([A-Za-z ]+)\]/', $ua, $prefixMatch)) {
        $browser = trim($prefixMatch[1]);
    } elseif (preg_match('/Brave/i', $ua)) {
        $browser = 'Brave';
    } elseif (preg_match('/Edg[e\/]([\d.]+)/i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/OPR\/([\d.]+)/i', $ua) || preg_match('/Opera/i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/Vivaldi\/([\d.]+)/i', $ua)) {
        $browser = 'Vivaldi';
    } elseif (preg_match('/YaBrowser\/([\d.]+)/i', $ua)) {
        $browser = 'Yandex';
    } elseif (preg_match('/SamsungBrowser\/([\d.]+)/i', $ua)) {
        $browser = 'Samsung Internet';
    } elseif (preg_match('/UCBrowser\/([\d.]+)/i', $ua)) {
        $browser = 'UC Browser';
    } elseif (preg_match('/Firefox\/([\d.]+)/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari\/([\d.]+)/i', $ua) && !preg_match('/Chrome/i', $ua)) {
        $browser = 'Safari';
    } elseif (preg_match('/Chrome\/([\d.]+)/i', $ua)) {
        $browser = 'Chrome';
    }

    // OS felismerés
    $os = '';
    if (preg_match('/Windows NT/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Macintosh|Mac OS/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/iPhone|iPad/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    $device = $browser;
    if ($os) $device .= ' – ' . $os;
    return $device;
}
