<?php
require_once "connect.php";

// 1) TÁBLÁK ÜRÍTÉSE
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
$conn->query("TRUNCATE TABLE Matches;");
$conn->query("TRUNCATE TABLE Championships;");
$conn->query("SET FOREIGN_KEY_CHECKS = 1;");

// 2) BAJNOKSÁGOK IMPORT
require_once "get_championships.php";

// 3) ÉLŐ MECCSEK IMPORT
require_once "get_matches_live.php";

// 4) NAPI MECCSEK (HA KELL)
require_once "get_matches_date.php";

echo "Sikeres reset + import!";