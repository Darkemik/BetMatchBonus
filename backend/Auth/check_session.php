<?php
/**
 * Session Check - Bejelentkezés ellenőrzés
 */

// Session indítása
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Felhasználó bejelentkezésének ellenőrzése
if (!isset($_SESSION['user_id'])) {
    // Ha nincs bejelentkezve, átirányítás a login oldalra
    header("Location: /BetMatchBonus/frontend/MainMenu/MainMenu.php");
    exit();
}

require_once __DIR__ . '/../Auth/settings_helper.php';
require_once __DIR__ . '/../connect.php';

// UserSessions.is_active ellenőrzés (revoke / X gomb)
if (isset($_COOKIE['remember_token'])) {
    $csTokenHash = hash('sha256', $_COOKIE['remember_token']);
    $stmtSess = $conn->prepare("SELECT is_active FROM UserSessions WHERE token = ? AND user_id = ? LIMIT 1");
    $stmtSess->bind_param("si", $csTokenHash, $_SESSION['user_id']);
    $stmtSess->execute();
    $sessRow = $stmtSess->get_result()->fetch_assoc();
    $stmtSess->close();
    if (!$sessRow || !(int)$sessRow['is_active']) {
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        header("Location: /BetMatchBonus/frontend/MainMenu/MainMenu.php");
        exit();
    }
}

// Force-logout ellenőrzés (admin kikényszerített kijelentkeztetés)
$stmtForce = $conn->prepare("SELECT force_logout_at FROM Users WHERE id = ? LIMIT 1");
$stmtForce->bind_param("i", $_SESSION['user_id']);
$stmtForce->execute();
$forceRow = $stmtForce->get_result()->fetch_assoc();
$stmtForce->close();
if ($forceRow && !empty($forceRow['force_logout_at'])) {
    $forceAt = strtotime($forceRow['force_logout_at']);
    $loginAt = (int)($_SESSION['login_started_at'] ?? 0);
    if ($forceAt > $loginAt) {
        session_destroy();
        header("Location: /BetMatchBonus/frontend/MainMenu/MainMenu.php?forced=1");
        exit();
    }
}

// Session timeout ellenőrzése
$timeout = get_setting_int('session_timeout_minutes', 30) * 60;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $timeout) {
        // Session lejárt
        session_destroy();
        header("Location: /BetMatchBonus/frontend/MainMenu/MainMenu.php?timeout=1");
        exit();
    }
}

// Utolsó aktivitás frissítése
$_SESSION['last_activity'] = time();
?>
