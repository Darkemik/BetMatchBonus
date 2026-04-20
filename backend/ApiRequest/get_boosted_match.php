<?php
/**
 * GET_BOOSTED_MATCH.PHP — Napi "Oddsűrhajó" kiemelt meccs
 * 
 * Naponta determinisztikusan kiválaszt 1 meccset a főbb bajnokságokból,
 * és az első piac egyik odds-ára 1.5x szorzót alkalmaz.
 * A napi kiválasztást cache-ből olvassa (boosted_match_cache.php).
 * 
 * Output: JSON { success, eventId, matchName, ... }
 */

require_once dirname(__DIR__) . "/connect.php";
require_once dirname(__DIR__) . "/config.php";
require_once __DIR__ . '/boosted_match_cache.php';

header('Content-Type: application/json; charset=utf-8');

$cached = getDailyBoostedMatch();

if (!$cached || empty($cached['eventId'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Nincs elérhető meccs, mert jelenleg nincs használható odds adat (API/DB).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$bpNow = new DateTime('now', new DateTimeZone('Europe/Budapest'));
$nextBoostBp = new DateTime('tomorrow 00:00:00', new DateTimeZone('Europe/Budapest'));
$secondsUntilNextBoost = max(0, $nextBoostBp->getTimestamp() - $bpNow->getTimestamp());

$eventId = (int)$cached['eventId'];
$statusStmt = $conn->prepare("SELECT status_id, is_live, start_time FROM Events WHERE api_id = ? LIMIT 1");
if ($statusStmt) {
    $statusStmt->bind_param('i', $eventId);
    $statusStmt->execute();
    $eventRow = $statusStmt->get_result()->fetch_assoc();
    $statusStmt->close();

    $isLive = (int)($eventRow['is_live'] ?? 0) === 1;
    $isFinished = (int)($eventRow['status_id'] ?? 0) === 3;

    // Ha a mai Oddsűrhajó meccs élő vagy már véget ért, ma nem váltunk újra.
    if ($eventRow && ($isLive || $isFinished)) {
        echo json_encode([
            'success' => false,
            'reason' => 'today_closed',
            'error' => 'A mai Oddsűrhajó már lezárult.',
            'eventId' => $eventId,
            'secondsUntilNextBoost' => $secondsUntilNextBoost,
            'nextBoostAt' => $nextBoostBp->format(DateTime::ATOM)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Csapatnevek bontása
$matchName = $cached['matchName'] ?? '';
$homeTeam = '';
$awayTeam = '';
$separators = [' vs. ', ' vs ', ' - ', ' – '];
foreach ($separators as $sep) {
    if (strpos($matchName, $sep) !== false) {
        $parts = explode($sep, $matchName, 2);
        $homeTeam = trim($parts[0]);
        $awayTeam = trim($parts[1]);
        break;
    }
}

// Időformázás (UTC → Budapest)
$startFormatted = '';
if (!empty($cached['startUtc'])) {
    $startUtcDt = new DateTime($cached['startUtc'], new DateTimeZone('UTC'));
    $startUtcDt->setTimezone(new DateTimeZone('Europe/Budapest'));
    $startFormatted = $startUtcDt->format('m.d. H:i');
}

echo json_encode([
    'success'          => true,
    'eventId'          => $eventId,
    'matchName'        => $matchName,
    'homeTeam'         => $homeTeam,
    'awayTeam'         => $awayTeam,
    'country'          => $cached['country'] ?? 'Nemzetközi',
    'championship'     => $cached['championship'] ?? '',
    'startTime'        => $startFormatted,
    'isLive'           => (int)($cached['isLive'] ?? 0),
    'sportApiId'       => $cached['sportApiId'] ?? 0,
    'boostedMarket'    => $cached['boostedMarket'],
    'boostedSelection' => $cached['boostedSelection'],
    'originalOdd'      => $cached['originalOdd'],
    'boostedOdd'       => $cached['boostedOdd'],
    'boostMultiplier'  => 1.5,
    'date'             => $cached['date'],
    'secondsUntilNextBoost' => $secondsUntilNextBoost,
    'nextBoostAt'      => $nextBoostBp->format(DateTime::ATOM),
], JSON_UNESCAPED_UNICODE);
