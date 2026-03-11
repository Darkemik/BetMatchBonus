<?php
require_once __DIR__ . "/connect.php";

// Sport ID paraméter (alapból foci = 66)
$sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 66;

// Sport ikonok mapping (API ID-k alapján)
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

$sportIcon = isset($sportIcons[$sportId]) ? $sportIcons[$sportId] : 'fa-futbol';

$sql = "
SELECT 
    m.api_id,
    m.name AS match_name,
    m.start_utc,
    m.live_time,
    m.score,
    c.name AS country_name,
    ch.name AS championship_name
FROM Matches m
JOIN Championships ch ON m.championship_id = ch.id
JOIN Countries c ON ch.country_code = c.code
WHERE m.sport_id = ?
  AND m.is_live = 1
ORDER BY m.start_utc
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo '<div class="no-matches">Hiba az adatbázis lekérdezésnél.</div>';
    return;
}
$stmt->bind_param("i", $sportId);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    echo '<div class="no-matches"><i class="fas ' . $sportIcon . '" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs élő mérkőzés ebben a sportágban.</div>';
    $stmt->close();
    return;
}
?>
<table class="matches-table">
    <thead>
        <tr>
            <th><i class="fas fa-globe-europe"></i> Ország</th>
            <th><i class="fas fa-trophy"></i> Bajnokság</th>
            <th><i class="fas <?php echo $sportIcon; ?>"></i> Meccs</th>
            <th><i class="fas fa-star"></i> Állás</th>
            <th><i class="fas fa-clock"></i> Kezdés</th>
            <th><i class="fas fa-stopwatch"></i> Élő idő</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $res->fetch_assoc()):
            $liveTimeRaw = $row['live_time'];
            $timeDisplay = ($liveTimeRaw !== null && $liveTimeRaw !== '') ? htmlspecialchars($liveTimeRaw) : '-';

            $matchParts = explode(' - ', $row['match_name'], 2);
            $home = htmlspecialchars($matchParts[0]);
            $away = isset($matchParts[1]) ? htmlspecialchars($matchParts[1]) : '';

            $startFormatted = date('H:i', strtotime($row['start_utc']));
            $scoreDisplay = !empty($row['score']) ? htmlspecialchars($row['score']) : '0 - 0';
            $apiId = (int)$row['api_id'];
        ?>
            <tr class="match-row clickable" data-match-id="<?php echo $apiId; ?>">
                <td>
                    <span class="country-name"><?php echo htmlspecialchars($row['country_name']); ?></span>
                </td>
                <td>
                    <span class="league-name"><?php echo htmlspecialchars($row['championship_name']); ?></span>
                </td>
                <td class="match-cell">
                    <?php if ($away !== ''): ?>
                        <span class="team home-team"><?php echo $home; ?></span>
                        <span class="vs">vs</span>
                        <span class="team away-team"><?php echo $away; ?></span>
                    <?php else: ?>
                        <span class="team"><?php echo $home; ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="match-score"><?php echo $scoreDisplay; ?></span>
                </td>
                <td>
                    <span class="start-time"><?php echo $startFormatted; ?></span>
                </td>
                <td class="live-time-cell">
                    <span class="live-dot"></span>
                    <span class="live-time-value"><?php echo $timeDisplay; ?></span>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php $stmt->close(); ?>