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

// Session timeout ellenőrzése (30 perc)
$timeout = 60 * 30; // 30 minutes
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
