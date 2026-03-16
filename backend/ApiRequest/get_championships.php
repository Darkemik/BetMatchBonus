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

$stmtSelectCountry = $conn->prepare("
    SELECT id FROM Countries WHERE code = ?
");
if (!$stmtSelectCountry) {
    die("SQL hiba (stmtSelectCountry): " . $conn->error);
}

// 2) Prepared statement a verseny upsert-re
$stmtUpsertComp = $conn->prepare("
    INSERT INTO Competitions (api_id, sport_id, country_id, name)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        sport_id = VALUES(sport_id),
        country_id = VALUES(country_id),
        name = VALUES(name)
");
if (!$stmtUpsertComp) {
    die("SQL hiba (stmtUpsertComp): " . $conn->error);
}

// Sport belső id keresése / létrehozása (api_id → Sports.id)
$stmtFindSport = $conn->prepare("SELECT id FROM Sports WHERE api_id = ?");
if (!$stmtFindSport) {
    die("SQL hiba (stmtFindSport): " . $conn->error);
}
$stmtInsertSport = $conn->prepare("INSERT INTO Sports (api_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
if (!$stmtInsertSport) {
    die("SQL hiba (stmtInsertSport): " . $conn->error);
}

// 3) Minden sporthoz: bajnokságok importja
foreach ($sports as $sport) {
    $sportId   = (int)$sport['id'];
    $sportName = $sport['name'] ?? '';

    // API sport id → belső Sports.id
    $stmtFindSport->bind_param("i", $sportId);
    $stmtFindSport->execute();
    $resSport = $stmtFindSport->get_result();
    if ($rowSport = $resSport->fetch_assoc()) {
        $internalSportId = (int)$rowSport['id'];
    } else {
        $stmtInsertSport->bind_param("is", $sportId, $sportName);
        $stmtInsertSport->execute();
        $internalSportId = (int)$stmtInsertSport->insert_id;
    }

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

        // Country id lekérése
        $stmtSelectCountry->bind_param("s", $countryCode);
        $stmtSelectCountry->execute();
        $countryResult = $stmtSelectCountry->get_result();
        $countryRow = $countryResult->fetch_assoc();
        $countryId = $countryRow ? (int)$countryRow['id'] : null;

        // Competition upsert (belső sport_id alapján)
        $stmtUpsertComp->bind_param("iiis", $apiChampId, $internalSportId, $countryId, $champName);
        $stmtUpsertComp->execute();
    }

    echo "Importálva: sportId=$sportId ($sportName) bajnokságai.\n";
}

$stmtUpsertComp->close();
$stmtSelectCountry->close();
$stmtUpsertCountry->close();
$stmtFindSport->close();
$stmtInsertSport->close();

echo "Minden sport bajnokságai frissítve.\n";