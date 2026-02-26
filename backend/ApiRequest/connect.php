<?php
$host = "localhost";
$user = "root";      // XAMPP alapértelmezett
$pass = "";          // ha nincs jelszó
$db   = "betmatchbonusbeta";

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Adatbázis hiba: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");
?>