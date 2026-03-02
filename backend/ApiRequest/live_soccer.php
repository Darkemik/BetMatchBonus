<?php
require_once "connect.php";

$sql = "
SELECT 
    m.name AS match_name,
    m.start_utc,
    m.live_time,
    c.name AS country_name,
    ch.name AS championship_name
FROM Matches m
JOIN Championships ch ON m.championship_id = ch.id
JOIN Countries c ON ch.country_code = c.code
WHERE m.sport_id = 66        -- foci
  AND m.is_live = 1          -- élő meccsek
ORDER BY m.start_utc
";

$res = $conn->query($sql);

$matches = [];

while ($row = $res->fetch_assoc()) {
    $matches[] = $row;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($matches);