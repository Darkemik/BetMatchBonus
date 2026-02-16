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
        <div class="logo-box">
            <a href="../../frontend/MainMenu/MainMenu.php">
                <img class="kep" src="../../img/logo.png" alt="logo">
            </a>
            <div class="logo">
                <a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a>
            </div>
        </div>

        <nav class="nav">
            <a href="../../frontend/MainMenu/MainMenu.php">Főoldal</a>

            <a href="../../frontend/Live/live.php">Élő</a>

            <a href="../../frontend/Bonus/bonus.php" class="active">Bónuszok</a>

            <a href="../../frontend/Help/help.php">Segítség</a>
        </nav>

        <div class="right_side">
            <button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
            <button class="registrationbtn"
                onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
        </div>
    </header>

    <div class="container">
        <div id="bonusContainer" class="bonus-row"></div>
    </div>

    <footer class="simple-footer">
        <div class="footer-top">
            <div class="footer-links">
                <a href="../Help/adatkezelesi_tajekoztatok.php" class="footer-link">ADATKEZELÉSI TÁJÉKOZTATÓ</a>
                <a href="../Help/reszveteli-szabalyzat.php" class="footer-link">RÉSZVÉTELI SZABÁLYZAT</a>
                <a href="../Help/kapcsolat.php" class="footer-link">UGYFELSZOLGALAT@BETMATCHBONUS.COM</a>
                <a href="../Help/GYIK.php" class="footer-link">GYIK</a>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-content">
                <div class="responsible-text">
                    <h2>Ajánlott felelős szervező!</h2>
                    <p>Maradjon játék! 18+. A túlzásba vitt szerencsejáték ártalmas, függőséget okozhat!
                        <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank">Tudj meg többet!</a>
                    </p>
                </div>
                <p>Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>
            </div>
        </div>
    </footer>
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px;">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2"> <a
                            href="../../frontend/MainMenu/MainMenu.php">
                            <img src="../../img/logo.png" alt="logo" style="height: 28px; cursor: pointer;"></a>Bejelentkezés</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"> <label class="form-label">Felhasználónév vagy e-mail cím</label> <input
                        type="text" class="form-control mb-3"> <label class="form-label">Jelszó</label> <input
                        type="password" class="form-control mb-2"> <a href="#" class="small"
                        style="color:#3498db;">Elfelejtettem a jelszavam</a> </div>
                <div class="modal-footer d-flex flex-column"> <button
                        class="btn btn-success w-100 mb-2">Bejelentkezés</button>
                    <p class="m-0"> Még nincs fiókod? <a href="../../frontend/Register/register.php"
                            style="color:#3498db;">Regisztrálj!</a> </p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Bonus/bonus.js"></script>
</body>

</html>