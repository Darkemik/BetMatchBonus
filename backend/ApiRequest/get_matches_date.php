<?php
require_once "connect.php";

$sportId = 67; // pl. Kosárlabda
$date = date('Y-m-d'); // mai nap

$url = "http://localhost:5000/api/matches/date?sportId={$sportId}&date={$date}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if ($response === false) {
    die("cURL hiba: " . curl_error($ch));
}
curl_close($ch);

$data = json_decode($response, true);
if (!is_array($data)) {
    die("API HIBA: nem tömb érkezett.");
}

$stmtFindChamp = $mysqli->prepare("
    SELECT id FROM Championships WHERE api_id = ?
");

$stmtUpsertMatch = $mysqli->prepare("
    INSERT INTO Matches (api_id, sport_id, championship_id, name, start_utc, is_live, live_time)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      sport_id = VALUES(sport_id),
      championship_id = VALUES(championship_id),
      name = VALUES(name),
      start_utc = VALUES(start_utc),
      is_live = VALUES(is_live),
      live_time = VALUES(live_time)
");

foreach ($data as $match) {
    $apiMatchId   = $match['id'];
    $sportId      = $match['sportId'];
    $leagueId     = $match['leagueId'];
    $name         = $match['name'];
    $startUtcStr  = $match['startDateUtc'];
    $isLive       = $match['isLive'] ? 1 : 0;
    $liveTime     = $match['liveTime'] ?? null;

    $stmtFindChamp->bind_param("i", $leagueId);
    $stmtFindChamp->execute();
    $resultChamp = $stmtFindChamp->get_result();
    $champRow = $resultChamp->fetch_assoc();

    if (!$champRow) {
        continue;
    }

    $championshipId = (int)$champRow['id'];

    $dt = new DateTime($startUtcStr);
    $dt->setTimezone(new DateTimeZone('UTC'));
    $startUtcMysql = $dt->format('Y-m-d H:i:s');

    $stmtUpsertMatch->bind_param(
        "iiissis",
        $apiMatchId,
        $sportId,
        $championshipId,
        $name,
        $startUtcMysql,
        $isLive,
        $liveTime
    );
    $stmtUpsertMatch->execute();
}

echo "Napi meccsek importja kész.\n";