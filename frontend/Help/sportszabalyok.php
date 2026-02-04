<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segítség | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/main.css">
    <link rel="stylesheet" href="../../css/Help/help.css">
    <link rel="stylesheet" href="../../css/Footer/footer.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
    <header class="header">
        <div class="logo-box">
            <a href="../../frontend/MainMenu/MainMenu.php"><img class="kep" src="../../img/logo.png" alt="logo"></a>
            <div class="logo"><a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a></div>
        </div>

        <nav class="nav">
            <a href="../../frontend/MainMenu/MainMenu.php">Főoldal</a>
            <a href="../../frontend/Live/live.php">Élő</a>
            <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>
            <a href="../../frontend/Help/help.php">Segítség</a>
        </nav>
        <div class="right_side">
            <button class="loginbtn" onclick="location.href='../../frontend/Login/login.php'">Bejelentkezés</button>
            <button class="registrationbtn"
                onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
        </div>
    </header>

    <div class="help-container">
        <!-- Bal oldali menü sáv -->
        <aside class="left-sidebar">
            <div class="sidebar-section">
                <h3>INFORMÁCIÓK</h3>
                <ul>
                    <li><a href="../Help/GYIK.php">GYIK</a></li>
                    <li><a href="../Help/uj_funkcio.php">Új funkciók</a></li>
                    <li><a href="../Help/sportszabalyok.php">Sportszabályok</a></li>
                    <li><a href="../Help/szotar.php">Szótár</a></li>
                    <li><a href="../Help/fizetesi_lehetosegek.php">Fizetési lehetőségek</a></li>
                    <li><a href="../Help/jatekosvedelem.php">Játékosvédelem</a></li>
                    <li><a href="../Help/informaciobiztonsag.php">Információbiztonság</a></li>
                    <li><a href="../Help/panaszkezeles.php">Panaszkezelés</a></li>
                    <li><a href="../Help/kapcsolat.php">Kapcsolat</a></li>
                    <li><a href="../Help/adatkezelesi_tajekoztatok.php">Adatkezelési tájékoztatók</a></li>
                    <li><a href="../Help/reszveteli-szabalyzat.php">Részvételi szabályzat</a></li>
                </ul>
            </div>
        </aside>

        <!-- Fő tartalom (középső rész)-->
        <main class="main-content">

        </main>

        <!-- Jobb oldali sáv -->
        <aside class="right-sidebar">
            <div class="promo-kartya">
                <h3>ODDSŰRHAJÓ!</h3>
                <p>A legjobb szorzók, kizárólag nálunk!</p>
                <button class="tobb-info-gomb">RÉSZLETEK</button>
            </div>

            <div class="promo-kartya">
                <h3>ODDSPIRAMIS</h3>
                <p>Növelnéd a nyereményed? Keress aktuális ajánlatunkat a promóciók között!</p>
                <button class="tobb-info-gomb">RÉSZLETEK</button>
            </div>

            <div class="promo-kartya">
                <h3>BETMATCHBONUS MAGAZIN</h3>
                <p>Esélyek, információk, nyeremények - olvasd el aktuális bejegyzéseinket!</p>
                <button class="tobb-info-gomb">ELOLVASOM</button>
            </div>
        </aside>
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
                    <p>Maradjon játék! 18+. A túlzásba vitt szerencsejáték ártalmas, függőséget okozhat! Kérje
                        bejegyzését a játékosvédelmi nyilvántartásba!
                        <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank">Tudj megtöbbet!</a>
                    </p>
                </div>
                <p>Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>
            </div>
        </div>
    </footer>
</body>

</html>