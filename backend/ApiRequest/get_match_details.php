<?php
require_once __DIR__ . "/connect.php";

header('Content-Type: application/json; charset=utf-8');

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;

if ($eventId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Hiányzó vagy érvénytelen eventId']);
    exit;
}

$apiBaseUrl = "http://localhost:5000/api";

// Próba 1: /matches/event?eventId=
$eventUrl = "$apiBaseUrl/matches/event?eventId=$eventId";
$ch = curl_init($eventUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Próba 2: Ha az első nem működik, próbáljuk /matches/{id}
if ($httpCode !== 200 || !$response) {
    $eventUrl = "$apiBaseUrl/matches/$eventId";
    $ch = curl_init($eventUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

// Próba 3: Ha még mindig nem működik, próbáljuk /matches?id=
if ($httpCode !== 200 || !$response) {
    $eventUrl = "$apiBaseUrl/matches?id=$eventId";
    $ch = curl_init($eventUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
}

if ($httpCode !== 200 || !$response) {
    http_response_code(404);
    echo json_encode(['error' => 'Meccs nem található az API-ban (eventId: ' . $eventId . '). Próbált végpontok: /matches/event?eventId=, /matches/{id}, /matches?id=']);
    exit;
}

$apiData = json_decode($response, true);
if (!is_array($apiData)) {
    http_response_code(500);
    echo json_encode(['error' => 'API válasz parse hiba']);
    exit;
}

// Válasz összeállítása
$response_data = [
    'match' => [
        'id' => (int)($apiData['id'] ?? $eventId),
        'name' => $apiData['name'] ?? 'Ismeretlen meccs',
        'homeTeam' => $apiData['homeTeam'] ?? '',
        'awayTeam' => $apiData['awayTeam'] ?? '',
        'score' => '',
        'isLive' => !empty($apiData['isLive']) ? true : false,
        'liveTime' => $apiData['liveTime'] ?? null,
        'liveStatus' => $apiData['liveStatus'] ?? null,
        'country' => $apiData['countryCode'] ?? 'Ismeretlen',
        'championship' => $apiData['leagueName'] ?? 'Ismeretlen',
        'startUtc' => $apiData['startDateUtc'] ?? null,
    ],
    'markets' => []
];

// Eredmény összeállítása - több formátumot kezelünk
if (isset($apiData['score']) && is_array($apiData['score']) && count($apiData['score']) >= 2) {
    $response_data['match']['score'] = $apiData['score'][0] . ' - ' . $apiData['score'][1];
} elseif (isset($apiData['score']) && is_string($apiData['score'])) {
    // Ha string formátumban jön (pl. "1:1" vagy "1 - 1")
    $response_data['match']['score'] = str_replace(':', ' - ', $apiData['score']);
} elseif (isset($apiData['homeScore']) && isset($apiData['awayScore'])) {
    $response_data['match']['score'] = $apiData['homeScore'] . ' - ' . $apiData['awayScore'];
} elseif (isset($apiData['scores']) && is_array($apiData['scores'])) {
    // Néha "scores" kulcs alatt jön
    if (isset($apiData['scores']['home']) && isset($apiData['scores']['away'])) {
        $response_data['match']['score'] = $apiData['scores']['home'] . ' - ' . $apiData['scores']['away'];
    } elseif (count($apiData['scores']) >= 2) {
        $response_data['match']['score'] = $apiData['scores'][0] . ' - ' . $apiData['scores'][1];
    }
} else {
    // Ha sehogy nem találjuk, próbáljuk az élő meccsek API-ból lekérni az eredményt
    $liveUrl = "$apiBaseUrl/matches/live";
    $chLive = curl_init($liveUrl);
    curl_setopt($chLive, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chLive, CURLOPT_TIMEOUT, 5);
    $liveResponse = curl_exec($chLive);
    $liveHttpCode = curl_getinfo($chLive, CURLINFO_HTTP_CODE);
    curl_close($chLive);
    
    $foundScore = false;
    if ($liveHttpCode === 200 && $liveResponse) {
        $liveMatches = json_decode($liveResponse, true);
        if (is_array($liveMatches)) {
            foreach ($liveMatches as $liveMatch) {
                if (isset($liveMatch['id']) && (int)$liveMatch['id'] === $eventId) {
                    if (isset($liveMatch['score']) && is_array($liveMatch['score']) && count($liveMatch['score']) >= 2) {
                        $response_data['match']['score'] = $liveMatch['score'][0] . ' - ' . $liveMatch['score'][1];
                        // Frissítsük a liveTime-ot is ha van
                        if (isset($liveMatch['liveTime'])) {
                            $response_data['match']['liveTime'] = $liveMatch['liveTime'];
                        }
                        $foundScore = true;
                    }
                    break;
                }
            }
        }
    }
    
    if (!$foundScore) {
        $response_data['match']['score'] = '0 - 0';
    }
}

// Piacok feldolgozása
if (isset($apiData['markets']) && is_array($apiData['markets'])) {
    $seen = [];
    
    foreach ($apiData['markets'] as $market) {
        $marketName = $market['name'] ?? '';
        $specialVal = $market['specialValue'] ?? null;
        $marketKey = $marketName . '||' . ($specialVal ?? '');
        
        // Duplikátum szűrés
        if (isset($seen[$marketKey])) {
            continue;
        }
        $seen[$marketKey] = true;
        
        $marketData = [
            'name' => $marketName,
            'specialValue' => $specialVal,
            'selections' => []
        ];
        
        // Selections feldolgozása
        if (isset($market['selections']) && is_array($market['selections'])) {
            $seenSel = [];
            
            foreach ($market['selections'] as $selection) {
                $selName = $selection['name'] ?? '';
                
                // Duplikátum szűrés selections-ben
                if (isset($seenSel[$selName])) {
                    continue;
                }
                $seenSel[$selName] = true;
                
                $marketData['selections'][] = [
                    'name' => $selName,
                    'odds' => (float)($selection['odd'] ?? 1.0)
                ];
            }
        }
        
        // Piac csak akkor kerül be, ha van legalább 1 selection
        if (!empty($marketData['selections'])) {
            $response_data['markets'][] = $marketData;
        }
    }
}

echo json_encode($response_data, JSON_UNESCAPED_UNICODE);
?>
