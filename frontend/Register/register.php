<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regisztráció</title>
    <link rel="stylesheet" href="../../css/Register/register.css">
</head>

<body>
    <div class="container">


        <form id="registerForm" action="register2.php" method="POST">
            <h1>Regisztráljon hozzánk!</h1>

            <input class="inputok" type="text" name="username" placeholder="Felhasználónév" required>
            <input class="inputok" type="email" id="email" name="email" placeholder="Email" required>
            <input class="inputok" type="email" id="email2" placeholder="Email Újra" required>
            <input class="inputok" type="password" id="password" name="password" placeholder="Jelszó" required>
            
            <input class="inputok" type="password" id="password2" placeholder="Jelszó Újra" required>

            <p id="error" style="color:red; font-weight:bold;"></p>

            <input class="age" type="date" id="date-input" required>
            <input type="hidden" name="calculated_age" id="calculated_age">


            <p id="result"></p>




            <div class="container1">
                <input type="checkbox" id="terms_rules" name="terms_rules" value="1" required>
                <label for="terms_rules">
                    Elolvastam és elfogadom a
                    <a href="../Help/reszveteli-szabalyzat.php">Részvételi szabályzatot</a>
                </label>

                <br>

                <input type="checkbox" id="terms_privacy" name="terms_privacy" value="1" required>
                <label for="terms_privacy">
                    Elolvastam és elfogadom a
                    <a href="../Help/adatkezelesi_tajekoztatok.php">Adatkezelési tájékoztatóját</a>
                </label>
            </div>

            <button class="regisztraciobtn" type="submit" id="regisztracio">Folytatás</button>
            
            
            <p>Már van fiókod?</p>
            <a href="../Login/login.php">Jelentkezz be!</a>
        </form>
    </div>
</body>
<script src="../../js/Register/register.js"> </script>

</html>