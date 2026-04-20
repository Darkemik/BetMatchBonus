<?php
/**
 * Migráció: UserSessions tábla bővítése multi-device session kezeléshez.
 * + Users tábla force_logout_at oszlop hozzáadása.
 * + SystemSettings: max_concurrent_sessions beállítás.
 *
 * Futtatás: php migrate_user_sessions.php
 */
require_once __DIR__ . '/../connect.php';

$queries = [
    // UserSessions bővítés
    "ALTER TABLE UserSessions ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER is_active",
    "ALTER TABLE UserSessions ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER ip_address",
    "ALTER TABLE UserSessions ADD COLUMN last_active_at DATETIME DEFAULT NULL AFTER user_agent",
    "ALTER TABLE UserSessions ADD INDEX idx_session_user_active (user_id, is_active)",

    // Users: force_logout_at az admin force-logout funkcióhoz
    "ALTER TABLE Users ADD COLUMN force_logout_at DATETIME DEFAULT NULL AFTER login_locked_until",

    // SystemSettings: max egyidejű session
    "INSERT INTO SystemSettings (setting_key, setting_value, label, description, category, input_type)
     VALUES ('max_concurrent_sessions', '5', 'Max egyidejű munkamenet', 'Egy felhasználó egyszerre ennyi eszközön lehet bejelentkezve (0 = korlátlan).', 'security', 'number')
     ON DUPLICATE KEY UPDATE setting_key = setting_key",
];

echo "=== UserSessions migráció ===\n";
foreach ($queries as $sql) {
    try {
        $conn->query($sql);
        echo "[OK]  " . mb_substr($sql, 0, 80) . "...\n";
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'duplicate') !== false) {
            echo "[SKIP] Már létezik: " . mb_substr($sql, 0, 80) . "...\n";
        } else {
            echo "[ERR]  " . $e->getMessage() . "\n       " . mb_substr($sql, 0, 120) . "\n";
        }
    }
}

echo "\nKész!\n";
