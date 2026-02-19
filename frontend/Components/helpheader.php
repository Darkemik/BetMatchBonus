<header class="header">
    <div class="header-top-row">
        <button class="navbar-toggler navbar-dark" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Menü">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="logo-box">
            <a href="../../frontend/MainMenu/MainMenu.php">
                <img class="kep" src="../../img/logo.png" alt="logo">
            </a>
            <div class="logo">
                <a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a>
            </div>
        </div>

        <div class="right_side">
            <button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
            <button class="registrationbtn" onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
        </div>
    </div>

    <nav class="nav collapse navbar-collapse" id="mainNavbar">
        <a href="../../frontend/MainMenu/MainMenu.php">Főoldal</a>
        <a href="../../frontend/Live/live.php">Élő</a>
        <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>
        <a href="../../frontend/Help/help.php" class="active">Segítség</a>
    </nav>
</header>