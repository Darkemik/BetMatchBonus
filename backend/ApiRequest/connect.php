<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "betmatchbonusbeta";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");