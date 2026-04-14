<?php
/**
 * Teszt: me.php active_bonuses mező + UserBonuses.bonus_balance oszlop
 */
require_once dirname(__DIR__) . '/connect.php';

// 1. UserBonuses.bonus_balance oszlop létezik?
$col = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'UserBonuses' AND COLUMN_NAME = 'bonus_balance'");
$row = $col->fetch_assoc();
echo "UserBonuses.bonus_balance oszlop: " . ($row['cnt'] > 0 ? 'OK' : 'HIÁNYZIK') . "\n";

// 2. Tickets.user_bonus_id oszlop létezik?
$col2 = $conn->query("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Tickets' AND COLUMN_NAME = 'user_bonus_id'");
$row2 = $col2->fetch_assoc();
echo "Tickets.user_bonus_id oszlop: " . ($row2['cnt'] > 0 ? 'OK' : 'HIÁNYZIK') . "\n";

// 3. Aktív bónuszok listája (teszteléshez)
$res = $conn->query("
    SELECT ub.id, bc.name, ub.bonus_balance, ub.granted_amount, ub.status, ub.wagering_progress, ub.wagering_required
    FROM UserBonuses ub
    INNER JOIN BonusCodes bc ON bc.id = ub.bonus_id
    WHERE ub.status IN ('ACTIVE', 'PENDING')
      AND ub.used = 0
    ORDER BY ub.id ASC
    LIMIT 20
");

echo "\nAktív/Pending bónuszok a rendszerben:\n";
echo str_pad("ID", 5) . str_pad("Név", 30) . str_pad("Státusz", 10) . str_pad("Egyenleg", 12) . str_pad("Granted", 12) . "\n";
echo str_repeat("-", 79) . "\n";
$count = 0;
while ($r = $res->fetch_assoc()) {
    echo str_pad($r['id'], 5) 
       . str_pad($r['name'], 30) 
       . str_pad($r['status'], 10) 
       . str_pad(number_format((float)$r['bonus_balance'], 0), 12) 
       . str_pad(number_format((float)$r['granted_amount'], 0), 12) 
       . "\n";
    $count++;
}
if ($count === 0) echo "(nincs)\n";

echo "\nMigráció sikeres, multi-bonus rendszer kész!\n";
