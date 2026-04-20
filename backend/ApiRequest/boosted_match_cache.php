<?php
/**
 * BOOSTED_MATCH_CACHE.PHP — Napi Oddsűrhajó cache
 * 
 * Naponta egyszer kiszámolja a kiemelt meccset és fájlba menti.
 * Mindkét endpoint (get_boosted_match, get_match_details) ezt használja.
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

function isPreferredBoostMarketName(string $marketName): bool
{
    $name = mb_strtolower($marketName);
    return strpos($name, 'győztes') !== false
        || strpos($name, 'winner') !== false
        || strpos($name, '1x2') !== false
        || strpos($name, 'végeredmény') !== false
        || strpos($name, 'match result') !== false;
}

/**
 * Kiválaszt egy boostolható market/selection párost normalizált market tömbből.
 *
 * Elvárt market forma:
 * [
 *   ['name' => '...', 'selections' => [['name'=>'...', 'odd'=>1.95], ...]],
 *   ...
 * ]
 */
function resolveBoostFromMarkets(array $markets): ?array
{
    if (empty($markets)) return null;

    $preferredMarket = null;
    $fallbackMarket = null;

    foreach ($markets as $market) {
        if (!is_array($market)) continue;

        $marketName = (string)($market['name'] ?? '');
        $selections = $market['selections'] ?? [];
        if (!is_array($selections) || empty($selections)) continue;

        $validSelections = array_values(array_filter($selections, function ($selection) {
            return isset($selection['odd'])
                && is_numeric($selection['odd'])
                && (float)$selection['odd'] > 1
                && trim((string)($selection['name'] ?? '')) !== '';
        }));

        if (empty($validSelections)) continue;

        $candidate = [
            'name' => $marketName,
            'selections' => $validSelections,
        ];

        if ($fallbackMarket === null) {
            $fallbackMarket = $candidate;
        }

        if (isPreferredBoostMarketName($marketName)) {
            $preferredMarket = $candidate;
            break;
        }
    }

    $targetMarket = $preferredMarket ?? $fallbackMarket;
    if ($targetMarket === null || empty($targetMarket['selections'])) {
        return null;
    }

    $selection = $targetMarket['selections'][0];
    $originalOdd = round((float)$selection['odd'], 2);
    $boostedOdd = round($originalOdd * 1.5, 2);

    if ($boostedOdd <= 1) {
        return null;
    }

    return [
        'market' => (string)$targetMarket['name'],
        'selection' => (string)$selection['name'],
        'originalOdd' => $originalOdd,
        'boostedOdd' => $boostedOdd,
    ];
}

/**
 * DB fallback: EventMarkets + OddsOutcomes alapján próbál boostot választani.
 */
function resolveBoostFromDb(mysqli $conn, int $eventApiId): ?array
{
    $sql = "
        SELECT em.name AS market_name, oo.label AS selection_name, oo.odds
        FROM Events e
        JOIN EventMarkets em ON em.event_id = e.id
        JOIN OddsOutcomes oo ON oo.event_market_id = em.id
        WHERE e.api_id = ?
          AND em.name IS NOT NULL
          AND TRIM(em.name) != ''
          AND oo.label IS NOT NULL
          AND TRIM(oo.label) != ''
          AND oo.odds IS NOT NULL
          AND oo.odds > 1
        ORDER BY em.id ASC, oo.role ASC, oo.id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $eventApiId);
    $stmt->execute();
    $res = $stmt->get_result();

    $marketMap = [];
    while ($row = $res->fetch_assoc()) {
        $marketName = (string)($row['market_name'] ?? '');
        $selectionName = (string)($row['selection_name'] ?? '');
        $oddValue = (float)($row['odds'] ?? 0);
        if ($marketName === '' || $selectionName === '' || $oddValue <= 1) {
            continue;
        }

        if (!isset($marketMap[$marketName])) {
            $marketMap[$marketName] = [
                'name' => $marketName,
                'selections' => [],
            ];
        }

        $marketMap[$marketName]['selections'][] = [
            'name' => $selectionName,
            'odd' => $oddValue,
        ];
    }
    $stmt->close();

    if (empty($marketMap)) {
        return null;
    }

    return resolveBoostFromMarkets(array_values($marketMap));
}

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
    $bpNow = new DateTime('now', new DateTimeZone('Europe/Budapest'));
    $today = $bpNow->format('Y-m-d');
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

    // 2) Jelöltek lekérdezése (fix napi ablak: budapesti nap 00:00 → 23:59)
    //    - Csak labdarúgás (sport_api_id=66)
    //    - A főoldali sorrend szerinti első 3 ligatáblából választunk
    $fromBp = new DateTime($today . ' 00:00:00', new DateTimeZone('Europe/Budapest'));
    $toBp = new DateTime($today . ' 23:59:59', new DateTimeZone('Europe/Budapest'));
    $fromBp->setTimezone(new DateTimeZone('UTC'));
    $toBp->setTimezone(new DateTimeZone('UTC'));
    $from = $fromBp->format('Y-m-d H:i:s');
    $to   = $toBp->format('Y-m-d H:i:s');

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

    $allCandidates = $candidates;

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

            $resolvedBoost = resolveBoostFromMarkets((array)($apiData['markets'] ?? []));
            if ($resolvedBoost) {
                $boostedMarket = $resolvedBoost['market'];
                $boostedSelection = $resolvedBoost['selection'];
                $originalOdd = $resolvedBoost['originalOdd'];
                $boostedOdd = $resolvedBoost['boostedOdd'];
            }
        } catch (Throwable $e) {
            error_log("Oddsűrhajó cache API hiba (eventId=$eventId): " . $e->getMessage());
        }

        // Fallback: ha API oldalról nincs használható market, próbáljuk a DB-t.
        if ($boostedMarket === null || $boostedOdd === null || $boostedOdd <= 1) {
            $dbResolvedBoost = resolveBoostFromDb($conn, $eventId);
            if ($dbResolvedBoost) {
                $boostedMarket = $dbResolvedBoost['market'];
                $boostedSelection = $dbResolvedBoost['selection'];
                $originalOdd = $dbResolvedBoost['originalOdd'];
                $boostedOdd = $dbResolvedBoost['boostedOdd'];
            }
        }

        // Ha sikerült odds-ot kapni, kilépünk a ciklusból
        if ($boostedMarket !== null && $boostedOdd !== null && $boostedOdd > 1) {
            break;
        }

        // Nem sikerült, próbáljuk a következő jelöltet
        error_log("Oddsűrhajó: eventId=$eventId nem adott vissza odds-ot, következő jelölt...");
    }

    // Második kör: ha a top3 liga szűrésben nem találtunk boostot,
    // próbáljuk meg a teljes napi jelöltlistát is.
    if (($boostedMarket === null || $boostedOdd === null || $boostedOdd <= 1)
        && count($allCandidates) > count($candidates)) {
        $maxAttemptsAll = min(count($allCandidates), 50);

        for ($attempt = 0; $attempt < $maxAttemptsAll; $attempt++) {
            $selected = $allCandidates[$attempt];
            $eventId = (int)$selected['api_id'];

            $boostedMarket = null;
            $boostedSelection = null;
            $originalOdd = null;
            $boostedOdd = null;

            try {
                $apiData = apiGet(EP_MATCH_DETAILS . '/' . $eventId);

                $resolvedBoost = resolveBoostFromMarkets((array)($apiData['markets'] ?? []));
                if ($resolvedBoost) {
                    $boostedMarket = $resolvedBoost['market'];
                    $boostedSelection = $resolvedBoost['selection'];
                    $originalOdd = $resolvedBoost['originalOdd'];
                    $boostedOdd = $resolvedBoost['boostedOdd'];
                }
            } catch (Throwable $e) {
                error_log("Oddsűrhajó cache API hiba (2. kör, eventId=$eventId): " . $e->getMessage());
            }

            if ($boostedMarket === null || $boostedOdd === null || $boostedOdd <= 1) {
                $dbResolvedBoost = resolveBoostFromDb($conn, $eventId);
                if ($dbResolvedBoost) {
                    $boostedMarket = $dbResolvedBoost['market'];
                    $boostedSelection = $dbResolvedBoost['selection'];
                    $originalOdd = $dbResolvedBoost['originalOdd'];
                    $boostedOdd = $dbResolvedBoost['boostedOdd'];
                }
            }

            if ($boostedMarket !== null && $boostedOdd !== null && $boostedOdd > 1) {
                break;
            }
        }
    }

    // Ha egyetlen jelölt sem adott odds-ot, ne mentsünk üres cache-t
    if ($boostedMarket === null || $boostedOdd === null || $boostedOdd <= 1) {
        error_log("Oddsűrhajó: Egyik jelölt sem adott vissza érvényes odds-ot (top3: $maxAttempts próba, teljes lista fallback is lefutott)");
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
