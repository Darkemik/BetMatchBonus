<?php
/**
 * Cashback Free Bet bónusz beszúrása az adatbázisba.
 * Egyszeri futtatás.
 */
require_once dirname(__DIR__) . '/connect.php';

// Ellenőrizzük, hogy létezik-e már
$check = $conn->prepare("SELECT id FROM BonusCodes WHERE code = 'CASHBACK30' LIMIT 1");
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    echo "CASHBACK30 bónusz már létezik (id={$existing['id']}), nem szúrjuk be újra.\n";
    exit;
}

$sql = "INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'CASHBACK30',
  'Vesztes fogadás cashback (30% Free Bet)',
  'Ha egy legalább 5.000 Ft-os fogadásod veszít (min. odds: 1.80), visszakapsz 30%-ot Free Bet formájában. Naponta egyszer aktiválódik automatikusan a vesztes szelvény lezárásakor. A kapott Free Bet-et bármilyen fogadásra felhasználhatod.',
  4, 0.00, 5000.00, NULL, 30.00,
  'FREE_BET', 'LOSS', 'ANY', 0, 1.80, NULL, NULL,
  NULL, NULL, 0, 0, NULL, NULL,
  0, NULL, 48,
  NULL, NULL, 0, 0, NULL, 1,
  '2026-01-01 00:00:00', NULL, 1
)";

if ($conn->query($sql)) {
    echo "CASHBACK30 bónusz sikeresen beszúrva! (id=" . $conn->insert_id . ")\n";
} else {
    echo "Hiba: " . $conn->error . "\n";
}
