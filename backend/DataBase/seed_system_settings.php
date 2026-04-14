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
$defaults = get_system_settings_defaults();

function get_system_settings_defaults() {
    return [
        ['min_deposit',                  '3000',   'A legkisebb összeg amit be lehet fizetni',                    'deposit',       'Minimum befizetés (Ft)',              'number'],
        ['max_deposit',                  '600000', 'A legnagyobb összeg amit egyszerre be lehet fizetni',         'deposit',       'Maximum befizetés (Ft)',              'number'],
        ['min_withdrawal',               '6000',   'A legkisebb összeg amit ki lehet fizetni',                    'withdrawal',    'Minimum kifizetés (Ft)',              'number'],
        ['max_withdrawal',               '50000',  'A legnagyobb összeg amit egyszerre ki lehet fizetni',         'withdrawal',    'Maximum kifizetés (Ft)',              'number'],
        ['min_bet_amount',               '100',    'A legkisebb tét amit meg lehet tenni',                        'betting',       'Minimum tét összeg (Ft)',             'number'],
        ['min_password_length',          '7',      'A jelszónak legalább ennyi karakter hosszúnak kell lennie',   'security',      'Minimum jelszóhossz',                'number'],
        ['min_user_age',                 '18',     'A felhasználónak legalább ennyi évesnek kell lennie',         'registration',  'Minimum regisztrációs kor',           'number'],
        ['min_phone_length',             '11',     'A telefonszámnak legalább ennyi karakterből kell állnia',     'registration',  'Minimum telefonszám hossz',           'number'],
        ['session_timeout_minutes',      '30',     'Ennyi inaktív perc után dobja ki a felhasználót',             'security',      'Inaktivitási időkorlát (perc)',        'number'],
        ['session_max_duration_minutes', '60',     'Maximum ennyi percig maradhat bejelentkezve',                 'security',      'Munkamenet időkorlát (perc)',          'number'],
        ['max_login_attempts',           '3',      'Ennyi sikertelen próbálkozás után zárolódik a fiók',          'security',      'Max. bejelentkezési próbálkozás',      'number'],
        ['login_lockout_minutes',        '60',     'Sikertelen belépések után ennyi percre zárolódik a fiók',     'security',      'Zárolás időtartama (perc)',            'number'],
        ['recaptcha_threshold',          '0.5',    'Minimum pontszám a reCAPTCHA ellenőrzéshez (0.0 - 1.0)',      'security',      'reCAPTCHA küszöbérték',               'number'],
        ['daily_tip_multiplier',         '1.2',    'A napi tippek extra szorzója',                                'betting',       'Napi tipp szorzó',                    'number'],
        ['odds_pyramid_multiplier',      '1.3',    'A piramis bónusz szorzója',                                   'betting',       'Odds piramis szorzó',                 'number'],
        ['min_pyramid_selections',       '6',      'Minimum ennyi tételt kell kiválasztani a piramishoz',         'betting',       'Min. piramis választás',              'number'],
    ];
}

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
