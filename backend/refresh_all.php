<?php
/**
 * REFRESH_ALL.PHP
 * 
 * Egy fájl, amit lefuttatsz és frissül minden:
 *   1) Bónusz aktivitás (hétköznap/hétvége)
 *   2) Meccsek szinkronizálása az API-ból
 *   3) Szelvények kiértékelése
 * 
 * Használat:
 *   Böngésző: http://localhost/backend/refresh_all.php
 *   Terminál: php backend/refresh_all.php
 */

date_default_timezone_set('Europe/Budapest');
$startTime = microtime(true);
$results = [];
$hasError = false;

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

// DB kapcsolat
require_once __DIR__ . '/connect.php';

// ── 1. Bónusz frissítés ──────────────────────────
$stepStart = microtime(true);
try {
    $isWeekday = (date('N') <= 5); // H-P = 1-5
    $active = $isWeekday ? 1 : 0;
    $conn->query("UPDATE BonusCodes SET is_active = {$active} WHERE code = 'BONUSZHETKOZNAP5K'");

    $results[] = [
        'step' => 'Bónusz frissítés',
        'status' => 'ok',
        'message' => 'Hétköznapi bónusz: ' . ($isWeekday ? 'AKTÍV' : 'INAKTÍV'),
        'ms' => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Bónusz frissítés', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 2. Meccsek szinkronizálása ───────────────────
$stepStart = microtime(true);
try {
    ob_start();
    require __DIR__ . '/ApiRequest/sync_competitions_and_events.php';
    $output = trim(ob_get_clean());

    $json = json_decode($output, true);
    if (is_array($json) && isset($json['success']) && $json['success'] === false) {
        throw new RuntimeException($json['error'] ?? 'Szinkron hiba');
    }

    $results[] = [
        'step' => 'Meccs szinkron',
        'status' => 'ok',
        'message' => 'Sportok + meccsek frissítve',
        'ms' => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Meccs szinkron', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 3. Szelvények kiértékelése ───────────────────
$stepStart = microtime(true);
try {
    if (!function_exists('evaluateAllOpenTickets')) {
        require_once __DIR__ . '/ApiRequest/check_bets.php';
    }

    $evaluatedUsers = evaluateAllOpenTickets($conn);

    $results[] = [
        'step' => 'Szelvény kiértékelés',
        'status' => 'ok',
        'message' => "{$evaluatedUsers} felhasználó szelvényei ellenőrizve",
        'ms' => round((microtime(true) - $stepStart) * 1000),
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
        'success' => !$hasError,
        'total_ms' => $totalMs,
        'időpont' => date('Y-m-d H:i:s'),
        'lépések' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}