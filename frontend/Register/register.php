<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regisztráció</title>
    <link rel="stylesheet" href="../css/register.css">
</head>
<body>
    <div class="container">
        <form id="registerForm" action="../php/register.php" method="POST">
            <h1>Regisztráljon hozzánk!</h1>
            <input class="inputok" type="text" name="username" placeholder="Felhasználónév" id="username" required>
            <input class="inputok" type="email" name="email" placeholder="Email"  id="email" required>
            <input class="inputok" type="password" name="password" placeholder="Jelszó"  id="password" required>
            <p>A jelszó nem elég erős</p>
            <input class="inputok" type="date" name="age" placeholder="Életkor" id="age" required>
            <button class="regisztraciobtn" type="submit" id="regisztracio">Folytatás</button>
            
           <p>Már van fiókod?</p><a href="login.html">Jelentkezz be!</a>
        </form>

        <form action="">
            <div class="container1">
                <button class="visszabtn">Vissza</button>
                <p>A *-al jelölt mezők kitöltése kötelező!</p>
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">Elolvastam és elfogadom a <a href="../frontend/help.html">Felhasználási feltételeket*</a></label><br>
            </div>
        </form>
    </div>
    <script src="../js/registration.js"></script>
</body>
</html>
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
