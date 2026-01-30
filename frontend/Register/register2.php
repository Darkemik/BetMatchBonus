<?php


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: register.php");
  exit;
}

$username = $_POST['username'] ?? null;
$email    = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$age      = $_POST['age'] ?? null;
$terms    = $_POST['terms'] ?? null;


if ($username === '' || $email === '' || $password === '' || $age === '' || !$terms) {
  die("Hiányzó adat vagy nincs elfogadva a feltétel.");
  
}
?>


<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztráció - 2. lépés</title>
    <link rel="stylesheet" href="../../css/Register/register2.css">
</head>
<body>

<h1>Regisztráció Folytatása</h1>

<p>Email: <b><?php echo htmlspecialchars($email); ?></b></p>
<p>Felhasználónév: <b><?php echo htmlspecialchars($username); ?></b></p>
<p>Születési dátum: <b><?php echo htmlspecialchars($age); ?></b></p>

<br>


<form action="save.php" method="POST">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
    <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
    <input type="hidden" name="age" value="<?php echo htmlspecialchars($age); ?>">

    <button type="submit">Folytatás</button>
</form>

<br>

<a href="register.php">Vissza</a>

</body>
</html>
