<?php
/**
 * Közös admin sidebar.
 * Használat: $activePage = 'dashboard'; include __DIR__ . '/sidebar.php';
 * Szükséges: $role, $perms (get_role_permissions() eredménye), $activePage
 */
if (!isset($activePage)) $activePage = '';
if (!isset($perms)) $perms = [];

$sidebarPages = [
    ['key' => 'dashboard',     'href' => 'dashboard.php',     'icon' => '👥', 'label' => 'Felhasználók',    'section' => 'Általános'],
    ['key' => 'registrations', 'href' => 'registrations.php', 'icon' => '📋', 'label' => 'Regisztrációk',   'section' => 'Általános'],
    ['key' => 'tickets',       'href' => 'tickets.php',       'icon' => '🎫', 'label' => 'Szelvények',      'section' => 'Általános'],
    ['key' => 'bonuses',       'href' => 'bonuses.php',       'icon' => '🎁', 'label' => 'Bónuszok',        'section' => 'Pénzügy'],
    ['key' => 'deposits',      'href' => 'deposits.php',      'icon' => '💰', 'label' => 'Befizetések',      'section' => 'Pénzügy'],
    ['key' => 'withdrawals',   'href' => 'withdrawals.php',   'icon' => '💸', 'label' => 'Kifizetések',      'section' => 'Pénzügy'],
    ['key' => 'statistics',    'href' => 'statistics.php',    'icon' => '📊', 'label' => 'Statisztikák',    'section' => 'Riportok'],
    ['key' => 'notifications', 'href' => 'notifications.php', 'icon' => '🔔', 'label' => 'Értesítések',      'section' => 'Kommunikáció'],
];

$lastSection = '';
foreach ($sidebarPages as $p) {
    // Jogosultság ellenőrzés
    if (!($perms[$p['key']] ?? false)) continue;

    // Szekció fejléc
    if ($p['section'] !== $lastSection) {
        echo '<div class="nav-section">' . htmlspecialchars($p['section']) . '</div>' . "\n";
        $lastSection = $p['section'];
    }

    $activeStyle = ($activePage === $p['key']) ? ' style="color: #fff; background: #0f3460;"' : '';
    echo '<a href="' . $p['href'] . '" class="nav-link"' . $activeStyle . '>' . $p['icon'] . ' ' . $p['label'] . '</a>' . "\n";
}

// Rendszer szekció — mindig SUPERADMIN only
if ($role === 'SUPERADMIN') {
    echo '<div class="nav-section">Rendszer</div>' . "\n";
    $staffActive = ($activePage === 'staff') ? ' style="color: #fff; background: #0f3460;"' : '';
    echo '<a href="staff.php" class="nav-link"' . $staffActive . '>🛡️ Staff (Adminok)</a>' . "\n";
    $auditActive = ($activePage === 'audit_logs') ? ' style="color: #fff; background: #0f3460;"' : '';
    echo '<a href="audit_logs.php" class="nav-link"' . $auditActive . '>📋 Audit Logs</a>' . "\n";
    $settingsActive = ($activePage === 'settings') ? ' style="color: #fff; background: #0f3460;"' : '';
    echo '<a href="settings.php" class="nav-link"' . $settingsActive . '>⚙️ Rendszerbeállítások</a>' . "\n";
}
?>
