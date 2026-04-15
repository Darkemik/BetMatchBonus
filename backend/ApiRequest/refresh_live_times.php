<?php
/**
 * REFRESH_LIVE_TIMES.PHP — Könnyű endpoint: csak élő meccsidők frissítése
 * 
 * Csak az API /api/matches/live végpontot hívja, és frissíti:
 *   - live_time (pl. "45'", "HT", "62'")
 *   - home_score, away_score
 *   - is_live, status_id (ha befejezett lett)
 * 
 * Nem szinkronizál sportokat, bajnokságokat, napi meccseket!
 * Throttle: max 30 mp-ként egyszer (szerver oldali védelem).
 * 
 * Hívás: GET /backend/ApiRequest/refresh_live_times.php?sport_id=66
 * Válasz: JSON { updated: 12, elapsed_ms: 340 }
 */

date_default_timezone_set('Europe/Budapest');

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/connect.php';
require_once dirname(__DIR__) . '/config.php';

$sportApiId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

if ($sportApiId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'sport_id kötelező']);
    exit;
}

// ── Szerver oldali throttle (30 mp / sport) ──
$cacheKey = 'rlt_' . $sportApiId;
$now = time();

$stmtThrottle = $conn->prepare("SELECT setting_value FROM SystemSettings WHERE setting_key = ? LIMIT 1");
$stmtThrottle->bind_param("s", $cacheKey);
$stmtThrottle->execute();
$throttleRow = $stmtThrottle->get_result()->fetch_assoc();
$stmtThrottle->close();

$lastRun = $throttleRow ? (int)$throttleRow['setting_value'] : 0;

if ($now - $lastRun < 25) {
    echo json_encode(['skipped' => true, 'reason' => 'throttle', 'retry_in' => 25 - ($now - $lastRun)]);
    exit;
}

// Throttle timestamp frissítése
if ($throttleRow) {
    $stmtUp = $conn->prepare("UPDATE SystemSettings SET setting_value = ? WHERE setting_key = ?");
    $nowStr = (string)$now;
    $stmtUp->bind_param("ss", $nowStr, $cacheKey);
    $stmtUp->execute();
    $stmtUp->close();
} else {
    $stmtIns = $conn->prepare("INSERT IGNORE INTO SystemSettings (setting_key, setting_value, description, category, label, input_type) VALUES (?, ?, 'Live time throttle', 'internal', '', 'hidden')");
    $nowStr = (string)$now;
    $stmtIns->bind_param("ss", $cacheKey, $nowStr);
    $stmtIns->execute();
    $stmtIns->close();
}

$startTime = microtime(true);

try {
    $liveMatches = apiGet(EP_MATCHES_LIVE, ['sportId' => $sportApiId]);

    if (!is_array($liveMatches)) {
        echo json_encode(['updated' => 0, 'error' => 'API nem tömböt adott vissza']);
        exit;
    }

    $finishedKeywords = FINISHED_KEYWORDS;

    $stmtUpdate = $conn->prepare("
        UPDATE Events 
        SET live_time = ?, home_score = ?, away_score = ?, is_live = ?, status_id = ?
        WHERE api_id = ?
    ");

    if (!$stmtUpdate) {
        echo json_encode(['updated' => 0, 'error' => 'DB prepare hiba']);
        exit;
    }

    $updated = 0;

    foreach ($liveMatches as $match) {
        $matchId = (int)($match['id'] ?? 0);
        if ($matchId <= 0) continue;

        $score = $match['score'] ?? [];
        $homeScore = isset($score[0]) ? (int)$score[0] : null;
        $awayScore = isset($score[1]) ? (int)$score[1] : null;
        $isLive = !empty($match['isLive']) ? 1 : 0;
        $liveTime = isset($match['liveTime']) ? (string)$match['liveTime'] : null;
        $liveStatus = $match['liveStatus'] ?? $match['status'] ?? null;

        // Status meghatározás
        if ($isLive) {
            $statusId = 2; // LIVE
        } else {
            $isFinished = false;
            $checkTexts = array_filter([$liveStatus, $liveTime]);
            foreach ($checkTexts as $txt) {
                $lower = strtolower(trim($txt));
                foreach ($finishedKeywords as $kw) {
                    if (strpos($lower, $kw) !== false) {
                        $isFinished = true;
                        break 2;
                    }
                }
            }
            $statusId = $isFinished ? 3 : 2; // Ha nem egyértelmű, marad LIVE
        }

        $stmtUpdate->bind_param("siiiii", $liveTime, $homeScore, $awayScore, $isLive, $statusId, $matchId);
        $stmtUpdate->execute();

        if ($stmtUpdate->affected_rows > 0) {
            $updated++;
        }
    }
    $stmtUpdate->close();

    $elapsed = round((microtime(true) - $startTime) * 1000);

    echo json_encode([
        'updated' => $updated,
        'total_live' => count($liveMatches),
        'elapsed_ms' => $elapsed
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API hiba: ' . $e->getMessage()]);
}
