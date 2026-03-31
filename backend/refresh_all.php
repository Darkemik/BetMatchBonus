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
}

require_once __DIR__ . '/connect.php';

// ── 1. BÓNUSZ FRISSÍTÉS ─────────────────────────
$stepStart = microtime(true);
try {
    $isWeekday = (date('N') <= 5);
    $active = $isWeekday ? 1 : 0;
    $conn->query("UPDATE BonusCodes SET is_active = {$active} WHERE code = 'BONUSZHETKOZNAP5K'");

    $weekendActive = $isWeekday ? 0 : 1;
    $conn->query("UPDATE BonusCodes SET is_active = {$weekendActive} WHERE code = 'BONUSZHETVEGE5K'");

    $results[] = [
        'step'    => 'Bónusz frissítés',
        'status'  => 'ok',
        'message' => 'Hétköznapi: ' . ($isWeekday ? 'AKTÍV' : 'INAKTÍV') . ' | Hétvégi: ' . (!$isWeekday ? 'AKTÍV' : 'INAKTÍV'),
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