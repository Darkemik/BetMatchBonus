<?php
require_once dirname(__DIR__) . '/connect.php';

// Darts bónusz aktuális értékek lekérdezése
$stmt = $conn->prepare("SELECT id, code, name, live_only, evaluate_on_settle, sport_restriction FROM BonusCodes WHERE code = 'DARTSBONUSZ5K'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
echo "Darts bonusz ELŐTTE: " . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

// live_only = 0 biztosítása
$fixStmt = $conn->prepare("UPDATE BonusCodes SET live_only = 0 WHERE code = 'DARTSBONUSZ5K'");
$fixStmt->execute();
echo "live_only fix affected_rows = " . $fixStmt->affected_rows . PHP_EOL;
$fixStmt->close();

// Ellenőrzés
$stmt2 = $conn->prepare("SELECT id, code, name, live_only, evaluate_on_settle, sport_restriction FROM BonusCodes WHERE code = 'DARTSBONUSZ5K'");
$stmt2->execute();
$row2 = $stmt2->get_result()->fetch_assoc();
$stmt2->close();
echo "Darts bonusz UTÁNA: " . json_encode($row2, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
