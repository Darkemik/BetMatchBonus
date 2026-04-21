<?php
/**
 * SYNC_EVENTMARKETS_AND_ODDS.PHP
 *
 * Piacok és oddsok szinkronizálása az API match-details végpontjáról:
 *   /api/matches/{eventId}
 *
 * Használat:
 *   - Include-ból: runEventMarketsOddsSync($conn, [...])
 *   - CLI: php backend/ApiRequest/sync_eventmarkets_and_odds.php
 *   - HTTP: /backend/ApiRequest/sync_eventmarkets_and_odds.php
 */

require_once dirname(__DIR__) . '/connect.php';
require_once dirname(__DIR__) . '/config.php';

date_default_timezone_set('Europe/Budapest');

if (!function_exists('emSyncSafeInt')) {
    function emSyncSafeInt($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            return $default;
        }
        return (int)$value;
    }
}

if (!function_exists('emSyncStablePositiveId')) {
    function emSyncStablePositiveId(string $seed): string
    {
        return (string)sprintf('%u', crc32($seed));
    }
}

if (!function_exists('emSyncNormalizeStatus')) {
    function emSyncNormalizeStatus(?string $status, string $fallback = 'OPEN'): string
    {
        $value = strtoupper(trim((string)$status));
        if ($value === '') {
            return $fallback;
        }

        if (strlen($value) > 20) {
            $value = substr($value, 0, 20);
        }

        return $value;
    }
}

if (!function_exists('emSyncLoadTargetEvents')) {
    function emSyncLoadTargetEvents(mysqli $conn, array $options): array
    {
        $daysBack = max(0, emSyncSafeInt($options['days_back'] ?? 1, 1));
        $daysForward = max(0, emSyncSafeInt($options['days_forward'] ?? 2, 2));
        $limit = emSyncSafeInt($options['limit'] ?? 160, 160);
        if ($limit <= 0) {
            $limit = 160;
        }
        if ($limit > 600) {
            $limit = 600;
        }

        $eventApiId = emSyncSafeInt($options['event_api_id'] ?? 0, 0);
        $sportApiId = emSyncSafeInt($options['sport_api_id'] ?? 0, 0);

        if ($eventApiId > 0) {
            $stmt = $conn->prepare("SELECT e.id, e.api_id FROM Events e WHERE e.api_id = ? LIMIT 1");
            $stmt->bind_param('i', $eventApiId);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $rows;
        }

        $sql = "
            SELECT e.id, e.api_id
            FROM Events e
            INNER JOIN Sports s ON s.id = e.sport_id
            WHERE e.api_id > 0
              AND (
                    e.is_live = 1
                    OR (
                        e.start_time BETWEEN DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$daysBack} DAY)
                                        AND DATE_ADD(UTC_TIMESTAMP(), INTERVAL {$daysForward} DAY)
                       )
                  )
              AND (e.is_live = 1 OR e.status_id IN (1, 2, 3))
        ";

        if ($sportApiId > 0) {
            $sql .= " AND s.api_id = ?";
        }

        $sql .= " ORDER BY e.is_live DESC, e.start_time ASC LIMIT {$limit}";

        $stmt = $conn->prepare($sql);
        if ($sportApiId > 0) {
            $stmt->bind_param('i', $sportApiId);
        }

        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('emSyncUpsertMarket')) {
    function emSyncUpsertMarket(
        mysqli $conn,
        int $eventId,
        string $apiMarketId,
        int $typeId,
        string $name,
        ?string $specialValue,
        int $isActive,
        string $status
    ): int {
        $sel = $conn->prepare("SELECT id FROM EventMarkets WHERE event_id = ? AND api_market_id = ? ORDER BY id ASC LIMIT 1");
        $sel->bind_param('is', $eventId, $apiMarketId);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        if ($row) {
            $marketId = (int)$row['id'];
            $upd = $conn->prepare("UPDATE EventMarkets SET type_id = ?, name = ?, special_value = ?, is_active = ?, status = ? WHERE id = ?");
            $upd->bind_param('issisi', $typeId, $name, $specialValue, $isActive, $status, $marketId);
            $upd->execute();
            $upd->close();
            return $marketId;
        }

        $ins = $conn->prepare("INSERT INTO EventMarkets (event_id, api_market_id, type_id, name, special_value, is_active, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $ins->bind_param('isissis', $eventId, $apiMarketId, $typeId, $name, $specialValue, $isActive, $status);
        $ins->execute();
        $marketId = (int)$conn->insert_id;
        $ins->close();

        return $marketId;
    }
}

if (!function_exists('emSyncUpsertOutcome')) {
    function emSyncUpsertOutcome(
        mysqli $conn,
        int $eventMarketId,
        string $apiOutcomeId,
        string $label,
        float $odds,
        ?int $role,
        string $status
    ): int {
        $sel = $conn->prepare("SELECT id FROM OddsOutcomes WHERE event_market_id = ? AND api_outcome_id = ? ORDER BY id ASC LIMIT 1");
        $sel->bind_param('is', $eventMarketId, $apiOutcomeId);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        if ($row) {
            $outcomeId = (int)$row['id'];
            $upd = $conn->prepare("UPDATE OddsOutcomes SET label = ?, odds = ?, role = ?, status = ? WHERE id = ?");
            $upd->bind_param('sdisi', $label, $odds, $role, $status, $outcomeId);
            $upd->execute();
            $upd->close();
            return $outcomeId;
        }

        $ins = $conn->prepare("INSERT INTO OddsOutcomes (event_market_id, api_outcome_id, label, odds, role, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $ins->bind_param('issdis', $eventMarketId, $apiOutcomeId, $label, $odds, $role, $status);
        $ins->execute();
        $outcomeId = (int)$conn->insert_id;
        $ins->close();

        return $outcomeId;
    }
}

if (!function_exists('runEventMarketsOddsSync')) {
    function runEventMarketsOddsSync(mysqli $conn, array $options = []): array
    {
        $targets = emSyncLoadTargetEvents($conn, $options);

        $stats = [
            'target_events' => count($targets),
            'processed_events' => 0,
            'synced_markets' => 0,
            'synced_outcomes' => 0,
            'api_errors' => 0,
            'skipped_no_markets' => 0,
        ];

        foreach ($targets as $eventRow) {
            $eventInternalId = (int)($eventRow['id'] ?? 0);
            $eventApiId = (int)($eventRow['api_id'] ?? 0);
            if ($eventInternalId <= 0 || $eventApiId <= 0) {
                continue;
            }

            try {
                $detail = apiGet(EP_MATCH_DETAILS . '/' . $eventApiId);
            } catch (Throwable $e) {
                $stats['api_errors']++;
                error_log('EventMarkets sync API hiba eventId=' . $eventApiId . ': ' . $e->getMessage());
                continue;
            }

            $markets = $detail['markets'] ?? null;
            if (!is_array($markets) || count($markets) === 0) {
                $stats['skipped_no_markets']++;
                continue;
            }

            $conn->begin_transaction();
            try {
                foreach ($markets as $marketIndex => $market) {
                    if (!is_array($market)) {
                        continue;
                    }

                    $marketName = trim((string)($market['name'] ?? ''));
                    if ($marketName === '') {
                        continue;
                    }

                    $specialValueRaw = isset($market['specialValue']) ? trim((string)$market['specialValue']) : null;
                    $specialValue = ($specialValueRaw === '' ? null : $specialValueRaw);

                    $apiMarketId = emSyncSafeInt($market['id'] ?? ($market['marketId'] ?? ($market['apiMarketId'] ?? 0)), 0);
                    if ($apiMarketId <= 0) {
                        $apiMarketIdStr = emSyncStablePositiveId($eventApiId . '|' . $marketName . '|' . (string)$specialValue);
                    } else {
                        $apiMarketIdStr = (string)$apiMarketId;
                    }

                    $typeId = emSyncSafeInt($market['typeId'] ?? ($market['type_id'] ?? 0), 0);
                    $marketStatus = emSyncNormalizeStatus(
                        (string)($market['status'] ?? ''),
                        (!empty($market['isActive']) ? 'OPEN' : 'CLOSED')
                    );
                    $isActive = ($marketStatus === 'OPEN' || $marketStatus === 'ACTIVE') ? 1 : 0;

                    $eventMarketId = emSyncUpsertMarket(
                        $conn,
                        $eventInternalId,
                        $apiMarketIdStr,
                        $typeId,
                        $marketName,
                        $specialValue,
                        $isActive,
                        $marketStatus
                    );
                    $stats['synced_markets']++;

                    $selections = $market['selections'] ?? null;
                    if (!is_array($selections)) {
                        continue;
                    }

                    $roleCounter = 1;
                    foreach ($selections as $selectionIndex => $selection) {
                        if (!is_array($selection)) {
                            continue;
                        }

                        $label = trim((string)($selection['name'] ?? ($selection['label'] ?? '')));
                        if ($label === '') {
                            continue;
                        }

                        $rawOdds = (float)($selection['odd'] ?? ($selection['odds'] ?? 0));
                        if ($rawOdds < 0) {
                            $rawOdds = 0.0;
                        }
                        $odds = round($rawOdds, 4);

                        $apiOutcomeId = emSyncSafeInt($selection['id'] ?? ($selection['outcomeId'] ?? ($selection['apiOutcomeId'] ?? 0)), 0);
                        if ($apiOutcomeId <= 0) {
                            $apiOutcomeIdStr = emSyncStablePositiveId($apiMarketIdStr . '|' . $label . '|' . $selectionIndex);
                        } else {
                            $apiOutcomeIdStr = (string)$apiOutcomeId;
                        }

                        $role = isset($selection['role']) && is_numeric($selection['role'])
                            ? (int)$selection['role']
                            : $roleCounter;
                        $roleCounter++;

                        $outcomeStatus = emSyncNormalizeStatus(
                            (string)($selection['status'] ?? ''),
                            ($odds > 0 ? 'OPEN' : 'CLOSED')
                        );

                        emSyncUpsertOutcome(
                            $conn,
                            $eventMarketId,
                            $apiOutcomeIdStr,
                            $label,
                            $odds,
                            $role,
                            $outcomeStatus
                        );
                        $stats['synced_outcomes']++;
                    }
                }

                $conn->commit();
                $stats['processed_events']++;
            } catch (Throwable $e) {
                $conn->rollback();
                $stats['api_errors']++;
                error_log('EventMarkets sync DB hiba eventId=' . $eventApiId . ': ' . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'message' => 'EventMarkets + OddsOutcomes szinkron kész',
            'stats' => $stats,
        ];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $isCli = (php_sapi_name() === 'cli');
    if (!$isCli) {
        header('Content-Type: application/json; charset=utf-8');
    }

    try {
        $options = [
            'days_back' => $_GET['daysBack'] ?? null,
            'days_forward' => $_GET['daysForward'] ?? null,
            'limit' => $_GET['limit'] ?? null,
            'event_api_id' => $_GET['eventId'] ?? null,
            'sport_api_id' => $_GET['sportId'] ?? null,
        ];

        $response = runEventMarketsOddsSync($conn, $options);

        if ($isCli) {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
            exit(0);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        if ($isCli) {
            fwrite(STDERR, 'EventMarkets sync hiba: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
