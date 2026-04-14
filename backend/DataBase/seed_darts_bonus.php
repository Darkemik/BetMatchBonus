<?php
require_once __DIR__ . '/../connect.php';

// Insert Darts Bonus
$code = 'DARTSBONUSZ5K';

// Check if already exists
$check = $conn->prepare("SELECT id FROM BonusCodes WHERE code = ?");
$check->bind_param("s", $code);
$check->execute();
$existing = $check->get_result()->fetch_assoc();
$check->close();

if ($existing) {
    echo "Darts bonus already exists (id={$existing['id']}). Skipping.\n";
} else {
    $sql = "INSERT INTO BonusCodes
        (code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
         bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
         wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
         valid_weekdays_only, daily_start_time, activation_expire_hours,
         specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
         valid_from, valid_to, is_active)
        VALUES (
          'DARTSBONUSZ5K',
          'DARTS BÓNUSZ (10.000 Ft fogadás, 5.000 Ft bónusz)',
          'Darts rajongóknak szóló exkluzív bónusz! Hogyan szerezheted meg? 1) Tégy meg egy legalább 10.000 Ft értékű fogadást kizárólag darts mérkőzésekre. 2) A fogadásnak legalább 2 eseményt (2-es kötést) kell tartalmaznia, minimum 2.00-es össz odds-szal. 3) A fogadásod lezárása és kiértékelése után 5.000 Ft bónusz pénzt kapsz a bónusz egyenlegedre. 4) A kapott 5.000 Ft bónuszt 2-szeresen kell megforgatnod (10.000 Ft értékű fogadás), mielőtt kifizethetővé válik. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: Az aktiválás után 48 órád van a bónusz felhasználására!',
          4, 5000.00, 10000.00, 5000.00, 0.00,
          'BONUS_MONEY', 'BET', 'DARTS', 0, 2.0, 2, NULL,
          2.0, 5.0, 1, 0, NULL, NULL,
          0, NULL, 48,
          NULL, NULL, 0, 0, NULL, 1,
          '2026-01-01 00:00:00', NULL, 1
        )";
    
    if ($conn->query($sql)) {
        echo "Darts bonus inserted! ID: " . $conn->insert_id . "\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
}
