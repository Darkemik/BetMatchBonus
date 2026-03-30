<?php
require_once dirname(__DIR__) . "/connect.php";

header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;

// Ugyanaz az ablak, mint a sidebarban
$from = (new DateTime('yesterday 00:00:00'))->format('Y-m-d H:i:s');
$to   = (new DateTime('tomorrow 23:59:59'))->format('Y-m-d H:i:s');

$sportIcons = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    78  => 'fa-bullseye',
    83  => 'fa-swimmer',
    73  => 'fa-hand-rock',
    70  => 'fa-hockey-puck',
    145 => 'fa-gamepad',
    77  => 'fa-table-tennis',
    76  => 'fa-running',
    90  => 'fa-hockey-puck',
    68  => 'fa-baseball-ball',
    69  => 'fa-football-ball',
    71  => 'fa-volleyball-ball',
    72  => 'fa-golf-ball',
    74  => 'fa-fist-raised',
    75  => 'fa-biking',
    79  => 'fa-skiing',
    80  => 'fa-snowflake',
    84  => 'fa-table-tennis',
    85  => 'fa-chess',
    109 => 'fa-volleyball-ball',
    110 => 'fa-futbol',
    138 => 'fa-running',
    151 => 'fa-trophy',
];

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
        ch.name AS championship_name,
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
        ch.name AS championship_name,
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
?>
<table class="matches-table">
    <thead>
        <tr>
            <th><i class="fas fa-globe-europe"></i> Ország</th>
            <th><i class="fas fa-trophy"></i> Bajnokság</th>
            <th><i class="fas fa-futbol"></i> Meccs</th>
            <th><i class="fas fa-star"></i> Állás</th>
            <th><i class="fas fa-clock"></i> Kezdés</th>
            <th><i class="fas fa-stopwatch"></i> Státusz</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $res->fetch_assoc()):
            $liveTimeRaw = $row['live_time'];
            $isLive = (int)$row['is_live'];
            $timeDisplay = ($liveTimeRaw !== null && $liveTimeRaw !== '') ? htmlspecialchars((string)$liveTimeRaw) : '-';

            $matchName = trim((string)$row['match_name']);
            if ($matchName === '' || $matchName === '-') {
                continue;
            }

            $countryName = $row['country_name'] ?? '';
            $champName   = $row['championship_name'] ?? '';

            // Rugalmas csapatnév bontás
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

            // Ha nem bontható biztonságosan, ne dobjuk el: teljes név jelenjen meg
            $showVsFormat = ($home !== '' && $away !== '');

            $startFormatted = date('H:i', strtotime((string)$row['start_utc']));

            $homeScore = $row['home_score'];
            $awayScore = $row['away_score'];
            if ($homeScore !== null && $awayScore !== null) {
                $scoreDisplay = (int)$homeScore . ' - ' . (int)$awayScore;
            } else {
                $scoreDisplay = '-';
            }

            $apiId = (int)$row['api_id'];
            $rowSportApiId = (int)$row['sport_api_id'];
            $rowIcon = $sportIcons[$rowSportApiId] ?? 'fa-futbol';
        ?>
            <tr class="match-row clickable" data-match-id="<?php echo $apiId; ?>">
                <td>
                    <span class="country-name"><?php echo htmlspecialchars($countryName ?: '—'); ?></span>
                </td>
                <td>
                    <span class="league-name"><?php echo htmlspecialchars($champName); ?></span>
                </td>
                <td class="match-cell">
                    <?php if ($showVsFormat): ?>
                        <span class="team home-team"><?php echo $home; ?></span>
                        <span class="vs">vs</span>
                        <span class="team away-team"><?php echo $away; ?></span>
                    <?php else: ?>
                        <span class="team full-match-name"><?php echo htmlspecialchars($matchName); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="match-score"><?php echo htmlspecialchars($scoreDisplay); ?></span>
                </td>
                <td>
                    <span class="start-time"><?php echo $startFormatted; ?></span>
                </td>
                <td class="live-time-cell">
                    <?php if ($isLive): ?>
                        <span class="live-dot"></span>
                        <span class="live-time-value"><?php echo $timeDisplay; ?></span>
                    <?php else: ?>
                        <span class="status-upcoming"><i class="fas fa-clock"></i> <?php echo $startFormatted; ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php $stmt->close(); ?>