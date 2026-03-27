<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

// Ugyanaz az ablak, mint a sidebarban
$from = (new DateTime('yesterday 00:00:00'))->format('Y-m-d H:i:s');
$to   = (new DateTime('tomorrow 23:59:59'))->format('Y-m-d H:i:s');

$priorityOrder = "
    CASE
        WHEN ch.name LIKE '%Champions League%'    THEN 1
        WHEN ch.name LIKE '%World Cup%'           THEN 2
        WHEN ch.name LIKE '%Europa League%'       THEN 3
        WHEN ch.name LIKE '%Conference League%'   THEN 4
        WHEN ch.name LIKE '%Premier League%'      THEN 5
        WHEN ch.name LIKE '%La Liga%'             THEN 6
        WHEN ch.name LIKE '%Bundesliga%'          THEN 7
        WHEN ch.name LIKE '%Serie A%'             THEN 8
        WHEN ch.name LIKE '%Ligue 1%'             THEN 9
        WHEN ch.name LIKE '%NB I%'
          OR ch.name LIKE '%NB1%'
          OR ch.name LIKE '%Nemzeti Bajnokság%'   THEN 10
        WHEN ch.name LIKE '%Eredivisie%'          THEN 11
        WHEN ch.name LIKE '%Primeira Liga%'       THEN 12
        ELSE 99
    END ASC
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
        ch.name AS championship_name
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
    ORDER BY m.is_live DESC, $priorityOrder, m.start_time ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        exit;
    }
    $stmt->bind_param("iss", $sportId, $from, $to);
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
        ch.name AS championship_name
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE m.start_time BETWEEN ? AND ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
      $naFilter
    ORDER BY m.is_live DESC, $priorityOrder, m.start_time ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        exit;
    }
    $stmt->bind_param("ss", $from, $to);
}

$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas fa-calendar-times" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs megjeleníthető meccs.</div>';
    $stmt->close();
    exit;
}

// Collect rows into country → championship groups (preserving SQL order)
$groups = [];
while ($row = $res->fetch_assoc()) {
    $matchName = trim((string)$row['match_name']);
    if ($matchName === '' || $matchName === '-') {
        continue;
    }

    $countryName = $row['country_name'] ?? '';
    $champName   = $row['championship_name'] ?? '';

    // Split team names
    $separators = [' vs. ', ' vs ', ' - ', ' – ', ' v '];
    $matchParts = [$matchName];
    foreach ($separators as $sep) {
        if (strpos($matchName, $sep) !== false) {
            $matchParts = explode($sep, $matchName, 2);
            break;
        }
    }
    $home = trim($matchParts[0] ?? '');
    $away = trim($matchParts[1] ?? '');
    $showVsFormat = ($home !== '' && $away !== '');

    $startFormatted = date('H:i', strtotime((string)$row['start_utc']));

    $homeScore = $row['home_score'];
    $awayScore = $row['away_score'];
    $scoreDisplay = ($homeScore !== null && $awayScore !== null)
        ? (int)$homeScore . ' - ' . (int)$awayScore
        : '-';

    $countryKey = $countryName ?: '—';
    $champKey   = $champName ?: '—';

    $groups[$countryKey][$champKey][] = [
        'api_id'       => (int)$row['api_id'],
        'home'         => htmlspecialchars($home),
        'away'         => htmlspecialchars($away),
        'matchName'    => htmlspecialchars($matchName),
        'showVsFormat' => $showVsFormat,
        'scoreDisplay' => htmlspecialchars($scoreDisplay),
        'startFormatted' => $startFormatted,
        'isLive'       => (int)$row['is_live'],
        'timeDisplay'  => ($row['live_time'] !== null && $row['live_time'] !== '')
            ? htmlspecialchars((string)$row['live_time'])
            : '-',
        'country'      => htmlspecialchars($countryKey),
        'league'       => htmlspecialchars($champKey),
    ];
}
$stmt->close();

if (empty($groups)) {
    echo '<div class="no-matches"><i class="fas fa-calendar-times" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs megjeleníthető meccs.</div>';
    exit;
}
?>
<div class="matches-grouped">
<?php foreach ($groups as $country => $championships):
    $countryMatchCount = 0;
    foreach ($championships as $champMatches) {
        $countryMatchCount += count($champMatches);
    }
?>
    <div class="country-group">
        <div class="country-header">
            <i class="fas fa-globe-europe"></i>
            <span class="country-name"><?php echo htmlspecialchars($country); ?></span>
            <span class="country-match-count"><?php echo $countryMatchCount; ?></span>
            <i class="fas fa-chevron-down country-chevron"></i>
        </div>
        <div class="country-content">
        <?php foreach ($championships as $championship => $matches): ?>
            <div class="league-group">
                <div class="league-header">
                    <i class="fas fa-trophy"></i>
                    <span class="league-name"><?php echo htmlspecialchars($championship); ?></span>
                </div>
                <div class="league-matches">
                <?php foreach ($matches as $m): ?>
                    <div class="match-row clickable"
                         data-match-id="<?php echo $m['api_id']; ?>"
                         data-country="<?php echo $m['country']; ?>"
                         data-league="<?php echo $m['league']; ?>">
                        <span class="match-teams">
                        <?php if ($m['showVsFormat']): ?>
                            <span class="team home-team"><?php echo $m['home']; ?></span>
                            <span class="vs">vs</span>
                            <span class="team away-team"><?php echo $m['away']; ?></span>
                        <?php else: ?>
                            <span class="team full-match-name"><?php echo $m['matchName']; ?></span>
                        <?php endif; ?>
                        </span>
                        <span class="match-score"><?php echo $m['scoreDisplay']; ?></span>
                        <span class="start-time"><?php echo $m['startFormatted']; ?></span>
                        <span class="live-time-cell">
                        <?php if ($m['isLive']): ?>
                            <span class="live-dot"></span>
                            <span class="live-time-value"><?php echo $m['timeDisplay']; ?></span>
                        <?php else: ?>
                            <span class="status-upcoming"><i class="fas fa-clock"></i> <?php echo $m['startFormatted']; ?></span>
                        <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>