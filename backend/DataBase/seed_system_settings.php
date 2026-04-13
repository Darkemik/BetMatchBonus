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
        category VARCHAR(50) DEFAULT 'general',
        label VARCHAR(100) DEFAULT NULL,
        input_type VARCHAR(20) DEFAULT 'number',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// Hiányzó oszlopok hozzáadása (ha régi verzió van)
$conn->query("ALTER TABLE SystemSettings ADD COLUMN IF NOT EXISTS category VARCHAR(50) DEFAULT 'general' AFTER description");
$conn->query("ALTER TABLE SystemSettings ADD COLUMN IF NOT EXISTS label VARCHAR(100) DEFAULT NULL AFTER category");
$conn->query("ALTER TABLE SystemSettings ADD COLUMN IF NOT EXISTS input_type VARCHAR(20) DEFAULT 'number' AFTER label");

// Alapértékek: [key, value, description, category, label, input_type]
$defaults = [
    ['min_deposit',             '3000',   'Minimum befizetés (Ft)',                  'deposit',       'Minimum befizetés',              'number'],
    ['max_deposit',             '600000', 'Maximum befizetés (Ft)',                  'deposit',       'Maximum befizetés',              'number'],
    ['min_withdrawal',          '6000',   'Minimum kifizetés (Ft)',                  'withdrawal',    'Minimum kifizetés',              'number'],
    ['max_withdrawal',          '50000',  'Maximum kifizetés (Ft)',                  'withdrawal',    'Maximum kifizetés',              'number'],
    ['min_bet_amount',          '100',    'Minimum tét összeg (Ft)',                 'betting',       'Minimum tét',                    'number'],
    ['min_password_length',     '7',      'Minimum jelszóhossz',                    'security',      'Minimum jelszóhossz',            'number'],
    ['min_user_age',            '18',     'Minimum regisztrációs kor',              'registration',  'Minimum életkor',                'number'],
    ['min_phone_length',        '11',     'Minimum telefonszám hossz',              'registration',  'Minimum telefonszám hossz',      'number'],
    ['session_timeout_minutes', '30',     'Session timeout (perc)',                  'security',      'Session timeout (perc)',          'number'],
    ['max_login_attempts',      '3',      'Maximum bejelentkezési próbálkozás',     'security',      'Max. bejelentkezési próbálkozás', 'number'],
    ['login_lockout_minutes',   '60',     'Zárolás időtartama (perc)',              'security',      'Zárolás időtartama (perc)',       'number'],
    ['recaptcha_threshold',     '0.5',    'reCAPTCHA küszöbérték',                  'security',      'reCAPTCHA küszöbérték',           'number'],
    ['daily_tip_multiplier',    '1.2',    'Napi tipp szorzó',                       'betting',       'Napi tipp szorzó',               'number'],
    ['odds_pyramid_multiplier', '1.3',    'Odds piramis szorzó',                    'betting',       'Odds piramis szorzó',            'number'],
    ['min_pyramid_selections',  '6',      'Minimum piramis választás',              'betting',       'Min. piramis választás',         'number'],
];

$stmt = $conn->prepare("
    INSERT INTO SystemSettings (setting_key, setting_value, description, category, label, input_type)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        category   = COALESCE(VALUES(category), category),
        label      = COALESCE(VALUES(label), label),
        input_type = COALESCE(VALUES(input_type), input_type)
");

$count = 0;
foreach ($defaults as [$key, $val, $desc, $cat, $lbl, $type]) {
    $stmt->bind_param('ssssss', $key, $val, $desc, $cat, $lbl, $type);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $count++;
}
$stmt->close();

echo "SystemSettings: tábla kész, {$count} beállítás frissítve.\n";
