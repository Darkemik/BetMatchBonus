<?php
require_once __DIR__ . '/../connect.php';

// Check BonusTypes
$r = $conn->query("SELECT * FROM BonusTypes ORDER BY id");
echo "=== BonusTypes ===\n";
while ($row = $r->fetch_assoc()) {
    echo "id={$row['id']} name={$row['name']}\n";
}

// Check existing bonuses
$r = $conn->query("SELECT id, code, name, bonus_type_id, sport_restriction, live_only, is_active FROM BonusCodes ORDER BY id");
echo "\n=== Existing BonusCodes ===\n";
while ($row = $r->fetch_assoc()) {
    echo "id={$row['id']} code={$row['code']} name={$row['name']} type={$row['bonus_type_id']} sport={$row['sport_restriction']} live={$row['live_only']} active={$row['is_active']}\n";
}

// Check Sports table for darts
$r = $conn->query("SELECT id, name, api_id FROM Sports WHERE UPPER(name) LIKE '%DART%'");
echo "\n=== Darts in Sports ===\n";
if ($r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "id={$row['id']} name={$row['name']} api_id={$row['api_id']}\n";
    }
} else {
    echo "No darts sport found. Checking all sports...\n";
    $r2 = $conn->query("SELECT id, name, api_id FROM Sports ORDER BY name LIMIT 30");
    while ($row = $r2->fetch_assoc()) {
        echo "id={$row['id']} name={$row['name']} api_id={$row['api_id']}\n";
    }
}

// Check live events
$r = $conn->query("SELECT s.name, s.api_id, COUNT(*) as cnt FROM Events e JOIN Sports s ON e.sport_id = s.id WHERE e.is_live = 1 GROUP BY s.id ORDER BY cnt DESC");
echo "\n=== Live events by sport ===\n";
while ($row = $r->fetch_assoc()) {
    echo "{$row['name']} (api_id={$row['api_id']}): {$row['cnt']} live\n";
}
