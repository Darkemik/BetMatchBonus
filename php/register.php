<?php

// adatbázis kapcsolat
$conn = new mysqli("localhost", "root", "", "bettingdb");

// ha hiba van
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// form adatok
$email = $_POST['email'];
$password = $_POST['password'];

// jelszó hash
$hash = password_hash($password, PASSWORD_BCRYPT);

// adatbázis INSERT
$sql = "INSERT INTO users (email, password_hash)
        VALUES ('$email', '$hash')";

if ($conn->query($sql) === TRUE) {
    echo "Sikeres regisztráció!";
} else {
    echo "Hiba: " . $conn->error;
}

$conn->close();
?>
