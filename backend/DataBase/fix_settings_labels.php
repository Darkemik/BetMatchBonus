<?php
require_once __DIR__ . '/../connect.php';

$updates = [
    ['session_timeout_minutes',       'Inaktivitási időkorlát (perc)',        'Ennyi inaktív perc után dobja ki a felhasználót'],
    ['session_max_duration_minutes',   'Munkamenet időkorlát (perc)',          'Maximum ennyi percig maradhat bejelentkezve'],
    ['login_lockout_minutes',          'Zárolás időtartama (perc)',            'Sikertelen belépések után ennyi percre zárolódik a fiók'],
    ['max_login_attempts',             'Max. bejelentkezési próbálkozás',      'Ennyi sikertelen próbálkozás után zárolódik a fiók'],
    ['min_password_length',            'Minimum jelszóhossz',                  'A jelszónak legalább ennyi karakter hosszúnak kell lennie'],
    ['recaptcha_threshold',            'reCAPTCHA küszöbérték',                'Minimum pontszám a reCAPTCHA ellenőrzéshez (0.0 - 1.0)'],
    ['min_deposit',                    'Minimum befizetés (Ft)',               'A legkisebb összeg amit be lehet fizetni'],
    ['max_deposit',                    'Maximum befizetés (Ft)',               'A legnagyobb összeg amit egyszerre be lehet fizetni'],
    ['min_withdrawal',                 'Minimum kifizetés (Ft)',               'A legkisebb összeg amit ki lehet fizetni'],
    ['max_withdrawal',                 'Maximum kifizetés (Ft)',               'A legnagyobb összeg amit egyszerre ki lehet fizetni'],
    ['min_bet_amount',                 'Minimum tét összeg (Ft)',              'A legkisebb tét amit meg lehet tenni'],
    ['min_user_age',                   'Minimum regisztrációs kor',            'A felhasználónak legalább ennyi évesnek kell lennie'],
    ['min_phone_length',               'Minimum telefonszám hossz',            'A telefonszámnak legalább ennyi karakterből kell állnia'],
    ['daily_tip_multiplier',           'Napi tipp szorzó',                     'A napi tippek extra szorzója'],
    ['odds_pyramid_multiplier',        'Odds piramis szorzó',                  'A piramis bónusz szorzója'],
    ['min_pyramid_selections',         'Min. piramis választás',               'Minimum ennyi tételt kell kiválasztani a piramishoz'],
];

$stmt = $conn->prepare("UPDATE SystemSettings SET label = ?, description = ? WHERE setting_key = ?");

foreach ($updates as [$key, $label, $desc]) {
    $stmt->bind_param("sss", $label, $desc, $key);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    echo "$key: " . ($affected > 0 ? "UPDATED" : "unchanged") . "\n";
}

$stmt->close();
echo "Done!\n";
