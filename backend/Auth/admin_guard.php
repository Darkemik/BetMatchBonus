<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function admin_guard($minRole = 'MOD') {
    $roles = ['MOD' => 1, 'ADMIN' => 2, 'SUPERADMIN' => 3];

    if (!isset($_SESSION['admin_id'])) {
        header('Location: /BetMatchBonus/frontend/Admin/admin_login.php');
        exit;
    }

    $userLevel = $roles[$_SESSION['admin_role']] ?? 0;
    $minLevel  = $roles[$minRole] ?? 0;

    if ($userLevel < $minLevel) {
        die('Nincs jogosultságod ehhez az oldalhoz. Minimum szint: ' . $minRole);
    }
}