<?php
/**
 * GET_FINISHED_MATCHES.PHP — Lejátszott/megtörtént meccsek (utolsó 3 nap)
 * 
 * Query: ?sport_id=66 (opcionális)
 * Output: HTML tábla
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

function resolveCompetitionApiIdFinished(mysqli $conn, array $countryCodes, array $leagueNames): int {
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

$from = (new DateTime('-3 days 00:00:00', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
$now  = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$priorityIds = [
    'premier' => resolveCompetitionApiIdFinished($conn, ['ENG', 'GBR'], ['Premier League']),
    'laliga' => resolveCompetitionApiIdFinished($conn, ['ESP'], ['La Liga', 'LaLiga']),
    'serieA' => resolveCompetitionApiIdFinished($conn, ['ITA'], ['Serie A']),
    'bundesliga' => resolveCompetitionApiIdFinished($conn, ['DEU', 'GER'], ['Bundesliga']),
    'ligue1' => resolveCompetitionApiIdFinished($conn, ['FRA'], ['Ligue 1']),
    'fizz' => resolveCompetitionApiIdFinished($conn, [], ['Fizz Liga', 'Fizz League']),
    'nb1' => resolveCompetitionApiIdFinished($conn, ['HUN'], ['NB I', 'NB1', 'OTP Bank Liga']),
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
        WHEN LOWER(TRIM(ch.name)) = 'premier league'
             AND UPPER(TRIM(COALESCE(c.code, ''))) NOT IN ('ENG', 'GBR') THEN 500
        ELSE ({$fallbackPriorityOrder})
    END
";

$naFilter = "
    AND m.api_id IS NOT NULL
    AND m.api_id > 0
    AND LOWER(TRIM(ch.name)) != 'n/a'
    AND TRIM(ch.name) != ''
    AND (c.name IS NULL OR (LOWER(TRIM(c.name)) != 'n/a' AND TRIM(c.name) != ''))
";

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
        s.api_id AS sport_api_id,
        ({$priorityOrder}) AS priority_score
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
        WHERE s.api_id = ?
        AND (
            m.status_id = 3
            OR (m.is_live = 0 AND m.start_time < ?)
            )
      AND m.start_time BETWEEN ? AND ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
      $naFilter
    ORDER BY $priorityOrder, m.start_time DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        exit;
    }
    $stmt->bind_param("isss", $sportId, $now, $from, $now);
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
        s.api_id AS sport_api_id,
        ({$priorityOrder}) AS priority_score
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
        WHERE (
            m.status_id = 3
            OR (m.is_live = 0 AND m.start_time < ?)
            )
      AND m.start_time BETWEEN ? AND ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
      $naFilter
    ORDER BY $priorityOrder, m.start_time DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        exit;
    }
    $stmt->bind_param("sss", $now, $from, $now);
}

$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas fa-flag-checkered" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Nincs lejátszott meccs az elmúlt 3 napban.</div>';
    $stmt->close();
    exit;
}
?>
<?php
// ── Meccsek összegyűjtése bajnokságonként ──
$leagues = [];

$todayBp = (new DateTime('now', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');
$yesterdayBp = (new DateTime('-1 day', new DateTimeZone('Europe/Budapest')))->format('Y-m-d');

while ($row = $res->fetch_assoc()):
    $matchName = trim((string)$row['match_name']);
    if ($matchName === '' || $matchName === '-') continue;

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
    $matchDay = $startUtcDt->format('Y-m-d');
    if ($matchDay === $todayBp) {
        $dayLabel = 'Ma';
    } elseif ($matchDay === $yesterdayBp) {
        $dayLabel = 'Tegnap';
    } else {
        $dayLabel = $startUtcDt->format('m.d.');
    }
    $startFormatted = $startUtcDt->format('H:i');

    $homeScore = $row['home_score'];
    $awayScore = $row['away_score'];
    $scoreDisplay = ($homeScore !== null && $awayScore !== null)
        ? (int)$homeScore . ' - ' . (int)$awayScore
        : 'Nincs adat';

    $apiId = (int)$row['api_id'];

    $competitionApiId = (int)($row['competition_api_id'] ?? 0);
    $countryCode = strtoupper(trim((string)($row['country_code'] ?? '')));
    $priorityScore = (int)($row['priority_score'] ?? 9999);
    $leagueKey = $competitionApiId > 0
        ? ('comp_' . $competitionApiId)
        : ('name_' . mb_strtolower($champName . '|' . $countryCode));

    if (!isset($leagues[$leagueKey])) {
        $leagues[$leagueKey] = [
            'league_name' => $champName ?: 'Egyéb',
            'country' => $countryName ?: 'Nemzetközi',
            'priority_score' => $priorityScore,
            'matches' => [],
        ];
    } elseif ($priorityScore < (int)$leagues[$leagueKey]['priority_score']) {
        $leagues[$leagueKey]['priority_score'] = $priorityScore;
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
    ];
endwhile;
$stmt->close();

if (!empty($leagues)) {
    uasort($leagues, function ($a, $b) {
        $pa = (int)($a['priority_score'] ?? 9999);
        $pb = (int)($b['priority_score'] ?? 9999);
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        $la = mb_strtolower((string)($a['league_name'] ?? ''));
        $lb = mb_strtolower((string)($b['league_name'] ?? ''));
        return $la <=> $lb;
    });
}
?>

<?php if (empty($leagues)): ?>
    <div class="no-matches"><i class="fas fa-flag-checkered" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Nincs lejátszott meccs az elmúlt 3 napban.</div>
<?php else: ?>
    <?php foreach ($leagues as $leagueKey => $leagueData):
        $matches = $leagueData['matches'];
        $matchCount = count($matches);
        $countryDisplay = htmlspecialchars($leagueData['country']);
        $leagueDisplay = htmlspecialchars($leagueData['league_name'] ?? 'Egyéb');
        $leagueId = 'fleague-' . md5($leagueKey);
    ?>
    <div class="league-group" data-league-id="<?php echo $leagueId; ?>">
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
                    <tr class="match-row finished clickable <?php echo $idx === 0 ? 'league-first-match' : 'league-extra-match'; ?>" data-match-id="<?php echo $m['apiId']; ?>">
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
                            <span class="match-score finished-score"><?php echo htmlspecialchars($m['score']); ?></span>
                        </td>
                        <td>
                            <span class="start-time"><?php echo $m['dayLabel'] . ' ' . $m['startFormatted']; ?></span>
                        </td>
                        <td class="live-time-cell">
                            <span class="status-finished"><i class="fas fa-check-circle"></i> Vége</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
