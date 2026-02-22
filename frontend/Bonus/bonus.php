<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bónuszok | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Bonus/bonus.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
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
            <div class="lang-switcher">
                    <button class="translateBtn" id="btn-hu">
                        <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                            <rect width="9" height="2" y="0" fill="#c8102e" />
                            <rect width="9" height="2" y="2" fill="#ffffff" />
                            <rect width="9" height="2" y="4" fill="#436f4d" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="lang-dropdown">
                        <button class="lang-btn" id="btn-hu-switch" title="Magyar">
                            <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                                <rect width="9" height="2" y="0" fill="#c8102e" />
                                <rect width="9" height="2" y="2" fill="#ffffff" />
                                <rect width="9" height="2" y="4" fill="#436f4d" />
                            </svg>
                        </button>
                        <button class="lang-btn" id="btn-en" title="English">
                            <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                                <rect width="9" height="6" fill="#ffffff" />
                                <rect x="4" width="1" height="6" fill="#c8102e" />
                                <rect y="2.5" width="9" height="1" fill="#c8102e" />
                            </svg>
                        </button>
                    </div>
                </div>
            <button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
            <button class="registrationbtn" onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
        </div>
    </div>

    <nav class="nav collapse navbar-collapse" id="mainNavbar">
        <a href="../../frontend/MainMenu/MainMenu.php">Főoldal</a>
        <a href="../../frontend/Live/live.php">Élő</a>
        <a href="../../frontend/Bonus/bonus.php" class="active">Bónuszok</a>
        <a href="../../frontend/Help/help.php">Segítség</a>
    </nav>
</header>

    <div class="container">
        <div id="bonusContainer" class="bonus-row"></div>
    </div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <?php include '../../frontend/Components/modal.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <script src="../../js/Bonus/bonus.js"></script>
</body>

</html>