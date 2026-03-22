<?php
require_once "connect.php";
header('Content-Type: application/json; charset=utf-8');

// Csak az aktív bónuszokat kérjük le
$query = "SELECT id, code, name, description, bonus_amount, min_deposit, bet_reward_type 
          FROM BonusCodes 
          WHERE is_active = 1 
          ORDER BY id DESC";

$result = $conn->query($query);
$bonuses = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Formázzuk a kiírást a frontend számára
        $bonuses[] = [
            'id' => $row['id'],
            'code' => $row['code'], // Ha null, akkor backendben lekezeljük
            'title' => $row['name'],
            'amount' => $row['bonus_amount'] > 0 ? number_format($row['bonus_amount'], 0, '', ' ') . ' FT' : 'Több lépcsős',
            'condition' => "Min. befizetés: " . number_format($row['min_deposit'], 0, '', ' ') . " FT",
            'longDescription' => $row['description'],
            // Ide jöhet valami generikus kép, vagy bevezethetünk egy 'image_url' oszlopot később. Most fix képet adok:
            'image' => '../../img/logo.png' 
        ];
    }
}

echo json_encode($bonuses);