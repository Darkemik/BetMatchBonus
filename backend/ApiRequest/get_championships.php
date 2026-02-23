<?php
require_once "../db.php"; // DB kapcsolat

// API endpoint
$url = "http://localhost:5000/api/sports/championships";

// cURL indítása
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

// JSON decode
$data = json_decode($response, true);

// Ha nem tömb → hiba
if (!is_array($data)) {
    die("API HIBA: nem tömb érkezett.");
}

// Végigmegyünk a bajnokságokon
foreach ($data as $champ) {

    $api_id = $champ['id'];
    $sport_id = $champ['sportId'];
    $countryCode = $champ['countryCode'];
    $name = $champ['name'];

    // beszúrás (vagy kihagyás, ha már megvan)
    $stmt = $mysqli->prepare("
        INSERT INTO Championships (api_id, sport_id, country_code, name)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name)
    ");

    $stmt->bind_param("iiss", $api_id, $sport_id, $countryCode, $name);
    $stmt->execute();
}

echo "KÉSZ: Bajnokságok betöltve.";
?>