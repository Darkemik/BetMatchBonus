<?php
/**
 * BOOSTED_MATCH_CACHE.PHP — Napi Oddsűrhajó cache
 * 
 * Naponta egyszer kiszámolja a kiemelt meccset és fájlba menti.
 * Mindkét endpoint (get_boosted_match, get_match_details) ezt használja.
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

function resolveCompetitionApiIdForBoost(mysqli $conn, array $countryCodes, array $leagueNames): int
{
    if (empty($leagueNames)) return 0;

    $leagueConds = [];
    $types = '';
    $params = [];

    foreach ($leagueNames as $name) {
        $leagueConds[] = 'LOWER(TRIM(comp.name)) = ?';
        $types .= 's';
        $params[] = mb_strtolower(trim($name));
    }

    $countrySql = '';
    if (!empty($countryCodes)) {
        $countryConds = [];
        foreach ($countryCodes as $code) {
            $countryConds[] = 'UPPER(TRIM(c.code)) = ?';
            $types .= 's';
            $params[] = strtoupper(trim($code));
        }
        $countrySql = ' AND (' . implode(' OR ', $countryConds) . ')';
    }

    $sql = "
        SELECT comp.api_id
        FROM Competitions comp
        LEFT JOIN Countries c ON comp.country_id = c.id
        WHERE comp.api_id IS NOT NULL
          AND (" . implode(' OR ', $leagueConds) . ")
          {$countrySql}
        ORDER BY comp.id ASC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['api_id'] ?? 0);
}

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

    $strategyVersion = 3;
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

            $sameStrategy = (int)($cached['strategyVersion'] ?? 1) === $strategyVersion;

            if ($cachedEventId > 0 && $hasBoostData && $sameStrategy) {
                return $cached;
            }
        }
    }

    // 2) Jelöltek lekérdezése (fix napi ablak: ma 00:00 UTC → ma 23:59 UTC)
    //    - Csak labdarúgás (sport_api_id=66)
    //    - A főoldali sorrend szerinti első 3 ligatáblából választunk
    $from = (new DateTime('today 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $to   = (new DateTime('today 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

    $priorityIds = [
        'premier' => resolveCompetitionApiIdForBoost($conn, ['ENG', 'GBR'], ['Premier League']),
        'laliga' => resolveCompetitionApiIdForBoost($conn, ['ESP'], ['La Liga', 'LaLiga']),
        'serieA' => resolveCompetitionApiIdForBoost($conn, ['ITA'], ['Serie A']),
        'bundesliga' => resolveCompetitionApiIdForBoost($conn, ['DEU', 'GER'], ['Bundesliga']),
        'ligue1' => resolveCompetitionApiIdForBoost($conn, ['FRA'], ['Ligue 1']),
        'fizz' => resolveCompetitionApiIdForBoost($conn, [], ['Fizz Liga', 'Fizz League']),
        'nb1' => resolveCompetitionApiIdForBoost($conn, ['HUN'], ['NB I', 'NB1', 'OTP Bank Liga']),
    ];

    $fallbackPriorityOrder = str_replace('comp.', 'ch.', LEAGUE_PRIORITY_SQL);
    $priorityOrder = "
        CASE
            WHEN ch.api_id = " . ($priorityIds['premier'] > 0 ? (int)$priorityIds['premier'] : -1) . " THEN 1
            WHEN ch.api_id = " . ($priorityIds['laliga'] > 0 ? (int)$priorityIds['laliga'] : -1) . " THEN 2
            WHEN ch.api_id = " . ($priorityIds['serieA'] > 0 ? (int)$priorityIds['serieA'] : -1) . " THEN 3
            WHEN ch.api_id = " . ($priorityIds['bundesliga'] > 0 ? (int)$priorityIds['bundesliga'] : -1) . " THEN 4
            WHEN ch.api_id = " . ($priorityIds['ligue1'] > 0 ? (int)$priorityIds['ligue1'] : -1) . " THEN 5
            WHEN ch.api_id = " . ($priorityIds['fizz'] > 0 ? (int)$priorityIds['fizz'] : -1) . " THEN 6
            WHEN ch.api_id = " . ($priorityIds['nb1'] > 0 ? (int)$priorityIds['nb1'] : -1) . " THEN 7
            ELSE ({$fallbackPriorityOrder})
        END
    ";

    $sql = "
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_time AS start_utc,
        m.is_live,
        c.name AS country_name,
        c.code AS country_code,
        ch.name AS championship_name,
        ch.api_id AS competition_api_id,
        s.api_id AS sport_api_id,
        ({$priorityOrder}) AS priority_score
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
            AND s.api_id = 66
      AND m.status_id != 3
      AND m.is_live = 0
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.api_id IS NOT NULL
      AND m.api_id > 0
    ORDER BY ({$priorityOrder}) ASC, m.start_time ASC
    LIMIT 200
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ss", $from, $to);
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

    $topLeagueKeys = [];
    $topLeagueKeySet = [];

    foreach ($candidates as $row) {
        $competitionApiId = (int)($row['competition_api_id'] ?? 0);
        $countryCode = strtoupper(trim((string)($row['country_code'] ?? '')));
        $leagueName = trim((string)($row['championship_name'] ?? ''));
        $leagueKey = $competitionApiId > 0
            ? ('comp_' . $competitionApiId)
            : ('name_' . mb_strtolower($leagueName . '|' . $countryCode));

        if (!isset($topLeagueKeySet[$leagueKey])) {
            $topLeagueKeySet[$leagueKey] = true;
            $topLeagueKeys[] = $leagueKey;
            if (count($topLeagueKeys) >= 3) {
                break;
            }
        }
    }

    if (!empty($topLeagueKeySet)) {
        $candidates = array_values(array_filter($candidates, function ($row) use ($topLeagueKeySet) {
            $competitionApiId = (int)($row['competition_api_id'] ?? 0);
            $countryCode = strtoupper(trim((string)($row['country_code'] ?? '')));
            $leagueName = trim((string)($row['championship_name'] ?? ''));
            $leagueKey = $competitionApiId > 0
                ? ('comp_' . $competitionApiId)
                : ('name_' . mb_strtolower($leagueName . '|' . $countryCode));
            return isset($topLeagueKeySet[$leagueKey]);
        }));
    }

    if (empty($candidates)) {
        return null;
    }

    // 3) Napi legfontosabb meccs kiválasztása + fallback ha az API-ból nincs odds
    //    Ha az első legfontosabb meccshez nincs piac/odds, próbáljuk a következő fontosságú napi meccset.
    $maxAttempts = min(count($candidates), 20);

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $selected = $candidates[$attempt];
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
                    $validSelections = array_values(array_filter($targetMarket['selections'], function ($s) {
                        return isset($s['odd']) && is_numeric($s['odd']) && (float)$s['odd'] > 1;
                    }));

                    if (empty($validSelections)) {
                        continue;
                    }

                    // Konzisztens, egyszeru valasztas: az elso ervenyes opcio
                    $sel = $validSelections[0];

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
        'strategyVersion'   => $strategyVersion,
    ];

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    file_put_contents($cacheFile, json_encode($cacheData, JSON_UNESCAPED_UNICODE));

    return $cacheData;
}
