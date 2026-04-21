<?php
/**
 * EVENTMARKETS HEALTH CHECK
 *
 * Használat:
 *   - Include-ból: runEventMarketsHealthCheck($conn, true);
 *   - CLI: php backend/DataBase/check_eventmarkets_health.php
 *   - HTTP: /backend/DataBase/check_eventmarkets_health.php
 */

date_default_timezone_set('Europe/Budapest');

if (!function_exists('decodeEventMarketsJsonOutput')) {
    function decodeEventMarketsJsonOutput(string $output): array
    {
        $output = trim($output);
        if ($output === '') {
            throw new RuntimeException('Üres sync kimenet.');
        }

        $json = json_decode($output, true);
        if (is_array($json)) {
            return $json;
        }

        $firstBrace = strpos($output, '{');
        $lastBrace = strrpos($output, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidate = substr($output, $firstBrace, $lastBrace - $firstBrace + 1);
            $json = json_decode($candidate, true);
            if (is_array($json)) {
                return $json;
            }
        }

        throw new RuntimeException('Érvénytelen sync JSON kimenet.');
    }
}

if (!function_exists('collectEventMarketsHealthCounts')) {
    function collectEventMarketsHealthCounts(mysqli $conn): array
    {
        $eventMarkets = (int)($conn->query("SELECT COUNT(*) AS c FROM EventMarkets")->fetch_assoc()['c'] ?? 0);
        $oddsOutcomes = (int)($conn->query("SELECT COUNT(*) AS c FROM OddsOutcomes")->fetch_assoc()['c'] ?? 0);
        $joinRows = (int)($conn->query("SELECT COUNT(*) AS c FROM EventMarkets em JOIN OddsOutcomes oo ON oo.event_market_id = em.id")->fetch_assoc()['c'] ?? 0);

        return [
            'event_markets' => $eventMarkets,
            'odds_outcomes' => $oddsOutcomes,
            'join_rows' => $joinRows,
        ];
    }
}

if (!function_exists('runEventMarketsSyncSubprocess')) {
    function runEventMarketsSyncSubprocess(): array
    {
        $syncScript = dirname(__DIR__) . '/ApiRequest/sync_eventmarkets_and_odds.php';
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($syncScript);
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $raw = trim(implode("\n", $output));
        if ($exitCode !== 0) {
            throw new RuntimeException('Sync subprocess hibával tért vissza (exit=' . $exitCode . ').');
        }

        return decodeEventMarketsJsonOutput($raw);
    }
}

if (!function_exists('runEventMarketsHealthCheck')) {
    function runEventMarketsHealthCheck(mysqli $conn, bool $autoRepair = true): array
    {
        $before = collectEventMarketsHealthCounts($conn);

        $healthy = ($before['event_markets'] > 0 && $before['odds_outcomes'] > 0 && $before['join_rows'] > 0);

        $result = [
            'healthy' => $healthy,
            'checked_at' => date('Y-m-d H:i:s'),
            'before' => $before,
            'repair_attempted' => false,
            'repair_success' => false,
            'repair_message' => null,
            'after' => $before,
        ];

        if ($healthy || !$autoRepair) {
            return $result;
        }

        $result['repair_attempted'] = true;

        try {
            $syncJson = runEventMarketsSyncSubprocess();
            if (empty($syncJson['success'])) {
                $result['repair_message'] = $syncJson['error'] ?? 'Ismeretlen sync hiba';
            } else {
                $result['repair_message'] = 'Sync újrafuttatva: sikeres.';
            }
        } catch (Throwable $e) {
            $result['repair_message'] = $e->getMessage();
        }

        $after = collectEventMarketsHealthCounts($conn);
        $result['after'] = $after;
        $result['repair_success'] = ($after['event_markets'] > 0 && $after['odds_outcomes'] > 0 && $after['join_rows'] > 0);
        $result['healthy'] = $result['repair_success'];

        return $result;
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    require_once dirname(__DIR__) . '/connect.php';

    $isCli = (php_sapi_name() === 'cli');
    $response = runEventMarketsHealthCheck($conn, true);

    if ($isCli) {
        echo "EventMarkets health: " . ($response['healthy'] ? 'OK' : 'HIBA') . PHP_EOL;
        echo 'Before: ' . json_encode($response['before'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        if ($response['repair_attempted']) {
            echo 'Repair: ' . ($response['repair_success'] ? 'SIKERES' : 'SIKERTELEN') . PHP_EOL;
            echo 'Repair message: ' . ($response['repair_message'] ?? '-') . PHP_EOL;
            echo 'After: ' . json_encode($response['after'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        exit($response['healthy'] ? 0 : 1);
    }

    header('Content-Type: application/json; charset=utf-8');
    http_response_code($response['healthy'] ? 200 : 500);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
