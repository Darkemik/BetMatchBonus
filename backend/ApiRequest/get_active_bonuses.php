<?php
session_start();
require_once dirname(__DIR__) . '/connect.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Budapest');

$lang = (isset($_GET['lang']) && strtolower((string)$_GET['lang']) === 'en') ? 'en' : 'hu';

$isWeekday = ((int)date('N') <= 5);
$isWeekend = ((int)date('N') >= 6);

$isGuest = !isset($_SESSION['user_id']);
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isBirthdayTodayForUser = false;
$isBetmatchBonusDay = (date('m-d') === '05-26');
$todayFrom = date('Y-m-d 00:01:00'); // fallback, per-bonus override below
$tomorrowFrom = date('Y-m-d 00:01:00', strtotime('+1 day'));

if (!$isGuest && $userId > 0) {
    $birthCheckStmt = $conn->prepare(" 
        SELECT DATE_FORMAT(birth_date, '%m-%d') AS md
        FROM Users
        WHERE id = ?
        LIMIT 1
    ");
    if ($birthCheckStmt) {
        $birthCheckStmt->bind_param('i', $userId);
        $birthCheckStmt->execute();
        $birthCheckRow = $birthCheckStmt->get_result()->fetch_assoc();
        $birthCheckStmt->close();
        $isBirthdayTodayForUser = !empty($birthCheckRow['md']) && $birthCheckRow['md'] === date('m-d');
    }
}

function localizeBonusDescription($desc, $lang, $bonusTrigger) {
    $source = trim((string)$desc);
    $huFreeBet = 'Ha egy legalább 5.000 Ft-os fogadásod veszít (min. odds: 1.80), visszakapsz 30%-ot Free Bet formájában. Naponta egyszer aktiválódik automatikusan a vesztes szelvény lezárásakor. A kapott Free Bet-et bármilyen fogadásra felhasználhatod.';
    $enFreeBet = 'If a bet of at least 5,000 Ft loses (min. odds: 1.80), you get 30% back as a Free Bet. It is automatically activated once per day when the losing ticket is settled. You can use the received Free Bet on any bet.';

    if ($lang === 'en') {
        if ((string)$bonusTrigger === 'LOSS') {
            return $enFreeBet;
        }

        if ($source !== '' && mb_stripos($source, 'Ha egy legalább 5.000 Ft-os fogadásod veszít') !== false) {
            return $enFreeBet;
        }
    }

    if ($lang === 'hu' && (string)$bonusTrigger === 'LOSS') {
        return $huFreeBet;
    }

    return $source;
}

// Sémakompatibilitás: régebbi DB-ben egyes oszlopok hiányozhatnak.
$hasImageUrlCol = false;
$hasBirthdayBonusCol = false;

$colStmt = $conn->prepare(" 
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'BonusCodes'
            AND COLUMN_NAME IN ('image_url', 'birthday_bonus')
");
if ($colStmt) {
        $colStmt->execute();
        $colRes = $colStmt->get_result();
        while ($c = $colRes->fetch_assoc()) {
                if (($c['COLUMN_NAME'] ?? '') === 'image_url') {
                        $hasImageUrlCol = true;
                }
                if (($c['COLUMN_NAME'] ?? '') === 'birthday_bonus') {
                        $hasBirthdayBonusCol = true;
                }
        }
        $colStmt->close();
}

$imageSelect = $hasImageUrlCol ? 'image_url' : "'' AS image_url";
$birthdaySelect = $hasBirthdayBonusCol ? 'birthday_bonus' : '0 AS birthday_bonus';

// Lekérdezés: csak aktív bónuszok
$query = "SELECT id, code, name, description, {$imageSelect}, {$birthdaySelect}, bonus_amount, min_deposit, max_bonus_amount, match_percent, 
                                 is_step_bonus, step_number, bonus_type_id, valid_weekdays_only, is_active,
                                 daily_start_time, admin_force_active, sport_restriction, bonus_trigger, per_user_limit
                    FROM BonusCodes 
                    WHERE is_active = 1
                        AND (
                            code IS NULL
                            OR code NOT IN ('TOP_REWARD_DAILY', '__ADMIN_FREEBET__', '__ADMIN_BONUS__')
                        )
                    ORDER BY id ASC";

$result = $conn->query($query);
$bonuses = [];

// Cache: live sportok ellenőrzése (sport_restriction-ös bónuszokhoz)
$liveSportsCache = null;
function hasLiveSport($conn, $sportName, &$cache) {
    if ($cache === null) {
        $cache = [];
        $r = $conn->query("
            SELECT UPPER(s.name) AS sport_name, COUNT(*) AS cnt
            FROM Events e
            JOIN Sports s ON e.sport_id = s.id
            WHERE e.is_live = 1
            GROUP BY s.id
        ");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $cache[$row['sport_name']] = (int)$row['cnt'];
            }
        }
    }
    return ($cache[strtoupper($sportName)] ?? 0) > 0;
}

$todaySportsCache = null;
function hasTodaySport($conn, $sportName, &$cache) {
    $normalizedSport = strtoupper((string)$sportName);

    // Esportnál ne sportnév-szövegre támaszkodjunk, mert eltérhet (pl. Esports/E-Sport).
    if ($normalizedSport === 'ESPORT') {
        if (!array_key_exists('ESPORT', $cache ?? [])) {
            $dayStartBp = new DateTime('today 00:00:00', new DateTimeZone('Europe/Budapest'));
            $dayEndBp = new DateTime('today 23:59:59', new DateTimeZone('Europe/Budapest'));
            $dayStartBp->setTimezone(new DateTimeZone('UTC'));
            $dayEndBp->setTimezone(new DateTimeZone('UTC'));
            $fromUtc = $dayStartBp->format('Y-m-d H:i:s');
            $toUtc = $dayEndBp->format('Y-m-d H:i:s');

            $stmtEsport = $conn->prepare(" 
                SELECT COUNT(*) AS cnt
                FROM Events e
                JOIN Sports s ON e.sport_id = s.id
                WHERE e.start_time BETWEEN ? AND ?
                  AND s.api_id = 145
            ");
            $cache = $cache ?? [];
            if ($stmtEsport) {
                $stmtEsport->bind_param('ss', $fromUtc, $toUtc);
                $stmtEsport->execute();
                $resEsport = $stmtEsport->get_result()->fetch_assoc();
                $cache['ESPORT'] = (int)($resEsport['cnt'] ?? 0);
                $stmtEsport->close();
            } else {
                $cache['ESPORT'] = 0;
            }
        }

        return ($cache['ESPORT'] ?? 0) > 0;
    }

    if ($cache === null) {
        $cache = [];

        $dayStartBp = new DateTime('today 00:00:00', new DateTimeZone('Europe/Budapest'));
        $dayEndBp = new DateTime('today 23:59:59', new DateTimeZone('Europe/Budapest'));
        $dayStartBp->setTimezone(new DateTimeZone('UTC'));
        $dayEndBp->setTimezone(new DateTimeZone('UTC'));
        $fromUtc = $dayStartBp->format('Y-m-d H:i:s');
        $toUtc = $dayEndBp->format('Y-m-d H:i:s');

        $stmt = $conn->prepare(" 
            SELECT UPPER(s.name) AS sport_name, COUNT(*) AS cnt
            FROM Events e
            JOIN Sports s ON e.sport_id = s.id
            WHERE e.start_time BETWEEN ? AND ?
            GROUP BY s.id
        ");
        if ($stmt) {
            $stmt->bind_param('ss', $fromUtc, $toUtc);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $cache[$row['sport_name']] = (int)$row['cnt'];
            }
            $stmt->close();
        }
    }

    return ($cache[$normalizedSport] ?? 0) > 0;
}

// Többszörös bónusz rendszer: nincs egyszerre-egy-bónusz korlátozás.
// Minden bónusznak saját egyenlege van (UserBonuses.bonus_balance).
$hasExistingBonus = false; // Kompatibilitás megtartása a frontend felé

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bonusName = (string)($row['name'] ?? '');
        $isBetmatchBirthdayByName = (bool)preg_match('/^BETMATCH(?:\s*BONUS)?\s+SZ[ÜU]LET[ÉE]SNAPI\s+B[ÓO]NUSZ/ui', $bonusName);

        if ($isBetmatchBirthdayByName && !$isBetmatchBonusDay) {
            continue;
        }

        $isBirthdayBonus = ((int)($row['birthday_bonus'] ?? 0) === 1);
        $isBetmatchBirthdayBonus = (bool)preg_match('/^BETMATCH(?:\s*BONUS)?\s+SZ[ÜU]LET[ÉE]SNAPI\s+B[ÓO]NUSZ/ui', $bonusName);

        // Születésnapi bónuszok megjelenítése:
        // - BetMatchBonus születésnapi bónusz: csak bejelentkezve + május 26-án
        // - Standard születésnapi bónusz: csak bejelentkezve + aznapi felhasználói születésnap
        if ($isBirthdayBonus) {
            if ($isGuest) {
                continue;
            }

            if ($isBetmatchBirthdayBonus) {
                if (!$isBetmatchBonusDay) {
                    continue;
                }
            } else {
                if (!$isBirthdayTodayForUser) {
                    continue;
                }
            }
        }

        if (!$isGuest) {
            // Hétköznapi bónusz láthatóság: hétköznap + daily_start_time után, VAGY admin_force_active
            if ((int)$row['valid_weekdays_only'] === 1 && empty($row['admin_force_active'])) {
                $dailyStart = $row['daily_start_time'] ?? null;
                $isAfterDailyStart = ($dailyStart === null || date('H:i:s') >= $dailyStart);
                $isWeekdayWindow = ($isWeekday && $isAfterDailyStart);
                if (!$isWeekdayWindow) {
                    continue;
                }
            }

            // Hétvégi bónusz csak szombat-vasárnap legyen látható (admin force átugorja).
            $bonusCode = strtoupper((string)($row['code'] ?? ''));
            if ($bonusCode === 'HETVEGI5K' && empty($row['admin_force_active']) && !$isWeekend) {
                continue;
            }

            // Ha már van aktív/várakozó példány, ne kínáljuk fel újra a bónuszt.
            $activeInstanceStmt = $conn->prepare(" 
                SELECT COUNT(*) AS cnt
                FROM UserBonuses
                WHERE user_id = ?
                  AND bonus_id = ?
                  AND status IN ('ACTIVE', 'PENDING')
                  AND used = 0
                  AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $activeInstanceStmt->bind_param("ii", $userId, $row['id']);
            $activeInstanceStmt->execute();
            $activeRow = $activeInstanceStmt->get_result()->fetch_assoc();
            $activeInstanceCount = (int)($activeRow['cnt'] ?? 0);
            $activeInstanceStmt->close();

            $perUserLimit = (int)($row['per_user_limit'] ?? 1);
            $hasLimit = ($perUserLimit > 0);

            // Elért összes beváltás esetén ne listázzuk újra a bónuszt.
            $claimCountStmt = $conn->prepare(" 
                SELECT COUNT(*) AS cnt
                FROM UserBonuses
                WHERE user_id = ? AND bonus_id = ?
            ");
            $claimCountStmt->bind_param("ii", $userId, $row['id']);
            $claimCountStmt->execute();
            $claimCountRow = $claimCountStmt->get_result()->fetch_assoc();
            $claimCountStmt->close();
            $claimCount = (int)($claimCountRow['cnt'] ?? 0);

            if ($hasLimit && $claimCount >= $perUserLimit) {
                continue;
            }

            // Többször használható bónusznál csak akkor rejtjük el,
            // ha az aktív/pending példányok száma elérte a limitet.
            if ($hasLimit && $activeInstanceCount >= $perUserLimit) {
                continue;
            }

            // Hétköznapi napi bónusz ne jelenjen meg, ha ma már igényelték
            if ($userId > 0 && (int)$row['valid_weekdays_only'] === 1) {
                $ds = $row['daily_start_time'] ?? '00:01:00';
                $bonusTodayFrom = date('Y-m-d') . ' ' . $ds;
                $bonusTomorrowFrom = date('Y-m-d', strtotime('+1 day')) . ' ' . $ds;
                $claimedTodayStmt = $conn->prepare(" 
                    SELECT id
                    FROM UserBonuses
                    WHERE user_id = ?
                      AND bonus_id = ?
                      AND created_at >= ?
                      AND created_at < ?
                    LIMIT 1
                ");
                $claimedTodayStmt->bind_param("iiss", $userId, $row['id'], $bonusTodayFrom, $bonusTomorrowFrom);
                $claimedTodayStmt->execute();
                $claimedTodayRes = $claimedTodayStmt->get_result();
                $alreadyClaimedToday = $claimedTodayRes->num_rows > 0;
                $claimedTodayStmt->close();

                if ($alreadyClaimedToday) {
                    continue;
                }
            }
        }

        // Sport-specifikus bónuszok láthatósága:
        // - BET trigger esetén: legyen az adott sportból MAI (naptári nap) esemény.
        // - egyébként: legyen az adott sportból élő esemény.
        $sportRestriction = $row['sport_restriction'] ?? null;
        if ($sportRestriction && $sportRestriction !== 'ANY') {
            $bonusTrigger = strtoupper((string)($row['bonus_trigger'] ?? ''));
            if ($bonusTrigger === 'BET') {
                if (!hasTodaySport($conn, $sportRestriction, $todaySportsCache)) {
                    continue; // Nincs mai esemény ebből a sportból → ne jelenjen meg
                }
            } else {
                if (!hasLiveSport($conn, $sportRestriction, $liveSportsCache)) {
                    continue; // Nincs élő meccs ebből a sportból → ne jelenjen meg
                }
            }
        }

        $isStepBonus = ((int)($row['is_step_bonus'] ?? 0) === 1);
        $matchPercent = (float)($row['match_percent'] ?? 0);
        $maxBonusAmount = (float)($row['max_bonus_amount'] ?? 0);
        $bonusAmount = (float)($row['bonus_amount'] ?? 0);
        $minDeposit = (float)($row['min_deposit'] ?? 0);

        $amountText = $lang === 'en' ? 'Bonus offer' : 'Bónusz ajánlat';
        if ($matchPercent > 0 && $maxBonusAmount > 0) {
            $amountText = number_format($matchPercent, 0, '', ' ') . '% max ' . number_format($maxBonusAmount, 0, '', ' ') . ' FT';
        } elseif ($bonusAmount > 0) {
            $amountText = number_format($bonusAmount, 0, '', ' ') . ' FT';
        }

        $conditionText = '';
        $bonusTrigger = $row['bonus_trigger'] ?? 'DEPOSIT';
        if ($bonusTrigger === 'BET') {
            $conditionText = ($lang === 'en' ? 'Min. stake: ' : 'Min. tét: ') . number_format($minDeposit, 0, '', ' ') . ' FT';
        } else {
            $conditionText = ($lang === 'en' ? 'Min. deposit: ' : 'Min. befizetés: ') . number_format($minDeposit, 0, '', ' ') . ' FT';
        }
        if ($isStepBonus) {
            $conditionText .= $lang === 'en' ? ' | Multi-step bonus' : ' | Több lépcsős bónusz';
        }

        $perUserLimit = (int)($row['per_user_limit'] ?? 1);
        if ($perUserLimit > 1) {
            $conditionText .= $lang === 'en'
                ? (' | Usable up to ' . $perUserLimit . ' times')
                : (' | Felhasználható: max. ' . $perUserLimit . ' alkalom');
        }

        $longDescription = localizeBonusDescription($row['description'] ?? '', $lang, $row['bonus_trigger'] ?? 'DEPOSIT');
        if ($isBetmatchBirthdayByName && $lang === 'hu') {
            $longDescription = 'A BetMatchBonus születésnapi különleges promóciója - limitált számban elérhető! Hogyan működik? 1) A promóció évente május 26-án aktiválódik. 2) Ezen a napon az első 500 jogosult igénylés teljesülhet. 3) A bónusszal bármilyen sportra, bármilyen mérkőzésre fogadhatsz - nincs sportági megkötés. 4) Nincs forgatási követelmény, a nyereményed azonnal kifizethetővé válik. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: csak 500 db érhető el összesen.';
        }

        $bonuses[] = [
            'id' => $row['id'],
            'code' => $row['code'],
            'title' => $row['name'],
            'isBirthdayBonus' => $isBirthdayBonus,
            'amount' => $amountText,
            'condition' => $conditionText,
            'isStepBonus' => $isStepBonus,
            'status' => $isGuest ? null : ($lang === 'en' ? 'ACTIVE' : 'AKTÍV'),
            'longDescription' => $longDescription,
            'image' => !empty($row['image_url']) ? $row['image_url'] : '../../img/logo.png',
            'hasExistingBonus' => $hasExistingBonus,
            'sportRestriction' => ($sportRestriction && $sportRestriction !== 'ANY') ? $sportRestriction : null,
            'bonusTrigger' => $bonusTrigger,
            'perUserLimit' => (int)($row['per_user_limit'] ?? 1)
        ];
    }
}

echo json_encode($bonuses);