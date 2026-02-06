<?php


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: register2.php");
  exit;
}

$username = $_POST['username'] ?? null;
$email    = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$calculated_age = $_POST['calculated_age'] ?? '';
$terms_rules  = isset($_POST['terms_rules']);
$terms_privacy = isset($_POST['terms_privacy']);


?>




<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
<h1>Regisztráció Befejezése</h1>

<p>Email: <b><?php echo htmlspecialchars($email); ?></b></p>
<p>Felhasználónév: <b><?php echo htmlspecialchars($username); ?></b></p>
<p>Életkor: <b><?php echo htmlspecialchars($calculated_age); ?></b></p>

<br>


<form action="save.php" method="POST">
    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
    <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">
    <input type="hidden" name="calculated_age" value="<?php echo htmlspecialchars($calculated_age); ?>">
    <button type="submit">Folytatás</button>
</form>
</body>
</html>