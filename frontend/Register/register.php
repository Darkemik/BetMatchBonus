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

            <input class="inputok" type="text"     name="username" placeholder="Felhasználónév" required>
            <input class="inputok" type="email"    name="email" placeholder="Email" required>
            <input class="inputok" type="password" name="password" placeholder="Jelszó" required>
            <p>A jelszó nem elég erős nem működik vmiért</p>
            <input class="inputok" type="date"     name="age" placeholder="Életkor" required>

            <div class="container1">
                
                <input type="checkbox" id="terms" name="terms" value="1" required>
                <label for="terms">
                    Elolvastam és elfogadom a 
                    <a href="../T&C../T&C.php">Felhasználási feltételeket*</a>
                </label>
            </div>

            <button class="regisztraciobtn" type="submit" id="regisztracio">Folytatás</button>

            <p>Már van fiókod?</p>
            <a href="../Login/login.php">Jelentkezz be!</a>
        </form>

    </div>
</body>
</html>
