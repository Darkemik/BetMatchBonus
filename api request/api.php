<?php
$url = "http://localhost:5000/api/sports/championships";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);

if ($response === false) {
  die("cURL hiba: " . curl_error($ch));
}

$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $http\n\n";
header("Content-Type: application/json; charset=utf-8");
echo $response;
