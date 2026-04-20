<?php
/**
 * GET_LIVE_TICKER.PHP — Eredmény feed + közelgő meccsek
 * 
 * Góldetekció: cache-ben tárolt korábbi eredményeket hasonlítja az aktuális
 * DB állapothoz. A gól-események 5 percig maradnak a feedben.
 * Gólgazdag (3+ összgól) meccsek kiegészítő elemként jelennek meg.
 * 
 * Minden ticker elem kap egy stabil `id` mezőt (goal_{apiId} / hs_{apiId})
 * ami a frontend DOM diffing kulcsa — ezzel nem villognak az elemek.
 * 
 * File locking védi a cache-t az egyidejű írás/olvasás ellen.
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: application/json; charset=utf-8');

$cacheDir  = dirname(__DIR__) . '/uploads';
$cacheFile = $cacheDir . '/live_scores_prev.json';

// sport_id GET param: 0 = összes sport
$sportFilter = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

try {
    $now = time();
    $result = ['ticker' => [], 'upcoming' => [], 'serverTime' => $now];

    // ── 1) Összes élő meccs eredményeinek lekérése ──
    $stmtLive = $conn->prepare("
        SELECT e.api_id, e.name, e.home_score, e.away_score, e.live_time, 
               e.home_team_name, e.away_team_name, s.api_id AS sport_api_id
        FROM Events e
        JOIN Sports s ON e.sport_id = s.id
        WHERE e.is_live = 1
          AND (e.live_time IS NULL OR LOWER(TRIM(e.live_time)) NOT IN ('nem kezdődött el', 'not started', '', 'unknown'))
          AND e.name IS NOT NULL AND TRIM(e.name) != ''
          AND e.home_score IS NOT NULL AND e.away_score IS NOT NULL
        ORDER BY e.updated_at DESC
        LIMIT 500
    ");
    $stmtLive->execute();
    $resLive = $stmtLive->get_result();

    $currentScores = [];
    while ($row = $resLive->fetch_assoc()) {
        $currentScores[(int)$row['api_id']] = [
            'name'       => $row['name'],
            'homeTeam'   => $row['home_team_name'] ?? '',
            'awayTeam'   => $row['away_team_name'] ?? '',
            'homeScore'  => (int)$row['home_score'],
            'awayScore'  => (int)$row['away_score'],
            'liveTime'   => $row['live_time'] ?? '-',
            'sportApiId' => (int)$row['sport_api_id'],
        ];
    }
    $stmtLive->close();

    // ── 2) Cache olvasás + írás atomi file-lock-kal ──
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

    $prevScores = [];
    $goalEvents = [];

    // File lock: olvasás ÉS írás egy lock-on belül → nincs race condition
    $fp = fopen($cacheFile, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $cacheData = $raw ? json_decode($raw, true) : null;
        if (is_array($cacheData)) {
            $prevScores = $cacheData['scores'] ?? [];
            $goalEvents = $cacheData['goals'] ?? [];
        }

        // ── 3) Góldetekció ──
        foreach ($currentScores as $apiId => $match) {
            $prev = $prevScores[$apiId] ?? null;
            if (!$prev) continue;

            $prevHome = (int)($prev['homeScore'] ?? 0);
            $prevAway = (int)($prev['awayScore'] ?? 0);
            $curHome  = $match['homeScore'];
            $curAway  = $match['awayScore'];

            if ($curHome > $prevHome) {
                $goalEvents[] = [
                    'apiId'      => $apiId,
                    'team'       => $match['homeTeam'] ?: explode(' vs', $match['name'])[0],
                    'side'       => 'home',
                    'newScore'   => $curHome . ' - ' . $curAway,
                    'prevScore'  => $prevHome . ' - ' . $prevAway,
                    'time'       => $match['liveTime'],
                    'matchName'  => $match['name'],
                    'sportApiId' => $match['sportApiId'],
                    'sportIcon'  => getSportIcon($match['sportApiId']),
                    'timestamp'  => $now,
                ];
            }
            if ($curAway > $prevAway) {
                $goalEvents[] = [
                    'apiId'      => $apiId,
                    'team'       => $match['awayTeam'] ?: trim(explode('vs.', $match['name'])[1] ?? ''),
                    'side'       => 'away',
                    'newScore'   => $curHome . ' - ' . $curAway,
                    'prevScore'  => $prevHome . ' - ' . $prevAway,
                    'time'       => $match['liveTime'],
                    'matchName'  => $match['name'],
                    'sportApiId' => $match['sportApiId'],
                    'sportIcon'  => getSportIcon($match['sportApiId']),
                    'timestamp'  => $now,
                ];
            }
        }

        // Max 5 perc régi + deduplikáció kulcs alapján (apiId_side → legfrissebb marad)
        $goalEvents = array_filter($goalEvents, function($g) use ($now) {
            return ($now - ($g['timestamp'] ?? 0)) < 300;
        });
        // Deduplikáció: ugyanarra az eseményre (apiId + side + új állás) csak a legfrissebb maradjon.
        $dedupMap = [];
        foreach ($goalEvents as $g) {
            $dk = $g['apiId'] . '_' . $g['side'] . '_' . ($g['newScore'] ?? '');
            if (!isset($dedupMap[$dk]) || $g['timestamp'] > $dedupMap[$dk]['timestamp']) {
                $dedupMap[$dk] = $g;
            }
        }
        $goalEvents = array_values($dedupMap);
        usort($goalEvents, function($a, $b) { return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0); });
        $goalEvents = array_slice($goalEvents, 0, 30);

        // Cache mentése (lock-on belül)
        $scoresToSave = [];
        foreach ($currentScores as $apiId => $match) {
            $scoresToSave[$apiId] = [
                'homeScore' => $match['homeScore'],
                'awayScore' => $match['awayScore'],
            ];
        }
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode([
            'scores'  => $scoresToSave,
            'goals'   => $goalEvents,
            'updated' => $now,
        ], JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);

    // ── 4) Ticker összeállítása (sport szűréssel) ──
    // Eseményszintű elemeket adunk vissza: minden külön gólváltozás külön elem marad.
    foreach ($goalEvents as $goal) {
        if ($sportFilter > 0 && (int)($goal['sportApiId'] ?? 0) !== $sportFilter) continue;

        $aid = $goal['apiId'];

        // Stabil, esemény-szintű ID: ugyanaz az esemény ugyanazzal az ID-val marad,
        // de egy új gólváltozás új ID-t kap.
        $eventId = 'goal_' . $aid
            . '_' . (int)($goal['timestamp'] ?? $now)
            . '_' . preg_replace('/[^a-z]/i', '', (string)($goal['side'] ?? 'x'))
            . '_' . preg_replace('/[^0-9]/', '', (string)($goal['newScore'] ?? ''));

        $result['ticker'][] = [
            'id'        => $eventId,
            'matchId'   => (int)$aid,
            'name'      => $goal['matchName'],
            'score'     => $goal['newScore'],
            'prevScore' => $goal['prevScore'],
            'liveTime'  => $goal['time'],
            'sportIcon' => $goal['sportIcon'],
            'goalTeam'  => $goal['team'],
            'ts'        => (int)($goal['timestamp'] ?? $now),
        ];
    }

    // ── 5) Közelgő meccsek ──
    $nowStr = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $soon   = (new DateTime('+3 hours', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $upcomingSql = "
        SELECT e.api_id, e.name, e.start_time, comp.name AS league, s.api_id AS sport_api_id
        FROM Events e
        JOIN Sports s ON e.sport_id = s.id
        JOIN Competitions comp ON e.competition_id = comp.id
        WHERE e.start_time BETWEEN ? AND ?
          AND e.is_live = 0
          AND (e.status_id IS NULL OR e.status_id = 1)
          AND e.name IS NOT NULL AND TRIM(e.name) != ''
          AND e.api_id IS NOT NULL AND e.api_id > 0
    ";
    if ($sportFilter > 0) {
        $upcomingSql .= " AND s.api_id = ?";
    }
    $upcomingSql .= " ORDER BY e.start_time ASC LIMIT 15";

    $stmtUpcoming = $conn->prepare($upcomingSql);
    if ($sportFilter > 0) {
        $stmtUpcoming->bind_param("ssi", $nowStr, $soon, $sportFilter);
    } else {
        $stmtUpcoming->bind_param("ss", $nowStr, $soon);
    }
    $stmtUpcoming->execute();
    $resUpcoming = $stmtUpcoming->get_result();

    while ($row = $resUpcoming->fetch_assoc()) {
        $dt = new DateTime($row['start_time'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Budapest'));
        $result['upcoming'][] = [
            'apiId'     => (int)$row['api_id'],
            'name'      => $row['name'],
            'startTime' => $dt->format('H:i'),
            'league'    => $row['league'],
            'sportIcon' => getSportIcon((int)$row['sport_api_id'])
        ];
    }
    $stmtUpcoming->close();

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'ticker' => [], 'upcoming' => []], JSON_UNESCAPED_UNICODE);
}
