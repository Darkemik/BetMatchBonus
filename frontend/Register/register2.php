<?php


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: register.php");
  exit;
}

$username = $_POST['username'] ?? null;
$email    = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$calculated_age = $_POST['calculated_age'] ?? '';
$terms_rules  = isset($_POST['terms_rules']);
$terms_privacy = isset($_POST['terms_privacy']);

if ($username === '' || $email === '' || $password === '' || $calculated_age === '' || !$terms_rules || !$terms_privacy) {
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
<p>Életkor: <b><?php echo htmlspecialchars($calculated_age); ?></b></p>

<br>


<form action="register3.php" method="POST">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
    <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
    <input type="hidden" name="calculated_age" value="<?php echo htmlspecialchars($calculated_age); ?>">
    <button type="submit" onclick="location.href='registe3.php'">Folytatás</button>
</form>

<br>

<a href="register.php">Vissza</a>

</body>
</html>
