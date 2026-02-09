<?php


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: register.php");
  exit;
}

$username = $_POST['username'] ?? null;
$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$terms_rules = isset($_POST['terms_rules']);
$terms_privacy = isset($_POST['terms_privacy']);

if ($username === '' || $email === '' || $password === '' || !$terms_rules || !$terms_privacy) {
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

  <h1>Teljes név blokkokal felosztva, személyi kép 2 oldala, lakcim kép 1 oldala, édesanyad lánykori neve, születési hely, szemelyi szam,  
    lakcim adatokkal cim utca lépcsőház emelet ajtó (ha van) iranyito szam telepules. teloszam    
</h1>
  <br>


  <form action="register3.php" method="POST" enctype="multipart/form-data" >
  <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
  <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
  <input type="hidden" name="password" value="<?php echo htmlspecialchars($password); ?>">

  <input type="file" id="id_image_first" accept="image/*" required>
  
  <img id="preview">

  <input type="file" id="id_image_second" accept="image/*" required>
  
  <img id="preview">



  <input type="file" id="address_image_first" accept="image/*" required>

  <img id="preview">


  
  <label for="date-input">Születési dátum:</label><br>
  <input class="age" type="date" id="date-input" name="birthdate" required>
  <input type="hidden" name="calculated_age" id="calculated_age">

  <button class="regisztraciobtn" type="submit" id="regisztracio" >
    Folytatás
  </button>
</form>

  <br>

  <a href="register.php">Vissza</a>

</body>
<script src="../../js/Register/register2.js"></script>

</html>
