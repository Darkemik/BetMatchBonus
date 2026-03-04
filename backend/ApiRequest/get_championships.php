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

// 2) Prepared statement a bajnokság upsert-re
$stmtUpsertChamp = $conn->prepare("
    INSERT INTO Championships (api_id, sport_id, country_code, name)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        sport_id = VALUES(sport_id),
        country_code = VALUES(country_code),
        name = VALUES(name)
");

// 3) Minden sporthoz: bajnokságok importja
foreach ($sports as $sport) {
    $sportId   = $sport['id'];   // pl. 66 foci, 67 kosár, 70 jégkorong...
    $sportName = $sport['name'];

    // Ha akarsz, itt szűrhetsz csak bizonyos sportokra
    // if ($sportId != 66 && $sportId != 67) continue;

    $urlChamps = "http://localhost:5000/api/sports/championships?sportId=" . $sportId;

    $ch = curl_init($urlChamps);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $champsResponse = curl_exec($ch);

    if ($champsResponse === false) {
        echo "cURL hiba sportId=$sportId ($sportName): " . curl_error($ch) . "\n";
        continue;
    }
    curl_close($ch);

    $champs = json_decode($champsResponse, true);
    if (!is_array($champs)) {
        echo "API HIBA: championships nem tömb sportId=$sportId ($sportName)\n";
        continue;
    }

    foreach ($champs as $champ) {
        $apiChampId  = $champ['id'];          // API-beli leagueId
        $countryCode = $champ['countryCode'] ?: null;
        $champName   = $champ['name'];

        $stmtUpsertChamp->bind_param(
            "iiss",
            $apiChampId,
            $sportId,
            $countryCode,
            $champName
        );
        $stmtUpsertChamp->execute();
    }

    echo "Importálva: sportId=$sportId ($sportName) bajnokságai.\n";
}

$stmtUpsertChamp->close();

echo "Minden sport bajnokságai frissítve.\n";