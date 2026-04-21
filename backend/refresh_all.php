<?php
/**
 * REFRESH_ALL.PHP — ⭐ EGY GOMBNYOMÁS = MINDEN FRISSÜL
 * 
 * Lépések:
 *   1) Bónusz aktivitás frissítés (hétköznap/hétvége)
 *   2) Sportadatok szinkronizálása (API → DB) via sync_competitions_and_events.php
 *   3) Nyitott szelvények kiértékelése via check_bets.php
 * 
 * Használat:
 *   Böngésző: http://localhost/backend/refresh_all.php
 *   Terminál: php backend/refresh_all.php
 *   CRON:     every 2 min — php /path/to/backend/refresh_all.php
 */

date_default_timezone_set('Europe/Budapest');
$startTime = microtime(true);
$results   = [];
$hasError  = false;

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    // Session lock feloldása — ne blokkolja a párhuzamos AJAX kéréseket
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

require_once __DIR__ . '/connect.php';

/**
 * JSON objektum kinyerése zajos kimenetből is.
 * Pl. warning + JSON kombináció esetén is megpróbálja a tényleges JSON-t értelmezni.
 */
function decodeSyncOutput(string $output): array
{
    $output = trim($output);
    if ($output === '') {
        throw new RuntimeException('Szinkron kimenet üres. Ellenőrizd a sync fájl futását és PHP error logot.');
    }

    $json = json_decode($output, true);
    if (is_array($json)) {
        return $json;
    }

    // Fallback: ha warning/notice került a JSON elé vagy mögé, vágjuk ki a JSON blokkot.
    $firstBrace = strpos($output, '{');
    $lastBrace  = strrpos($output, '}');
    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $candidate = substr($output, $firstBrace, $lastBrace - $firstBrace + 1);
        $json = json_decode($candidate, true);
        if (is_array($json)) {
            return $json;
        }
    }

    $short = mb_substr(preg_replace('/\s+/', ' ', $output), 0, 220);
    throw new RuntimeException('Érvénytelen sync JSON kimenet: ' . $short);
}

function normalizeBonusToken(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = strtr($value, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
    ]);
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return $value ?? '';
}

function buildBonusLookup(mysqli $conn): array
{
    $rows = [];
    $byCodeNorm = [];

    $res = $conn->query('SELECT id, code, name FROM BonusCodes');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $code = (string)($row['code'] ?? '');
            $name = (string)($row['name'] ?? '');

            $entry = [
                'id' => $id,
                'code' => $code,
                'name' => $name,
                'code_norm' => normalizeBonusToken($code),
                'name_norm' => normalizeBonusToken($name),
            ];
            $rows[] = $entry;

            if ($entry['code_norm'] !== '' && !isset($byCodeNorm[$entry['code_norm']])) {
                $byCodeNorm[$entry['code_norm']] = $id;
            }
        }
    }

    return ['rows' => $rows, 'by_code_norm' => $byCodeNorm];
}

function resolveBonusIdFromSlug(string $slug, array $lookup): int
{
    $slugNorm = normalizeBonusToken($slug);
    if ($slugNorm === '') {
        return 0;
    }

    $byCodeNorm = $lookup['by_code_norm'] ?? [];
    $rows = $lookup['rows'] ?? [];

    $aliasToCode = [
        'weekdays' => 'BONUSZHETKOZNAP5K',
        'weekend' => 'HETVEGI5K',
        'darts' => 'DARTSBONUSZ5K',
        'cashback' => 'CASHBACK30',
        'daily' => 'TOP_REWARD_DAILY',
        'nb1' => 'NB1DERBY',
        'esport' => 'ESPORT5K',
        'admin' => '__ADMIN_BONUS__',
    ];

    if (isset($aliasToCode[$slugNorm])) {
        $codeNorm = normalizeBonusToken($aliasToCode[$slugNorm]);
        if (isset($byCodeNorm[$codeNorm])) {
            return (int)$byCodeNorm[$codeNorm];
        }
    }

    if ($slugNorm === 'bmbbirthday') {
        foreach ($rows as $r) {
            $nameNorm = (string)($r['name_norm'] ?? '');
            if (strpos($nameNorm, 'betmatch') !== false && strpos($nameNorm, 'szuletesnapi') !== false) {
                return (int)$r['id'];
            }
        }
    }

    if ($slugNorm === 'birthday') {
        foreach ($rows as $r) {
            $nameNorm = (string)($r['name_norm'] ?? '');
            if (strpos($nameNorm, 'szuletesnapi') !== false && strpos($nameNorm, 'betmatch') === false) {
                return (int)$r['id'];
            }
        }
    }

    if (isset($byCodeNorm[$slugNorm])) {
        return (int)$byCodeNorm[$slugNorm];
    }

    foreach ($rows as $r) {
        $nameNorm = (string)($r['name_norm'] ?? '');
        if ($nameNorm !== '' && strpos($nameNorm, $slugNorm) !== false) {
            return (int)$r['id'];
        }
    }

    return 0;
}

/**
 * Feltöltött bónusz képek visszaszinkronizálása az adatbázisba.
 *
 * Fájlnév minták:
 *   - bonus_{bonusId}_...ext
 *   - bonusz_{slug}.ext (pl. bonusz_weekdays.jpg)
 */
function syncBonusImagesFromUploads(mysqli $conn): array
{
    $uploadDir = __DIR__ . '/uploads/bonuses';
    if (!is_dir($uploadDir)) {
        return [
            'status' => 'skipped',
            'updated' => 0,
            'matched' => 0,
            'missing_bonus' => 0,
            'invalid_names' => 0,
            'message' => 'uploads/bonuses mappa nem található',
        ];
    }

    $files = glob($uploadDir . '/*.{jpg,jpeg,png,gif,svg,webp,JPG,JPEG,PNG,GIF,SVG,WEBP}', GLOB_BRACE);
    if (!$files) {
        return [
            'status' => 'ok',
            'updated' => 0,
            'matched' => 0,
            'missing_bonus' => 0,
            'invalid_names' => 0,
            'message' => 'Nincs szinkronizálható bónuszkép',
        ];
    }

    $latestByBonusId = [];
    $bonusLookup = buildBonusLookup($conn);
    $invalidNames = 0;

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        $bonusId = 0;

        if (preg_match('/^bonus_(\d+)(?:_|\.).+/i', $filename, $matches)) {
            $bonusId = (int)$matches[1];
        } elseif (preg_match('/^bonusz?_([a-z0-9_-]+)\.[a-z0-9]+$/i', $filename, $matches)) {
            $bonusId = resolveBonusIdFromSlug((string)$matches[1], $bonusLookup);
        }

        if ($bonusId <= 0) {
            $invalidNames++;
            continue;
        }

        $mtime = @filemtime($filePath) ?: 0;
        if (!isset($latestByBonusId[$bonusId]) || $mtime >= $latestByBonusId[$bonusId]['mtime']) {
            $latestByBonusId[$bonusId] = [
                'filename' => $filename,
                'mtime' => $mtime,
            ];
        }
    }

    if (!$latestByBonusId) {
        return [
            'status' => 'ok',
            'updated' => 0,
            'matched' => 0,
            'missing_bonus' => 0,
            'invalid_names' => $invalidNames,
            'message' => 'Nincs feldolgozható bónuszkép fájlnév minta alapján',
        ];
    }

    $selectStmt = $conn->prepare('SELECT image_url, code FROM BonusCodes WHERE id = ? LIMIT 1');
    $updateStmt = $conn->prepare('UPDATE BonusCodes SET image_url = ? WHERE id = ?');
    if (!$selectStmt || !$updateStmt) {
        throw new RuntimeException('DB statement hiba a bónusz képszinkron közben: ' . $conn->error);
    }

    $updated = 0;
    $matched = 0;
    $missingBonus = 0;

    foreach ($latestByBonusId as $bonusId => $item) {
        $expectedUrl = '../../backend/uploads/bonuses/' . $item['filename'];

        $selectStmt->bind_param('i', $bonusId);
        $selectStmt->execute();
        $row = $selectStmt->get_result()->fetch_assoc();

        if (!$row) {
            $missingBonus++;
            continue;
        }

        $bonusCode = (string)($row['code'] ?? '');
        if ($bonusCode === '__ADMIN_FREEBET__') {
            $matched++;
            continue;
        }

        $currentUrl = (string)($row['image_url'] ?? '');
        if ($currentUrl === $expectedUrl) {
            $matched++;
            continue;
        }

        $updateStmt->bind_param('si', $expectedUrl, $bonusId);
        if (!$updateStmt->execute()) {
            throw new RuntimeException('DB update hiba (bonus_id=' . $bonusId . '): ' . $updateStmt->error);
        }

        if ($updateStmt->affected_rows >= 0) {
            $updated++;
        }
    }

    $selectStmt->close();
    $updateStmt->close();

    return [
        'status' => 'ok',
        'updated' => $updated,
        'matched' => $matched,
        'missing_bonus' => $missingBonus,
        'invalid_names' => $invalidNames,
        'message' => 'Bónuszképek szinkron kész',
    ];
}

// ── 0. SEED CHECK — Ha üresek a táblák, automatikusan feltölti ──
try {
    $needPostal = $conn->query("SHOW TABLES LIKE 'PostalCodes'")->num_rows === 0
                  || $conn->query("SELECT 1 FROM PostalCodes LIMIT 1")->num_rows === 0;
    if ($needPostal) {
        require_once __DIR__ . '/DataBase/seed_postal_codes.php';
        $results[] = ['step' => 'Seed: PostalCodes', 'status' => 'ok'];
    }

    $needCities = $conn->query("SELECT 1 FROM Cities LIMIT 1")->num_rows === 0;
    if ($needCities) {
        require_once __DIR__ . '/DataBase/seed_cities.php';
        $results[] = ['step' => 'Seed: Cities', 'status' => 'ok'];
    }

    $needAdmins = $conn->query("SELECT 1 FROM AdminUsers LIMIT 1")->num_rows === 0;
    if ($needAdmins) {
        require_once __DIR__ . '/DataBase/seed_admins.php';
        $results[] = ['step' => 'Seed: AdminUsers', 'status' => 'ok'];
    }

    $needSettings = $conn->query("SHOW TABLES LIKE 'SystemSettings'")->num_rows === 0
                    || $conn->query("SELECT 1 FROM SystemSettings LIMIT 1")->num_rows === 0;
    if ($needSettings) {
        require_once __DIR__ . '/DataBase/seed_system_settings.php';
        $results[] = ['step' => 'Seed: SystemSettings', 'status' => 'ok'];
    }
} catch (Exception $e) {
    $results[] = ['step' => 'Seed check', 'status' => 'error', 'message' => $e->getMessage()];
}

// ── 1. BÓNUSZ FRISSÍTÉS ─────────────────────────
$stepStart = microtime(true);
try {
    // Hétköznapi bónusz fix üzleti paraméterek (nem lépcsős):
    // min befizetés 3000 Ft, 100% bónusz max 5000 Ft, 3x forgatás.
    $conn->query(" 
        UPDATE BonusCodes
        SET min_deposit = 3000.00,
            max_bonus_amount = 5000.00,
            match_percent = 100.00,
            bonus_amount = 0.00,
            is_step_bonus = 0,
            bonus_trigger = 'DEPOSIT',
            bet_reward_type = 'BONUS_MONEY',
            wagering_multiplier = 3.00,
            valid_weekdays_only = 1
        WHERE code = 'BONUSZHETKOZNAP5K'
    ");

    // Hétvégi bónusz auto-toggle: csak szombat-vasárnap legyen aktív.
    $isWeekend = ((int)date('N') >= 6) ? 1 : 0;
    $weekendToggleStmt = $conn->prepare(" 
        UPDATE BonusCodes
        SET is_active = CASE
            WHEN admin_force_active = 1 THEN 1
            WHEN ? = 1 THEN 1
            ELSE 0
        END
        WHERE code = 'HETVEGI5K'
    ");
    if ($weekendToggleStmt) {
        $weekendToggleStmt->bind_param('i', $isWeekend);
        $weekendToggleStmt->execute();
        $weekendToggleStmt->close();
    }

        // NB1 Derby bónusz auto-toggle: csak azon a napon aktív,
        // amikor Budapest idő szerint van Újpest–Ferencváros mérkőzés.
        $bpTz = new DateTimeZone('Europe/Budapest');
        $utcTz = new DateTimeZone('UTC');
        $bpStart = new DateTime('today 00:00:00', $bpTz);
        $bpEnd = new DateTime('today 23:59:59', $bpTz);
        $bpStart->setTimezone($utcTz);
        $bpEnd->setTimezone($utcTz);
        $dayFromUtc = $bpStart->format('Y-m-d H:i:s');
        $dayToUtc = $bpEnd->format('Y-m-d H:i:s');

        $nb1DerbyToday = 0;
        $nb1DerbyStmt = $conn->prepare(" 
                SELECT COUNT(*) AS cnt
                FROM Events e
                INNER JOIN Sports s ON s.id = e.sport_id
                WHERE e.start_time BETWEEN ? AND ?
                    AND UPPER(COALESCE(s.name, '')) = 'FOOTBALL'
                    AND (
                        (
                            (LOWER(COALESCE(e.home_team_name, '')) LIKE '%ujpest%' OR LOWER(COALESCE(e.home_team_name, '')) LIKE '%újpest%')
                            AND
                            (LOWER(COALESCE(e.away_team_name, '')) LIKE '%ferenc%' OR LOWER(COALESCE(e.away_team_name, '')) LIKE '%fradi%')
                        )
                        OR
                        (
                            (LOWER(COALESCE(e.away_team_name, '')) LIKE '%ujpest%' OR LOWER(COALESCE(e.away_team_name, '')) LIKE '%újpest%')
                            AND
                            (LOWER(COALESCE(e.home_team_name, '')) LIKE '%ferenc%' OR LOWER(COALESCE(e.home_team_name, '')) LIKE '%fradi%')
                        )
                    )
        ");
        if ($nb1DerbyStmt) {
                $nb1DerbyStmt->bind_param('ss', $dayFromUtc, $dayToUtc);
                $nb1DerbyStmt->execute();
                $nb1DerbyRow = $nb1DerbyStmt->get_result()->fetch_assoc();
                $nb1DerbyStmt->close();
                $nb1DerbyToday = ((int)($nb1DerbyRow['cnt'] ?? 0) > 0) ? 1 : 0;
        }

        $nb1ToggleStmt = $conn->prepare(" 
                UPDATE BonusCodes
                SET is_active = CASE
                        WHEN admin_force_active = 1 THEN 1
                        WHEN ? = 1 THEN 1
                        ELSE 0
                END
                WHERE code = 'NB1DERBY'
        ");
        if ($nb1ToggleStmt) {
                $nb1ToggleStmt->bind_param('i', $nb1DerbyToday);
                $nb1ToggleStmt->execute();
                $nb1ToggleStmt->close();
        }

        // Esport bónusz auto-toggle: csak akkor aktív,
        // ha ma van LoL / Counter-Strike / Valorant esport esemény.
        $esportToday = 0;
        $esportTodayStmt = $conn->prepare(" 
            SELECT COUNT(*) AS cnt
            FROM Events e
            INNER JOIN Sports s ON s.id = e.sport_id
            INNER JOIN Competitions ch ON ch.id = e.competition_id
            WHERE e.start_time BETWEEN ? AND ?
                AND s.api_id = 145
                AND COALESCE(NULLIF(ch.game_tag, ''), 'other') IN ('lol', 'cs', 'valorant')
        ");
        if ($esportTodayStmt) {
            $esportTodayStmt->bind_param('ss', $dayFromUtc, $dayToUtc);
            $esportTodayStmt->execute();
            $esportTodayRow = $esportTodayStmt->get_result()->fetch_assoc();
            $esportTodayStmt->close();
            $esportToday = ((int)($esportTodayRow['cnt'] ?? 0) > 0) ? 1 : 0;
        }

        $esportToggleStmt = $conn->prepare(" 
            UPDATE BonusCodes
            SET is_active = CASE
                WHEN admin_force_active = 1 THEN 1
                WHEN ? = 1 THEN 1
                ELSE 0
            END
            WHERE code = 'ESPORT5K'
        ");
        if ($esportToggleStmt) {
            $esportToggleStmt->bind_param('i', $esportToday);
            $esportToggleStmt->execute();
            $esportToggleStmt->close();
        }

        // BetMatch születésnapi bónusz auto-toggle: csak április 25-én legyen aktív.
        $betmatchBirthdayDate = '04-25';
        $isBetmatchBirthday = (date('m-d') === $betmatchBirthdayDate) ? 1 : 0;
        $betmatchBirthdayToggleStmt = $conn->prepare(" 
            UPDATE BonusCodes
            SET is_active = CASE
                WHEN admin_force_active = 1 THEN 1
                WHEN ? = 1 THEN 1
                ELSE 0
            END
            WHERE birthday_bonus = 1
              AND (code IS NULL OR code = '')
              AND name LIKE 'BetMatch Születésnapi Bónusz%'
        ");
        if ($betmatchBirthdayToggleStmt) {
            $betmatchBirthdayToggleStmt->bind_param('i', $isBetmatchBirthday);
            $betmatchBirthdayToggleStmt->execute();
            $betmatchBirthdayToggleStmt->close();
        }

        // Születésnapi bónusz auto-jóváírás: a felhasználó a saját születésnapján
        // automatikusan megkapja a standard "Születésnapi Bónusz" jutalmat (évente egyszer).
        $birthdayCheckedUsers = 0;
        $birthdayGrantedUsers = 0;

        $todayMonthDay = date('m-d');
        $currentYear = (int)date('Y');

        $birthdayBonusStmt = $conn->prepare(" 
            SELECT id, name, bonus_amount, max_bonus_amount, bet_reward_type,
                   wagering_multiplier, activation_expire_hours
            FROM BonusCodes
            WHERE birthday_bonus = 1
              AND (code IS NULL OR code = '')
              AND name LIKE 'Születésnapi Bónusz%'
            ORDER BY id ASC
            LIMIT 1
        ");

        $birthdayBonusRow = null;
        if ($birthdayBonusStmt) {
            $birthdayBonusStmt->execute();
            $birthdayBonusRow = $birthdayBonusStmt->get_result()->fetch_assoc();
            $birthdayBonusStmt->close();
        }

        if ($birthdayBonusRow) {
            $birthdayBonusId = (int)$birthdayBonusRow['id'];
            $birthdayBonusAmount = (float)($birthdayBonusRow['max_bonus_amount'] ?? 0);
            if ($birthdayBonusAmount <= 0) {
                $birthdayBonusAmount = (float)($birthdayBonusRow['bonus_amount'] ?? 0);
            }

            $birthdayIsFreeBet = (strtoupper((string)($birthdayBonusRow['bet_reward_type'] ?? '')) === 'FREE_BET');
            $birthdayWageringMultiplier = (float)($birthdayBonusRow['wagering_multiplier'] ?? 0);
            $birthdayWageringRequired = $birthdayBonusAmount > 0 ? ($birthdayBonusAmount * max(0.0, $birthdayWageringMultiplier)) : 0.0;

            $birthdayExpiresAt = null;
            $birthdayExpireHours = (int)($birthdayBonusRow['activation_expire_hours'] ?? 0);
            if ($birthdayExpireHours > 0) {
                $birthdayExpiresAt = date('Y-m-d H:i:s', strtotime('+' . $birthdayExpireHours . ' hours'));
            }

            $birthdayUsersStmt = $conn->prepare(" 
                SELECT id
                FROM Users
                WHERE is_active = 1
                  AND DATE_FORMAT(birth_date, '%m-%d') = ?
            ");

            if ($birthdayUsersStmt) {
                $birthdayUsersStmt->bind_param('s', $todayMonthDay);
                $birthdayUsersStmt->execute();
                $birthdayUsers = $birthdayUsersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $birthdayUsersStmt->close();

                $birthdayCheckedUsers = count($birthdayUsers);

                $alreadyGrantedStmt = $conn->prepare(" 
                    SELECT id
                    FROM UserBonuses
                    WHERE user_id = ?
                      AND bonus_id = ?
                      AND YEAR(created_at) = ?
                    LIMIT 1
                ");

                $insertBirthdayStmt = $conn->prepare(" 
                    INSERT INTO UserBonuses
                        (user_id, bonus_id, status, granted_amount, bonus_balance, free_bet_amount, bonus_money_amount, wagering_required, expires_at)
                    VALUES
                        (?, ?, 'ACTIVE', ?, ?, ?, ?, ?, ?)
                ");

                $syncBirthdayBalanceStmt = $conn->prepare(" 
                    UPDATE Users SET bonus_balance = (
                        SELECT COALESCE(SUM(ub.bonus_balance), 0)
                        FROM UserBonuses ub
                        WHERE ub.user_id = ?
                          AND ub.status = 'ACTIVE'
                          AND ub.used = 0
                          AND (ub.expires_at IS NULL OR ub.expires_at > NOW())
                    )
                    WHERE id = ?
                ");

                $birthdayNotifStmt = $conn->prepare(" 
                    INSERT INTO Notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Születésnapi bónusz jóváírás', 'Boldog születésnapot! Jóváírtunk neked 5.000 Ft születésnapi bónuszt.', 'bonus', NOW())
                ");

                foreach ($birthdayUsers as $uRow) {
                    $birthdayUserId = (int)($uRow['id'] ?? 0);
                    if ($birthdayUserId <= 0) {
                        continue;
                    }

                    if ($alreadyGrantedStmt) {
                        $alreadyGrantedStmt->bind_param('iii', $birthdayUserId, $birthdayBonusId, $currentYear);
                        $alreadyGrantedStmt->execute();
                        $alreadyGrantedRes = $alreadyGrantedStmt->get_result();
                        if ($alreadyGrantedRes && $alreadyGrantedRes->num_rows > 0) {
                            continue;
                        }
                    }

                    $birthdayBonusBalance = $birthdayIsFreeBet ? 0.0 : $birthdayBonusAmount;
                    $birthdayFreeBetAmount = $birthdayIsFreeBet ? $birthdayBonusAmount : 0.0;
                    $birthdayBonusMoneyAmount = $birthdayIsFreeBet ? 0.0 : $birthdayBonusAmount;

                    if ($insertBirthdayStmt) {
                        $insertBirthdayStmt->bind_param(
                            'iiddddds',
                            $birthdayUserId,
                            $birthdayBonusId,
                            $birthdayBonusAmount,
                            $birthdayBonusBalance,
                            $birthdayFreeBetAmount,
                            $birthdayBonusMoneyAmount,
                            $birthdayWageringRequired,
                            $birthdayExpiresAt
                        );
                        $ok = $insertBirthdayStmt->execute();
                        if (!$ok) {
                            continue;
                        }
                    }

                    if ($syncBirthdayBalanceStmt) {
                        $syncBirthdayBalanceStmt->bind_param('ii', $birthdayUserId, $birthdayUserId);
                        $syncBirthdayBalanceStmt->execute();
                    }

                    if ($birthdayNotifStmt) {
                        $birthdayNotifStmt->bind_param('i', $birthdayUserId);
                        $birthdayNotifStmt->execute();
                    }

                    $birthdayGrantedUsers++;
                }

                if ($alreadyGrantedStmt) $alreadyGrantedStmt->close();
                if ($insertBirthdayStmt) $insertBirthdayStmt->close();
                if ($syncBirthdayBalanceStmt) $syncBirthdayBalanceStmt->close();
                if ($birthdayNotifStmt) $birthdayNotifStmt->close();
            }
        }

    $isWeekday = ((int)date('N') <= 5) ? 1 : 0;

    // Hétköznap-only bónuszok auto-toggle: daily_start_time figyelembevételével
    // admin_force_active = 1 esetén nem írjuk felül (admin kézzel bekapcsolta)
    // Csak akkor töröljük az override-ot, ha a normál időablakban vagyunk (hétköznap + daily_start_time után)
    if ($isWeekday) {
        $conn->query("UPDATE BonusCodes SET admin_force_active = 0 WHERE valid_weekdays_only = 1 AND admin_force_active = 1 AND (daily_start_time IS NULL OR CURTIME() >= daily_start_time)");
    }
    $conn->query("
        UPDATE BonusCodes
        SET is_active = CASE
            WHEN admin_force_active = 1 THEN 1
            WHEN {$isWeekday} = 1 AND (daily_start_time IS NULL OR CURTIME() >= daily_start_time) THEN 1
            ELSE 0
        END
        WHERE valid_weekdays_only = 1
    ");

    $results[] = [
        'step'    => 'Bónusz frissítés',
        'status'  => 'ok',
        'message' => 'Hétköznapi auto-toggle: ' . ($isWeekday ? 'hétköznap' : 'hétvége')
            . ' | HETVEGI5K: ' . ($isWeekend ? 'aktív (hétvége)' : 'inaktív (hétköznap)')
            . ' | NB1DERBY: ' . ($nb1DerbyToday ? 'aktív (ma van Újpest–Ferencváros)' : 'inaktív (ma nincs derby)')
            . ' | ESPORT5K: ' . ($esportToday ? 'aktív (ma van LoL/CS/Valorant)' : 'inaktív (ma nincs LoL/CS/Valorant)')
            . ' | BetMatch szülinap: ' . ($isBetmatchBirthday ? 'aktív (ápr. 25)' : 'inaktív (nem ápr. 25)')
            . ' | Születésnapi bónusz: ' . $birthdayGrantedUsers . ' jóváírás (' . $birthdayCheckedUsers . ' érintett user)',
        'ms'      => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Bónusz frissítés', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 1/B. BÓNUSZ KÉPEK SZINKRON (UPLOADS → DB) ──
$stepStart = microtime(true);
try {
    $imgSync = syncBonusImagesFromUploads($conn);
    $results[] = [
        'step' => 'Bónusz képek szinkron',
        'status' => ($imgSync['status'] ?? 'ok') === 'ok' ? 'ok' : 'ok',
        'message' => sprintf(
            '%s | frissítve: %d, már egyezett: %d, hiányzó bónusz: %d, érvénytelen fájlnév: %d',
            $imgSync['message'] ?? 'Kész',
            (int)($imgSync['updated'] ?? 0),
            (int)($imgSync['matched'] ?? 0),
            (int)($imgSync['missing_bonus'] ?? 0),
            (int)($imgSync['invalid_names'] ?? 0)
        ),
        'ms' => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Bónusz képek szinkron', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 2. SPORTADATOK SZINKRONIZÁLÁSA (API → DB) ───
$stepStart = microtime(true);
try {
    ob_start();
    require __DIR__ . '/ApiRequest/sync_competitions_and_events.php';
    $output = trim(ob_get_clean());

    $json = decodeSyncOutput($output);
    if (!isset($json['success'])) {
        throw new RuntimeException('A sync válaszból hiányzik a success mező.');
    }
    if ($json['success'] !== true) {
        throw new RuntimeException($json['error'] ?? 'Szinkron hiba');
    }

    $stats = $json['stats'] ?? [];
    $results[] = [
        'step'    => 'Sportadatok szinkron',
        'status'  => 'ok',
        'message' => sprintf('%d sport, %d bajnokság szinkronizálva, %d meccs lezárva',
            $stats['sports'] ?? 0, $stats['competitions'] ?? 0, $stats['finished'] ?? 0),
        'ms'      => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Sportadatok szinkron', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 2/B. EVENTMARKETS + ODDS SZINKRON (API match-details → DB) ──
$stepStart = microtime(true);
try {
    require_once __DIR__ . '/ApiRequest/sync_eventmarkets_and_odds.php';

    $marketSync = runEventMarketsOddsSync($conn, [
        'days_back' => 1,
        'days_forward' => 2,
        'limit' => 180,
    ]);

    if (empty($marketSync['success'])) {
        throw new RuntimeException($marketSync['error'] ?? 'Ismeretlen EventMarkets sync hiba');
    }

    $ms = $marketSync['stats'] ?? [];
    $results[] = [
        'step' => 'EventMarkets + Odds sync',
        'status' => 'ok',
        'message' => sprintf(
            'cél: %d, feldolgozva: %d, piac: %d, kimenet: %d, API hiba: %d, piac nélküli: %d',
            (int)($ms['target_events'] ?? 0),
            (int)($ms['processed_events'] ?? 0),
            (int)($ms['synced_markets'] ?? 0),
            (int)($ms['synced_outcomes'] ?? 0),
            (int)($ms['api_errors'] ?? 0),
            (int)($ms['skipped_no_markets'] ?? 0)
        ),
        'ms' => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'EventMarkets + Odds sync', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 2/C. EVENTMARKETS HEALTH CHECK + AUTO-REPAIR ───────────
$stepStart = microtime(true);
try {
    require_once __DIR__ . '/DataBase/check_eventmarkets_health.php';
    $health = runEventMarketsHealthCheck($conn, true);

    if (!empty($health['healthy'])) {
        $msg = sprintf(
            'OK | EventMarkets: %d, OddsOutcomes: %d, JOIN: %d',
            (int)($health['after']['event_markets'] ?? 0),
            (int)($health['after']['odds_outcomes'] ?? 0),
            (int)($health['after']['join_rows'] ?? 0)
        );
        if (!empty($health['repair_attempted'])) {
            $msg .= ' | auto-repair lefutott';
        }

        $results[] = [
            'step' => 'EventMarkets health-check',
            'status' => 'ok',
            'message' => $msg,
            'ms' => round((microtime(true) - $stepStart) * 1000),
        ];
    } else {
        $hasError = true;
        $results[] = [
            'step' => 'EventMarkets health-check',
            'status' => 'hiba',
            'message' => 'Hiba: EventMarkets/OddsOutcomes üres vagy inkonzisztens. ' . (string)($health['repair_message'] ?? ''),
            'ms' => round((microtime(true) - $stepStart) * 1000),
        ];
    }
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'EventMarkets health-check', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 3. SZELVÉNYEK KIÉRTÉKELÉSE ───────────────────
$stepStart = microtime(true);
try {
    if (!function_exists('evaluateAllOpenTickets')) {
        require_once __DIR__ . '/ApiRequest/check_bets.php';
    }

    $evaluatedUsers = evaluateAllOpenTickets($conn);

    $results[] = [
        'step'    => 'Szelvény kiértékelés',
        'status'  => 'ok',
        'message' => "{$evaluatedUsers} felhasználó szelvényei ellenőrizve",
        'ms'      => round((microtime(true) - $stepStart) * 1000),
    ];
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Szelvény kiértékelés', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── 4. NAPI TOP JUTALMAK (23:00 után egyszer) ───
$stepStart = microtime(true);
try {
    $currentHour = (int)date('H');
    if ($currentHour >= 23) {
        require_once __DIR__ . '/ApiRequest/daily_top_rewards.php';
        $topResult = awardDailyTopRewards($conn);

        if (!empty($topResult['skipped'])) {
            $results[] = [
                'step'    => 'Napi top jutalmak',
                'status'  => 'ok',
                'message' => $topResult['message'] ?? 'Kihagyva',
                'ms'      => round((microtime(true) - $stepStart) * 1000),
            ];
        } else {
            $awardedCount = 0;
            $names = [];
            foreach ($topResult['awarded'] ?? [] as $a) {
                if ($a['status'] === 'awarded') {
                    $awardedCount++;
                    $names[] = $a['user'];
                }
            }
            $results[] = [
                'step'    => 'Napi top jutalmak',
                'status'  => 'ok',
                'message' => "{$awardedCount} jutalom kiosztva" . ($names ? ': ' . implode(', ', $names) : ''),
                'ms'      => round((microtime(true) - $stepStart) * 1000),
            ];
        }
    } else {
        $results[] = [
            'step'    => 'Napi top jutalmak',
            'status'  => 'ok',
            'message' => 'Csak 23:00 után fut (most: ' . date('H:i') . ')',
            'ms'      => 0,
        ];
    }
} catch (Throwable $e) {
    $hasError = true;
    $results[] = ['step' => 'Napi top jutalmak', 'status' => 'hiba', 'message' => $e->getMessage()];
}

// ── EREDMÉNY ─────────────────────────────────────
$totalMs = round((microtime(true) - $startTime) * 1000);

if ($isCli) {
    echo "\n=== BetMatchBonus Frissítés - " . date('Y-m-d H:i:s') . " ===\n";
    foreach ($results as $r) {
        $icon = ($r['status'] === 'ok') ? '✅' : '❌';
        echo "  {$icon} {$r['step']}: {$r['message']}\n";
    }
    echo "  ⏱ Összesen: {$totalMs}ms\n\n";
    if ($hasError) exit(1);
} else {
    if ($hasError) http_response_code(500);
    echo json_encode([
        'success'  => !$hasError,
        'total_ms' => $totalMs,
        'időpont'  => date('Y-m-d H:i:s'),
        'lépések'  => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}