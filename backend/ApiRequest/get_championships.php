<?php
require_once "connect.php";

// 1) ÖSSZES sport lekérése az API-ból
$urlSports = "http://localhost:5000/api/sports";

$ch = curl_init($urlSports);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$sportsResponse = curl_exec($ch);
if ($sportsResponse === false) {
    die("cURL hiba (sports): " . curl_error($ch));
}
curl_close($ch);

$sports = json_decode($sportsResponse, true);
if (!is_array($sports)) {
    die("API HIBA: sports nem tömb.");
}

/**
 * ÚJ: Country upsert statement (FK miatt kell!)
 * Ha nincs countryCode → INT / International
 */
$stmtUpsertCountry = $conn->prepare("
    INSERT INTO Countries (code, name)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE name = VALUES(name)
");
if (!$stmtUpsertCountry) {
    die("SQL hiba (stmtUpsertCountry): " . $conn->error);
}

// 2) Prepared statement a bajnokság upsert-re
$stmtUpsertChamp = $conn->prepare("
    INSERT INTO Championships (api_id, sport_id, country_code, name)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        sport_id = VALUES(sport_id),
        country_code = VALUES(country_code),
        name = VALUES(name)
");
if (!$stmtUpsertChamp) {
    die("SQL hiba (stmtUpsertChamp): " . $conn->error);
}

// 3) Minden sporthoz: bajnokságok importja
foreach ($sports as $sport) {
    $sportId   = (int)$sport['id'];
    $sportName = $sport['name'] ?? '';

    $urlChamps = "http://localhost:5000/api/sports/championships?sportId=" . $sportId;

    $ch = curl_init($urlChamps);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $champsResponse = curl_exec($ch);

    if ($champsResponse === false) {
        echo "cURL hiba sportId=$sportId ($sportName): " . curl_error($ch) . "\n";
        curl_close($ch);
        continue;
    }
    curl_close($ch);

    $champs = json_decode($champsResponse, true);
    if (!is_array($champs)) {
        echo "API HIBA: championships nem tömb sportId=$sportId ($sportName)\n";
        continue;
    }

    foreach ($champs as $champ) {
        $apiChampId = (int)($champ['id'] ?? 0);
        $champName  = (string)($champ['name'] ?? '');

        // Ha nincs countryCode → INT / International
        $countryCode = trim((string)($champ['countryCode'] ?? ''));
        if ($countryCode === '') {
            $countryCode = 'INT';
        }

        // Country upsert: INT → International, egyébként fallback név = kód
        $countryName = ($countryCode === 'INT') ? 'International' : $countryCode;

        $stmtUpsertCountry->bind_param("ss", $countryCode, $countryName);
        $stmtUpsertCountry->execute();

        // Championship upsert (FK már ok)
        $stmtUpsertChamp->bind_param("iiss", $apiChampId, $sportId, $countryCode, $champName);
        $stmtUpsertChamp->execute();
    }

    echo "Importálva: sportId=$sportId ($sportName) bajnokságai.\n";
}

$stmtUpsertChamp->close();
$stmtUpsertCountry->close();

echo "Minden sport bajnokságai frissítve.\n";