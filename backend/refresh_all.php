<?php
/**
 * REFRESH_ALL.PHP — ⭐ EGY GOMBNYOMÁS = MINDEN FRISSÜL
 * 
 * Lépések:
 *   1) Bónusz aktivitás frissítés (hétköznap/hétvége)
 *   2) Sportadatok szinkronizálása (API → DB) via sync_competitions_and_events.php
 *   3) Nyitott szelvények kiértékelése via check_bets.php
 * 
 * Használat:
 *   Böngésző: http://localhost/backend/refresh_all.php
 *   Terminál: php backend/refresh_all.php
 *   CRON:     every 2 min — php /path/to/backend/refresh_all.php
 */

date_default_timezone_set('Europe/Budapest');
$startTime = microtime(true);
$results   = [];
$hasError  = false;

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    // Session lock feloldása — ne blokkolja a párhuzamos AJAX kéréseket
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

require_once __DIR__ . '/connect.php';

// ── 0. SEED CHECK — Ha üresek a táblák, automatikusan feltölti ──
try {
    $needPostal = $conn->query("SHOW TABLES LIKE 'PostalCodes'")->num_rows === 0
                  || $conn->query("SELECT 1 FROM PostalCodes LIMIT 1")->num_rows === 0;
    if ($needPostal) {
        require_once __DIR__ . '/DataBase/seed_postal_codes.php';
        $results[] = ['step' => 'Seed: PostalCodes', 'status' => 'ok'];
    }

    $needCities = $conn->query("SELECT 1 FROM Cities LIMIT 1")->num_rows === 0;
    if ($needCities) {
        require_once __DIR__ . '/DataBase/seed_cities.php';
        $results[] = ['step' => 'Seed: Cities', 'status' => 'ok'];
    }

    $needAdmins = $conn->query("SELECT 1 FROM AdminUsers LIMIT 1")->num_rows === 0;
    if ($needAdmins) {
        require_once __DIR__ . '/DataBase/seed_admins.php';
        $results[] = ['step' => 'Seed: AdminUsers', 'status' => 'ok'];
    }

    $needSettings = $conn->query("SHOW TABLES LIKE 'SystemSettings'")->num_rows === 0
                    || $conn->query("SELECT 1 FROM SystemSettings LIMIT 1")->num_rows === 0;
    if ($needSettings) {
        require_once __DIR__ . '/DataBase/seed_system_settings.php';
        $results[] = ['step' => 'Seed: SystemSettings', 'status' => 'ok'];
    }
} catch (Exception $e) {
    $results[] = ['step' => 'Seed check', 'status' => 'error', 'message' => $e->getMessage()];
}

// ── 1. BÓNUSZ FRISSÍTÉS ─────────────────────────
$stepStart = microtime(true);
try {
    // Hétköznapi bónusz fix üzleti paraméterek (nem lépcsős):
    // min befizetés 3000 Ft, 100% bónusz max 5000 Ft, 3x forgatás.
    $conn->query(" 
        UPDATE BonusCodes
        SET min_deposit = 3000.00,
            max_bonus_amount = 5000.00,
            match_percent = 100.00,
            bonus_amount = 0.00,
            is_step_bonus = 0,
            bonus_trigger = 'DEPOSIT',
            bet_reward_type = 'BONUS_MONEY',
            wagering_multiplier = 3.00,
            valid_weekdays_only = 1
        WHERE code = 'BONUSZHETKOZNAP5K'
    ");

    $isWeekday = ((int)date('N') <= 5) ? 1 : 0;

    // Hétköznap-only bónuszok auto-toggle: daily_start_time figyelembevételével
    // admin_force_active = 1 esetén nem írjuk felül (admin kézzel bekapcsolta)
    // Csak akkor töröljük az override-ot, ha a normál időablakban vagyunk (hétköznap + daily_start_time után)
    if ($isWeekday) {
        $conn->query("UPDATE BonusCodes SET admin_force_active = 0 WHERE valid_weekdays_only = 1 AND admin_force_active = 1 AND (daily_start_time IS NULL OR CURTIME() >= daily_start_time)");
    }
    $conn->query("
        UPDATE BonusCodes
        SET is_active = CASE
            WHEN admin_force_active = 1 THEN 1
            WHEN {$isWeekday} = 1 AND (daily_start_time IS NULL OR CURTIME() >= daily_start_time) THEN 1
            ELSE 0
        END
        WHERE valid_weekdays_only = 1
    ");

    $results[] = [
        'step'    => 'Bónusz frissítés',
        'status'  => 'ok',
        'message' => 'Hétköznapi auto-toggle: ' . ($isWeekday ? 'hétköznap' : 'hétvége'),
        'ms'      => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Bónusz frissítés', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 2. SPORTADATOK SZINKRONIZÁLÁSA (API → DB) ───
$stepStart = microtime(true);
try {
    ob_start();
    require __DIR__ . '/ApiRequest/sync_competitions_and_events.php';
    $output = trim(ob_get_clean());

    $json = json_decode($output, true);
    if (is_array($json) && isset($json['success']) && $json['success'] === false) {
        throw new RuntimeException($json['error'] ?? 'Szinkron hiba');
    }

    $stats = $json['stats'] ?? [];
    $results[] = [
        'step'    => 'Sportadatok szinkron',
        'status'  => 'ok',
        'message' => sprintf('%d sport, %d bajnokság szinkronizálva, %d meccs lezárva',
            $stats['sports'] ?? 0, $stats['competitions'] ?? 0, $stats['finished'] ?? 0),
        'ms'      => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Sportadatok szinkron', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 3. SZELVÉNYEK KIÉRTÉKELÉSE ───────────────────
$stepStart = microtime(true);
try {
    if (!function_exists('evaluateAllOpenTickets')) {
        require_once __DIR__ . '/ApiRequest/check_bets.php';
    }

    $evaluatedUsers = evaluateAllOpenTickets($conn);

    $results[] = [
        'step'    => 'Szelvény kiértékelés',
        'status'  => 'ok',
        'message' => "{$evaluatedUsers} felhasználó szelvényei ellenőrizve",
        'ms'      => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Szelvény kiértékelés', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 4. NAPI TOP JUTALMAK (23:00 után egyszer) ───
$stepStart = microtime(true);
try {
    $currentHour = (int)date('H');
    if ($currentHour >= 23) {
        require_once __DIR__ . '/ApiRequest/daily_top_rewards.php';
        $topResult = awardDailyTopRewards($conn);

        if (!empty($topResult['skipped'])) {
            $results[] = [
                'step'    => 'Napi top jutalmak',
                'status'  => 'ok',
                'message' => $topResult['message'] ?? 'Kihagyva',
                'ms'      => round((microtime(true) - $stepStart) * 1000),
            ];
        } else {
            $awardedCount = 0;
            $names = [];
            foreach ($topResult['awarded'] ?? [] as $a) {
                if ($a['status'] === 'awarded') {
                    $awardedCount++;
                    $names[] = $a['user'];
                }
            }
            $results[] = [
                'step'    => 'Napi top jutalmak',
                'status'  => 'ok',
                'message' => "{$awardedCount} jutalom kiosztva" . ($names ? ': ' . implode(', ', $names) : ''),
                'ms'      => round((microtime(true) - $stepStart) * 1000),
            ];
        }
    } else {
        $results[] = [
            'step'    => 'Napi top jutalmak',
            'status'  => 'ok',
            'message' => 'Csak 23:00 után fut (most: ' . date('H:i') . ')',
            'ms'      => 0,
        ];
    }
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Napi top jutalmak', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── EREDMÉNY ─────────────────────────────────────
$totalMs = round((microtime(true) - $startTime) * 1000);

if ($isCli) {
    echo "\n=== BetMatchBonus Frissítés - " . date('Y-m-d H:i:s') . " ===\n";
    foreach ($results as $r) {
        $icon = ($r['status'] === 'ok') ? '✅' : '❌';
        echo "  {$icon} {$r['step']}: {$r['message']}\n";
    }
    echo "  ⏱ Összesen: {$totalMs}ms\n\n";
    if ($hasError) exit(1);
} else {
    if ($hasError) http_response_code(500);
    echo json_encode([
        'success'  => !$hasError,
        'total_ms' => $totalMs,
        'időpont'  => date('Y-m-d H:i:s'),
        'lépések'  => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}