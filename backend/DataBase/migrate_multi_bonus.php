<?php
/**
 * MIGRATE_MULTI_BONUS.PHP
 * Migráció: többszörös bónusz rendszer — minden bónuszhoz saját egyenleg
 * 
 * 1. UserBonuses.bonus_balance — egyedi bónusz egyenleg
 * 2. Tickets.user_bonus_id — melyik bónuszból fogadtak
 * 3. Meglévő adatok migrálása
 */
require_once dirname(__DIR__) . '/connect.php';

echo "=== Multi-Bonus Migráció ===\n\n";

// 1. UserBonuses.bonus_balance oszlop hozzáadása
$col = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'UserBonuses' AND COLUMN_NAME = 'bonus_balance'");
$row = $col->fetch_assoc();
if ((int)$row['cnt'] === 0) {
    $conn->query("ALTER TABLE UserBonuses ADD COLUMN bonus_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER wagering_progress");
    echo "[OK] UserBonuses.bonus_balance oszlop hozzáadva.\n";
} else {
    echo "[SKIP] UserBonuses.bonus_balance már létezik.\n";
}

// 2. Tickets.user_bonus_id oszlop hozzáadása
$col2 = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Tickets' AND COLUMN_NAME = 'user_bonus_id'");
$row2 = $col2->fetch_assoc();
if ((int)$row2['cnt'] === 0) {
    $conn->query("ALTER TABLE Tickets ADD COLUMN user_bonus_id INT DEFAULT NULL AFTER bonus_stake");
    echo "[OK] Tickets.user_bonus_id oszlop hozzáadva.\n";
} else {
    echo "[SKIP] Tickets.user_bonus_id már létezik.\n";
}

// 3. Meglévő aktív bónuszok migrálása: bonus_balance = granted_amount - wagering_progress figyelembe vétel nélkül
// Ha van aktív bónusz, tegyük bele a teljes granted_amount-ot (ha még nem volt beállítva)
$conn->query("
    UPDATE UserBonuses
    SET bonus_balance = COALESCE(granted_amount, 0)
    WHERE status = 'ACTIVE'
      AND used = 0
      AND bonus_balance = 0
      AND COALESCE(granted_amount, 0) > 0
");
$migrated = $conn->affected_rows;
echo "[OK] $migrated aktív bónusz migrálva (bonus_balance = granted_amount).\n";

// 4. Users.bonus_balance szinkronizálása (az új egyedi egyenlegek összege)
$conn->query("
    UPDATE Users u
    SET u.bonus_balance = (
        SELECT COALESCE(SUM(ub.bonus_balance), 0)
        FROM UserBonuses ub
        WHERE ub.user_id = u.id
          AND ub.status = 'ACTIVE'
          AND ub.used = 0
          AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
    )
");
echo "[OK] Users.bonus_balance szinkronizálva.\n";

echo "\n=== Migráció kész! ===\n";
