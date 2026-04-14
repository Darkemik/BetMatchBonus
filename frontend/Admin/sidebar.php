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
    ['key' => 'registrations',     'href' => 'registrations.php',     'icon' => '📋', 'label' => 'Regisztrációk',           'section' => 'Általános'],
    ['key' => 'data_verification', 'href' => 'data_verification.php', 'icon' => '🔍', 'label' => 'Adatellenőrzés',         'section' => 'Általános'],
    ['key' => 'tickets',           'href' => 'tickets.php',           'icon' => '🎫', 'label' => 'Szelvények',             'section' => 'Általános'],
    ['key' => 'bonuses',       'href' => 'bonuses.php',       'icon' => '🎁', 'label' => 'Bónuszok',        'section' => 'Pénzügy'],
    ['key' => 'freebet',       'href' => 'freebet.php',       'icon' => '🎟️', 'label' => 'Free Bet',        'section' => 'Pénzügy'],
    ['key' => 'deposits',      'href' => 'deposits.php',      'icon' => '💰', 'label' => 'Befizetések',      'section' => 'Pénzügy'],
    ['key' => 'withdrawals',   'href' => 'withdrawals.php',   'icon' => '💸', 'label' => 'Kifizetések',      'section' => 'Pénzügy'],
    ['key' => 'balances',      'href' => 'balances.php',      'icon' => '💳', 'label' => 'Egyenlegek',       'section' => 'Pénzügy'],
    ['key' => 'statistics',    'href' => 'statistics.php',    'icon' => '📊', 'label' => 'Statisztikák',    'section' => 'Riportok'],
    ['key' => 'notifications', 'href' => 'notifications.php', 'icon' => '🔔', 'label' => 'Értesítések',      'section' => 'Kommunikáció'],
];

$lastSection = '';
foreach ($sidebarPages as $p) {
    // Jogosultság ellenőrzés
    if (!($perms[$p['key']] ?? false)) continue;

    // Szekció fejléc
    if ($p['section'] !== $lastSection) {
        echo '<div class="nav-section" style="font-size: 0.7rem; text-transform: uppercase; color: #e94560; padding: 14px 20px 4px; letter-spacing: 1px; font-weight: 700;">' . htmlspecialchars($p['section']) . '</div>' . "\n";
        $lastSection = $p['section'];
    }

    $linkStyle = 'font-size: 1rem; line-height: 1.35;';
    if ($activePage === $p['key']) {
        $linkStyle .= ' color: #fff; background: #0f3460;';
    }
    echo '<a href="' . $p['href'] . '" class="nav-link" style="' . $linkStyle . '">' . $p['icon'] . ' ' . $p['label'] . '</a>' . "\n";
}

// Rendszer szekció — mindig SUPERADMIN only
if ($role === 'SUPERADMIN') {
    echo '<div class="nav-section" style="font-size: 0.7rem; text-transform: uppercase; color: #e94560; padding: 14px 20px 4px; letter-spacing: 1px; font-weight: 700;">Rendszer</div>' . "\n";
    $staffStyle = 'font-size: 1rem; line-height: 1.35;' . (($activePage === 'staff') ? ' color: #fff; background: #0f3460;' : '');
    echo '<a href="staff.php" class="nav-link" style="' . $staffStyle . '">🛡️ Staff (Adminok)</a>' . "\n";
    $auditStyle = 'font-size: 1rem; line-height: 1.35;' . (($activePage === 'audit_logs') ? ' color: #fff; background: #0f3460;' : '');
    echo '<a href="audit_logs.php" class="nav-link" style="' . $auditStyle . '">📋 Audit Logs</a>' . "\n";
    $settingsStyle = 'font-size: 1rem; line-height: 1.35;' . (($activePage === 'settings') ? ' color: #fff; background: #0f3460;' : '');
    echo '<a href="settings.php" class="nav-link" style="' . $settingsStyle . '">⚙️ Rendszerbeállítások</a>' . "\n";
}
?>
