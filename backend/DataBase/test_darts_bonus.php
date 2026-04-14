<?php
require_once __DIR__ . '/../connect.php';

// Check if darts bonus is in DB
$r = $conn->query("SELECT id, code, name, sport_restriction, is_active FROM BonusCodes WHERE code = 'DARTSBONUSZ5K'");
$bonus = $r->fetch_assoc();
echo "Darts bonus: id={$bonus['id']} sport={$bonus['sport_restriction']} active={$bonus['is_active']}\n";

// Check live darts events
$r = $conn->query("SELECT COUNT(*) AS cnt FROM Events e JOIN Sports s ON e.sport_id = s.id WHERE e.is_live = 1 AND UPPER(s.name) = 'DARTS'");
$row = $r->fetch_assoc();
echo "Live darts events: {$row['cnt']}\n";

// Simulate the API logic
$liveSportsCache = null;
function hasLiveSport2($conn, $sportName, &$cache) {
    if ($cache === null) {
        $cache = [];
        $r = $conn->query("SELECT UPPER(s.name) AS sport_name, COUNT(*) AS cnt FROM Events e JOIN Sports s ON e.sport_id = s.id WHERE e.is_live = 1 GROUP BY s.id");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $cache[$row['sport_name']] = (int)$row['cnt'];
            }
        }
    }
    return ($cache[strtoupper($sportName)] ?? 0) > 0;
}

$hasDarts = hasLiveSport2($conn, 'DARTS', $liveSportsCache);
echo "hasLiveSport('DARTS'): " . ($hasDarts ? "YES → bonus will show" : "NO → bonus will be hidden") . "\n";

echo "\nLive sports cache:\n";
foreach ($liveSportsCache as $sport => $cnt) {
    echo "  $sport: $cnt live\n";
}
