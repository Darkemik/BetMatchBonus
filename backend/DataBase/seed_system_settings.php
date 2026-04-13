<?php
/**
 * SEED_SYSTEM_SETTINGS.PHP — SystemSettings tábla létrehozása és alapértékek
 */

require_once __DIR__ . '/../connect.php';

// Tábla létrehozása ha nem létezik
$conn->query("
    CREATE TABLE IF NOT EXISTS SystemSettings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        description VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Alapértékek
$defaults = [
    ['min_deposit',             '3000',   'Minimum befizetés (Ft)'],
    ['max_deposit',             '600000', 'Maximum befizetés (Ft)'],
    ['min_withdrawal',          '6000',   'Minimum kifizetés (Ft)'],
    ['max_withdrawal',          '50000',  'Maximum kifizetés (Ft)'],
    ['min_bet_amount',          '100',    'Minimum tét összeg (Ft)'],
    ['min_password_length',     '7',      'Minimum jelszóhossz'],
    ['min_user_age',            '18',     'Minimum regisztrációs kor'],
    ['min_phone_length',        '11',     'Minimum telefonszám hossz'],
    ['session_timeout_minutes', '30',     'Session timeout (perc)'],
    ['max_login_attempts',      '3',      'Maximum bejelentkezési próbálkozás'],
    ['login_lockout_minutes',   '60',     'Zárolás időtartama (perc)'],
    ['recaptcha_threshold',     '0.5',    'reCAPTCHA küszöbérték'],
    ['daily_tip_multiplier',    '1.2',    'Napi tipp szorzó'],
    ['odds_pyramid_multiplier', '1.3',    'Odds piramis szorzó'],
    ['min_pyramid_selections',  '6',      'Minimum piramis választás'],
];

$stmt = $conn->prepare("
    INSERT IGNORE INTO SystemSettings (setting_key, setting_value, description)
    VALUES (?, ?, ?)
");

$count = 0;
foreach ($defaults as [$key, $val, $desc]) {
    $stmt->bind_param('sss', $key, $val, $desc);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $count++;
}
$stmt->close();

echo "SystemSettings: tábla kész, {$count} új beállítás hozzáadva.\n";
