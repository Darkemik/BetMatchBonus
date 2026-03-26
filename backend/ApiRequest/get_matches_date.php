<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

try {
    $date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])
        ? $_GET['date']
        : date('Y-m-d');

    $sportApiId = isset($_GET['sportId']) ? (int)$_GET['sportId'] : 0;

    $from = $date . " 00:00:00";
    $to   = $date . " 23:59:59";

    $sql = "
        SELECT
            e.api_id,
            e.name,
            e.start_time,
            e.is_live,
            e.live_time,
            e.home_score,
            e.away_score,
            s.api_id AS sport_api_id,
            s.name   AS sport_name,
            c.api_id AS league_api_id,
            c.name   AS league_name,
            COALESCE(cn.code, 'INT')          AS country_code,
            COALESCE(cn.name, 'Nemzetközi')   AS country_name
        FROM Events e
        INNER JOIN Sports s       ON s.id = e.sport_id
        INNER JOIN Competitions c ON c.id = e.competition_id
        LEFT JOIN Countries cn    ON cn.id = c.country_id
        WHERE e.start_time BETWEEN ? AND ?
    ";

    if ($sportApiId > 0) {
        $sql .= " AND s.api_id = ? ";
    }

    $sql .= " ORDER BY e.start_time ASC, e.id ASC ";

    $stmt = $conn->prepare($sql);

    if ($sportApiId > 0) {
        $stmt->bind_param("ssi", $from, $to, $sportApiId);
    } else {
        $stmt->bind_param("ss", $from, $to);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id'            => (int)$row['api_id'],
            'name'          => $row['name'],
            'startDateUtc'  => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['start_time'])),
            'isLive'        => (bool)$row['is_live'],
            'liveTime'      => $row['live_time'],
            'score'         => [$row['home_score'] !== null ? (int)$row['home_score'] : null, $row['away_score'] !== null ? (int)$row['away_score'] : null],
            'sportId'       => (int)$row['sport_api_id'],
            'sportName'     => $row['sport_name'],
            'leagueId'      => (int)$row['league_api_id'],
            'leagueName'    => $row['league_name'],
            'countryCode'   => $row['country_code'],
            'countryName'   => $row['country_name'],
        ];
    }

    $stmt->close();
    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}