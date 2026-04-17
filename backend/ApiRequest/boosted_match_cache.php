<?php
/**
 * BOOSTED_MATCH_CACHE.PHP — Napi Oddsűrhajó cache
 * 
 * Naponta egyszer kiszámolja a kiemelt meccset és fájlba menti.
 * Mindkét endpoint (get_boosted_match, get_match_details) ezt használja.
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

/**
 * Visszaadja a mai napi boosted meccs adatait.
 * Ha még nincs cache (vagy más napé), újraszámolja.
 *
 * FONTOS: ugyanazon a napon nem váltunk új meccsre akkor sem,
 * ha a napi Oddsűrhajó meccs időközben befejeződött.
 *
 * @return array|null  ['eventId'=>int, 'marketName'=>string, 'selectionName'=>string, 'date'=>string] vagy null
 */
function getDailyBoostedMatch(): ?array
{
    global $conn;

    $today = date('Y-m-d');
    $cacheDir  = __DIR__ . '/../uploads';
    $cacheFile = $cacheDir . '/boosted_cache.json';

    // 1) Cache olvasás: ha mai napra van és valid boost adatot tartalmaz, rögtön visszaadjuk
    //    (nem rotálunk új meccsre nap közben)
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && ($cached['date'] ?? '') === $today) {
            $cachedEventId = (int)($cached['eventId'] ?? 0);
            $hasBoostData =
                !empty($cached['boostedMarket']) &&
                !empty($cached['boostedSelection']) &&
                isset($cached['originalOdd']) &&
                isset($cached['boostedOdd']) &&
                is_numeric($cached['originalOdd']) &&
                is_numeric($cached['boostedOdd']) &&
                (float)$cached['boostedOdd'] > 1;

            if ($cachedEventId > 0 && $hasBoostData) {
                return $cached;
            }
        }
    }

    // 2) Jelöltek lekérdezése (fix napi ablak: ma 00:00 UTC → +3 nap)
    //    - Csak főoldali sportok (kizárjuk: 145=E-sportok, 146=e-Labdarúgás, 147=e-Kosárlabda, 148=e-Jégkorong)
    //    - Csak a TOP 3 bajnokság (fontossági sorrend szerint, amiknek van meccsük)
    $from = (new DateTime('today 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $to   = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $priorityOrder = str_replace('comp.', 'ch.', LEAGUE_PRIORITY_SQL);

    // Először megkeressük a top 3 bajnokságot fontossági sorrendben
    $topChampsSql = "
    SELECT DISTINCT ch.id AS comp_id, ch.name AS comp_name, ({$priorityOrder}) AS prio
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
      AND m.status_id != 3
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.api_id IS NOT NULL
      AND m.api_id > 0
      AND s.api_id NOT IN (145, 146, 147, 148)
      AND ({$priorityOrder}) < 99
    ORDER BY prio ASC
    LIMIT 3
    ";

    $stmtTop = $conn->prepare($topChampsSql);
    if (!$stmtTop) {
        return null;
    }
    $stmtTop->bind_param("ss", $from, $to);
    $stmtTop->execute();
    $topRes = $stmtTop->get_result();

    $topCompIds = [];
    while ($row = $topRes->fetch_assoc()) {
        $topCompIds[] = (int)$row['comp_id'];
    }
    $stmtTop->close();

    if (empty($topCompIds)) {
        return null;
    }

    // Meccsek lekérdezése csak a top 3 bajnokságból
    $inPlaceholders = implode(',', array_fill(0, count($topCompIds), '?'));
    $sql = "
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_time AS start_utc,
        m.is_live,
        c.name AS country_name,
        ch.name AS championship_name,
        s.api_id AS sport_api_id
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
      AND m.status_id != 3
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.api_id IS NOT NULL
      AND m.api_id > 0
      AND m.competition_id IN ({$inPlaceholders})
    ORDER BY {$priorityOrder}, m.start_time ASC
    LIMIT 50
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $types = "ss" . str_repeat('i', count($topCompIds));
    $params = array_merge([$from, $to], $topCompIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $candidates = [];
    while ($row = $res->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();

    if (empty($candidates)) {
        return null;
    }

    // 3) Determinisztikus kiválasztás + fallback ha az API-ból nincs odds
    //    Ha a kiválasztott meccsre az API nem ad vissza piacot, próbáljuk a következőt
    $dayHash = crc32($today . 'oddsboost');
    $totalCandidates = count($candidates);
    $startIndex = abs($dayHash) % $totalCandidates;
    $maxAttempts = min($totalCandidates, 10); // Max 10 próba

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $selectedIndex = ($startIndex + $attempt) % $totalCandidates;
        $selected = $candidates[$selectedIndex];
        $eventId = (int)$selected['api_id'];

        // 4) Odds lekérése az API-ból → piac és selection kiválasztása
        $boostedMarket = null;
        $boostedSelection = null;
        $originalOdd = null;
        $boostedOdd = null;

        try {
            $apiData = apiGet(EP_MATCH_DETAILS . '/' . $eventId);

            if (isset($apiData['markets']) && is_array($apiData['markets']) && count($apiData['markets']) > 0) {
                $targetMarket = null;
                foreach ($apiData['markets'] as $market) {
                    $mName = mb_strtolower($market['name'] ?? '');
                    $sels = $market['selections'] ?? [];
                    if (count($sels) < 2) continue;

                    if (strpos($mName, 'győztes') !== false ||
                        strpos($mName, 'winner') !== false ||
                        strpos($mName, '1x2') !== false ||
                        strpos($mName, 'végeredmény') !== false ||
                        strpos($mName, 'match result') !== false) {
                        $targetMarket = $market;
                        break;
                    }
                    if ($targetMarket === null) {
                        $targetMarket = $market;
                    }
                }

                if ($targetMarket && !empty($targetMarket['selections'])) {
                    $selCount = count($targetMarket['selections']);
                    $selIndex = abs(crc32($today . 'sel')) % $selCount;
                    $sel = $targetMarket['selections'][$selIndex];

                    $boostedMarket = $targetMarket['name'];
                    $boostedSelection = $sel['name'] ?? '';
                    $originalOdd = round((float)($sel['odd'] ?? 1.0), 2);
                    $boostedOdd = round($originalOdd * 1.5, 2);
                }
            }
        } catch (Throwable $e) {
            error_log("Oddsűrhajó cache API hiba (eventId=$eventId): " . $e->getMessage());
        }

        // Ha sikerült odds-ot kapni, kilépünk a ciklusból
        if ($boostedMarket !== null && $boostedOdd !== null && $boostedOdd > 1) {
            break;
        }

        // Nem sikerült, próbáljuk a következő jelöltet
        error_log("Oddsűrhajó: eventId=$eventId nem adott vissza odds-ot, következő jelölt...");
    }

    // Ha egyetlen jelölt sem adott odds-ot, ne mentsünk üres cache-t
    if ($boostedMarket === null || $boostedOdd === null || $boostedOdd <= 1) {
        error_log("Oddsűrhajó: Egyik jelölt sem adott vissza érvényes odds-ot ($maxAttempts próba)");
        return null;
    }

    // 5) Cache mentése
    $cacheData = [
        'date'              => $today,
        'eventId'           => $eventId,
        'matchName'         => $selected['match_name'],
        'startUtc'          => $selected['start_utc'],
        'isLive'            => (int)($selected['is_live'] ?? 0),
        'country'           => $selected['country_name'] ?: 'Nemzetközi',
        'championship'      => $selected['championship_name'],
        'sportApiId'        => (int)$selected['sport_api_id'],
        'boostedMarket'     => $boostedMarket,
        'boostedSelection'  => $boostedSelection,
        'originalOdd'       => $originalOdd,
        'boostedOdd'        => $boostedOdd,
        'boostMultiplier'   => 1.5,
    ];

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE));

    return $cacheData;
}
