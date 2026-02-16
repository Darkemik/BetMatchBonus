<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segítség | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Help/help.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
    <header class="header">
        <div class="logo-box">
            <a href="../../frontend/MainMenu/MainMenu.php"><img class="kep" src="../../img/logo.png" alt="logo"></a>
            <div class="logo"><a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a></div>
        </div>

        <nav class="nav">
        <a href="../../frontend/MainMenu/MainMenu.php" data-i18n="nav.home">Főoldal</a>
            <a href="../../frontend/Live/live.php" data-i18n="nav.live">Élő</a>
            <a href="../../frontend/Bonus/bonus.php" data-i18n="nav.bonuses">Bónuszok</a>
            <a href="../../frontend/Help/help.php" data-i18n="nav.help" class="active">Segítség</a>
        </nav>
        <div class="right_side">
            <button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
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
            <h1>Kérdésed van vagy problémád?</h1>
            <h4>Keress minket bizalommal emailen keresztül!</h4>
            <h5>Ügyfélszolgálat: ugyfelszolgalat@betmatchbonus.com</h5>
            <p class="additional-info">Ha további információkra van szükséged, használd a bal oldali menüpontokat.</p>
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

    <?php include '../../frontend/Components/footer.php';?>
    <?php include '../../frontend/Components/modal.php';?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>