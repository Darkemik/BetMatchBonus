<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Regisztráció - 2. lépés</title>
</head>
<body>

<h1>Regisztráció megerősítése</h1>

<p>Email: <b><?php echo htmlspecialchars($email); ?></b></p>
<p>Felhasználónév: <b><?php echo htmlspecialchars($username); ?></b></p>
<p>Születési dátum: <b><?php echo htmlspecialchars($age); ?></b></p>

<br>

<!-- Továbbküldés a végleges mentéshez -->
<form action="save.php" method="POST">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
    <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
    <input type="hidden" name="age" value="<?php echo htmlspecialchars($age); ?>">

    <button type="submit">Véglegesítés</button>
</form>

<br>

<a href="register.php">Vissza</a>

</body>
</html>
<?php
echo "<pre>";
print_r($_POST);
echo "</pre>";
exit;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: register.php");
  exit;
}

$username = $_POST['username'] ?? '';
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$age      = $_POST['age'] ?? '';
$terms    = $_POST['terms'] ?? null;

if ($username === '' || $email === '' || $password === '' || $age === '' || !$terms) {
  die("Hiányzó adat vagy nincs elfogadva a feltétel.");
}
?>