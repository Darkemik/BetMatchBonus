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
    echo json_encode(['success' => false, 'error' => 'Nincs elérhető meccs'], JSON_UNESCAPED_UNICODE);
    exit;
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
    'eventId'          => $cached['eventId'],
    'matchName'        => $matchName,
    'homeTeam'         => $homeTeam,
    'awayTeam'         => $awayTeam,
    'country'          => $cached['country'] ?? 'Nemzetközi',
    'championship'     => $cached['championship'] ?? '',
    'startTime'        => $startFormatted,
    'sportApiId'       => $cached['sportApiId'] ?? 0,
    'boostedMarket'    => $cached['boostedMarket'],
    'boostedSelection' => $cached['boostedSelection'],
    'originalOdd'      => $cached['originalOdd'],
    'boostedOdd'       => $cached['boostedOdd'],
    'boostMultiplier'  => 1.5,
    'date'             => $cached['date'],
], JSON_UNESCAPED_UNICODE);
