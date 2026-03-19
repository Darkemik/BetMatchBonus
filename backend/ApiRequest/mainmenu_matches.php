<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: text/html; charset=utf-8');

$sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;
$today = date('Y-m-d');

$sportIcons = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    78  => 'fa-bullseye',
    83  => 'fa-swimmer',
    73  => 'fa-hand-rock',
    70  => 'fa-hockey-puck',
    145 => 'fa-gamepad',
    77  => 'fa-table-tennis'
];

if ($sportId > 0) {
    $sql = "
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_time AS start_utc,
        m.is_live,
        m.live_time,
        CONCAT(IFNULL(m.home_score, 0), ' - ', IFNULL(m.away_score, 0)) AS score,
        c.name AS country_name,
        ch.name AS championship_name,
        s.api_id AS sport_api_id
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE s.api_id = ?
      AND DATE(m.start_time) = ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
    ORDER BY m.is_live DESC, m.start_time ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        return;
    }
    $stmt->bind_param("is", $sportId, $today);
} else {
    $sql = "
    SELECT 
        m.api_id,
        m.name AS match_name,
        m.start_time AS start_utc,
        m.is_live,
        m.live_time,
        CONCAT(IFNULL(m.home_score, 0), ' - ', IFNULL(m.away_score, 0)) AS score,
        c.name AS country_name,
        ch.name AS championship_name,
        s.api_id AS sport_api_id
    FROM Events m
    JOIN Sports s ON m.sport_id = s.id
    JOIN Competitions ch ON m.competition_id = ch.id
    LEFT JOIN Countries c ON ch.country_id = c.id
    WHERE DATE(m.start_time) = ?
      AND m.name IS NOT NULL
      AND TRIM(m.name) != ''
      AND m.start_time IS NOT NULL
    ORDER BY m.is_live DESC, m.start_time ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
        return;
    }
    $stmt->bind_param("s", $today);
}

$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas fa-calendar-times" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs megjeleníthető meccs.</div>';
    $stmt->close();
    return;
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
            $timeDisplay = ($liveTimeRaw !== null && $liveTimeRaw !== '') ? htmlspecialchars($liveTimeRaw) : '-';

            // Meccs név validáció — kihagyjuk ha üres vagy nem tartalmaz " - " elválasztót
            $matchName = trim($row['match_name']);
            if ($matchName === '' || $matchName === '-') {
                continue;
            }

            $matchParts = explode(' - ', $matchName, 2);
            $home = htmlspecialchars(trim($matchParts[0]));
            $away = isset($matchParts[1]) ? htmlspecialchars(trim($matchParts[1])) : '';

            // Ha az egyik csapatnév üres, kihagyjuk
            if ($home === '' || $away === '') {
                continue;
            }

            $startFormatted = date('H:i', strtotime($row['start_utc']));
            $scoreDisplay = !empty($row['score']) ? htmlspecialchars($row['score']) : '0 - 0';
            $apiId = (int)$row['api_id'];
            $rowSportApiId = (int)$row['sport_api_id'];
            $rowIcon = $sportIcons[$rowSportApiId] ?? 'fa-futbol';
        ?>
            <tr class="match-row clickable" data-match-id="<?php echo $apiId; ?>">
                <td>
                    <span class="country-name"><?php echo htmlspecialchars($row['country_name'] ?? 'N/A'); ?></span>
                </td>
                <td>
                    <span class="league-name"><?php echo htmlspecialchars($row['championship_name']); ?></span>
                </td>
                <td class="match-cell">
                    <span class="team home-team"><?php echo $home; ?></span>
                    <span class="vs">vs</span>
                    <span class="team away-team"><?php echo $away; ?></span>
                </td>
                <td>
                    <span class="match-score"><?php echo $scoreDisplay; ?></span>
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