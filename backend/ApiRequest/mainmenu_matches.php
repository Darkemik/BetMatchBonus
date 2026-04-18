<?php
/**
 * MAINMENU_MATCHES.PHP — Főoldal kiemelt meccsek (CSAK DB-ből)
 * 
 * Query: ?sport_id=66 (opcionális)
 * Output: HTML tábla
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;
$gameTag = isset($_GET['game_tag']) ? trim($_GET['game_tag']) : '';
$sortMode = isset($_GET['sort']) && $_GET['sort'] === 'time' ? 'time' : 'priority';
$lang = (isset($_GET['lang']) && strtolower((string)$_GET['lang']) === 'en') ? 'en' : 'hu';

function resolveCompetitionApiId(mysqli $conn, array $countryCodes, array $leagueNames): int {
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

$from = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$to   = (new DateTime('+3 days 23:59:59', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$priorityIds = [
    'premier' => resolveCompetitionApiId($conn, ['ENG', 'GBR'], ['Premier League']),
    'laliga' => resolveCompetitionApiId($conn, ['ESP'], ['La Liga', 'LaLiga']),
    'serieA' => resolveCompetitionApiId($conn, ['ITA'], ['Serie A']),
    'bundesliga' => resolveCompetitionApiId($conn, ['DEU', 'GER'], ['Bundesliga']),
    'ligue1' => resolveCompetitionApiId($conn, ['FRA'], ['Ligue 1']),
    'fizz' => resolveCompetitionApiId($conn, [], ['Fizz Liga', 'Fizz League']),
    'nb1' => resolveCompetitionApiId($conn, ['HUN'], ['NB I', 'NB1', 'OTP Bank Liga']),
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
$orderBy = $sortMode === 'time'
    ? 'm.start_time ASC, ' . $priorityOrder
    : $priorityOrder . ', m.start_time ASC';

$naFilter = "
    AND m.api_id IS NOT NULL
    AND m.api_id > 0
    AND LOWER(TRIM(ch.name)) != 'n/a'
    AND TRIM(ch.name) != ''
    AND (c.name IS NULL OR (LOWER(TRIM(c.name)) != 'n/a' AND TRIM(c.name) != ''))
";

// game_tag szűrés (esport alcímke)
$gameTagFilter = '';
if ($gameTag !== '') {
    if ($gameTag === 'other') {
        $gameTagFilter = " AND (ch.game_tag IS NULL OR ch.game_tag = '')";
    } else {
        $gameTagFilter = " AND ch.game_tag = ?";
    }
}

if ($sportId > 0) {
    $sql = "
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_time AS start_utc,
        m.is_live,
        m.live_time,
        m.home_score,
        m.away_score,
        c.name AS country_name,
        c.code AS country_code,
        ch.name AS championship_name,
        ch.api_id AS competition_api_id,
        s.api_id AS sport_api_id
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE s.api_id = ?
      AND m.start_time BETWEEN ? AND ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
      $naFilter
      $gameTagFilter
    ORDER BY $orderBy
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        exit;
    }
    if ($gameTag !== '' && $gameTag !== 'other') {
        $stmt->bind_param("isss", $sportId, $from, $to, $gameTag);
    } else {
        $stmt->bind_param("iss", $sportId, $from, $to);
    }
} else {
    $sql = "
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_time AS start_utc,
        m.is_live,
        m.live_time,
        m.home_score,
        m.away_score,
        c.name AS country_name,
        c.code AS country_code,
        ch.name AS championship_name,
        ch.api_id AS competition_api_id,
        s.api_id AS sport_api_id
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
      $naFilter
      $gameTagFilter
    ORDER BY $orderBy
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        exit;
    }
    if ($gameTag !== '' && $gameTag !== 'other') {
        $stmt->bind_param("sss", $from, $to, $gameTag);
    } else {
        $stmt->bind_param("ss", $from, $to);
    }
}

$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas fa-calendar-times" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs megjeleníthető meccs.</div>';
    $stmt->close();
    exit;
}
?>
<?php
// ── Meccsek összegyűjtése bajnokságonként ──
$leagues = []; // champName => [ 'country' => ..., 'matches' => [...] ]

$todayBp = (new DateTime('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
$tomorrowBp = (new DateTime('+1 day', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
$nowDt = new DateTime('now', new DateTimeZone('Europe/Budapest'));

while ($row = $res->fetch_assoc()):
    $matchName = trim((string)$row['match_name']);
    if ($matchName === '' || $matchName === '-') continue;

    $liveTimeRaw = $row['live_time'];
    $isLive = (int)$row['is_live'];
    $countryName = $row['country_name'] ?? '';
    $champName   = $row['championship_name'] ?? '';

    // Csapatnév bontás
    $separators = [' vs. ', ' vs ', ' - ', ' – ', ' v '];
    $matchParts = [$matchName];
    foreach ($separators as $sep) {
        if (strpos($matchName, $sep) !== false) {
            $matchParts = explode($sep, $matchName, 2);
            break;
        }
    }
    $home = htmlspecialchars(trim($matchParts[0] ?? ''));
    $away = htmlspecialchars(trim($matchParts[1] ?? ''));
    $showVsFormat = ($home !== '' && $away !== '');

    // UTC → Budapest
    $startUtcDt = new DateTime((string)$row['start_utc'], new DateTimeZone('UTC'));
    $startUtcDt->setTimezone(new DateTimeZone('Europe/Budapest'));
    $startFormatted = $startUtcDt->format('H:i');

    $matchDay = $startUtcDt->format('Y-m-d');
    if ($matchDay === $todayBp) {
        $dayLabel = $lang === 'en' ? 'Today' : 'Ma';
    } elseif ($matchDay === $tomorrowBp) {
        $dayLabel = $lang === 'en' ? 'Tomorrow' : 'Holnap';
    } else {
        $dayLabel = $startUtcDt->format('m.d.');
    }

    // Live idő
    if ($isLive) {
        $invalidLiveTexts = ['nem kezdődött el', 'not started', 'unknown', '-', ''];
        $rawLower = mb_strtolower(trim((string)$liveTimeRaw));
        if ($liveTimeRaw === null || in_array($rawLower, $invalidLiveTexts, true)) {
            $nowUtc = new DateTime('now', new DateTimeZone('UTC'));
            $startCheck = new DateTime((string)$row['start_utc'], new DateTimeZone('UTC'));
            $diffMinutes = max(0, (int)floor(($nowUtc->getTimestamp() - $startCheck->getTimestamp()) / 60));
            $timeDisplay = $diffMinutes > 0 ? $diffMinutes . "'" : 'Élő';
        } else {
            $timeDisplay = htmlspecialchars((string)$liveTimeRaw);
        }
    } else {
        $timeDisplay = '-';
    }

    $homeScore = $row['home_score'];
    $awayScore = $row['away_score'];
    $scoreDisplay = ($homeScore !== null && $awayScore !== null) ? (int)$homeScore . ' - ' . (int)$awayScore : '-';

    $apiId = (int)$row['api_id'];

    $competitionApiId = (int)($row['competition_api_id'] ?? 0);
    $countryCode = strtoupper(trim((string)($row['country_code'] ?? '')));
    $leagueKey = $competitionApiId > 0
        ? ('comp_' . $competitionApiId)
        : ('name_' . mb_strtolower($champName . '|' . $countryCode));
    if (!isset($leagues[$leagueKey])) {
        $leagues[$leagueKey] = [
            'league_name' => $champName ?: 'Egyéb',
            'country' => $countryName ?: 'Nemzetközi',
            'matches' => [],
        ];
    }
    $leagues[$leagueKey]['matches'][] = [
        'apiId' => $apiId,
        'home' => $home,
        'away' => $away,
        'showVs' => $showVsFormat,
        'matchName' => $matchName,
        'score' => $scoreDisplay,
        'dayLabel' => $dayLabel,
        'startFormatted' => $startFormatted,
        'isLive' => $isLive,
        'timeDisplay' => $timeDisplay,
        'startUtcDt' => $startUtcDt,
    ];
endwhile;
$stmt->close();
?>

<?php if (empty($leagues)): ?>
    <div class="no-matches"><i class="fas fa-calendar-times" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs megjeleníthető meccs.</div>
<?php else: ?>
    <?php $leagueRenderIndex = 0; ?>
    <?php foreach ($leagues as $leagueKey => $leagueData):
        $isInitiallyExpanded = $leagueRenderIndex < 3;
        $matches = $leagueData['matches'];
        $matchCount = count($matches);
        $countryDisplay = htmlspecialchars($leagueData['country']);
        $leagueDisplay = htmlspecialchars($leagueData['league_name'] ?? 'Egyéb');
        $leagueId = 'league-' . md5($leagueKey);
    ?>
    <div class="league-group<?php echo $isInitiallyExpanded ? ' expanded' : ''; ?>" data-league-id="<?php echo $leagueId; ?>">
        <div class="league-header" onclick="this.parentElement.classList.toggle('expanded')">
            <div class="league-header-left">
                <i class="fas fa-globe-europe league-country-icon"></i>
                <span class="league-country"><?php echo $countryDisplay; ?></span>
                <span class="league-separator">—</span>
                <i class="fas fa-trophy league-trophy-icon"></i>
                <span class="league-title"><?php echo $leagueDisplay; ?></span>
                <span class="league-match-count"><?php echo $matchCount; ?></span>
            </div>
            <div class="league-header-right">
                <i class="fas fa-chevron-down league-toggle-icon"></i>
            </div>
        </div>
        <div class="league-matches">
            <table class="matches-table">
                <tbody>
                <?php foreach ($matches as $idx => $m): ?>
                    <tr class="match-row clickable <?php echo $idx === 0 ? 'league-first-match' : 'league-extra-match'; ?>" data-match-id="<?php echo $m['apiId']; ?>">
                        <td class="match-cell">
                            <?php if ($m['showVs']): ?>
                                <span class="team home-team"><?php echo $m['home']; ?></span>
                                <span class="vs">vs</span>
                                <span class="team away-team"><?php echo $m['away']; ?></span>
                            <?php else: ?>
                                <span class="team full-match-name"><?php echo htmlspecialchars($m['matchName']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="match-score"><?php echo htmlspecialchars($m['score']); ?></span>
                        </td>
                        <td class="live-time-cell">
                            <?php if ($m['isLive']): ?>
                                <span class="live-dot"></span>
                                <span class="live-time-value"><?php echo $m['timeDisplay']; ?></span>
                            <?php elseif ($m['startUtcDt'] <= $nowDt): ?>
                                <span class="status-upcoming">Hamarosan</span>
                            <?php else: ?>
                                <span class="start-time"><?php echo $m['dayLabel'] . ' ' . $m['startFormatted']; ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php $leagueRenderIndex++; ?>
    <?php endforeach; ?>
<?php endif; ?>