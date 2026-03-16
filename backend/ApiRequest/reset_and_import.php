<?php
require_once "connect.php";

// 1) TÁBLÁK ÜRÍTÉSE
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
$conn->query("TRUNCATE TABLE Events;");
$conn->query("TRUNCATE TABLE Competitions;");
$conn->query("SET FOREIGN_KEY_CHECKS = 1;");

echo "Táblák ürítve.\n";

// 2) BAJNOKSÁGOK IMPORT
include "get_championships.php";

// 3) ÉLŐ MECCSEK IMPORT
include "get_matches_live.php";

// 4) NAPI MECCSEK (HA KELL)
include "get_matches_date.php";

echo "\nSikeres reset + import!";