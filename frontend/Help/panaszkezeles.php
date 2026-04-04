<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segítség | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Help/help.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Modal/modal.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/helpheader.php'; ?>

    <div class="help-container">
        <?php include '../../frontend/Components/helpaside.php'; ?>

        <!-- Fő tartalom (középső rész)-->
        <main class="main-content">
            <h1>Panaszkezelés</h1>

            <div class="help-menu-container">
                A BetMatchBonus célja a gyors, átlátható és korrekt ügyintézés.
                Ha észrevételed, kifogásod vagy panaszod van, azt minden esetben kivizsgáljuk,
                és törekszünk a mielőbbi, egyértelmű megoldásra.
            </div>

            <h4>Hogyan nyújthatsz be panaszt?</h4>
            <div class="additional-info">
                Panaszodat írásban add meg, és lehetőség szerint tartalmazza az alábbiakat:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>felhasználóneved vagy regisztrált e-mail címed,</li>
                    <li>az érintett esemény/tranzakció azonosítója,</li>
                    <li>a probléma rövid, tényszerű leírása,</li>
                    <li>a kért megoldás megjelölése.</li>
                </ul>
            </div>

            <h4>Panaszkezelési folyamat</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>A beérkező panasz rögzítésre kerül a rendszerben.</li>
                    <li>Az ügyet a releváns adatok (napló, tranzakció, fogadási előzmény) alapján kivizsgáljuk.</li>
                    <li>Az eredményről és a döntés indokáról írásban tájékoztatást adunk.</li>
                    <li>Szükség esetén további egyeztetést kezdeményezünk a gyors lezárás érdekében.</li>
                </ul>
            </div>

            <h4>Válaszadási idő és együttműködés</h4>
            <div class="help-menu-container">
                Általános esetben a panaszok feldolgozása rövid határidőn belül megtörténik.
                Összetettebb ügyeknél hosszabb kivizsgálási idő is szükséges lehet,
                erről külön tájékoztatást adunk.
                Kérjük, hogy minden releváns információt pontosan adj meg,
                mert ez jelentősen felgyorsítja az ügyintézést.
            </div>
        </main>

        <?php include '../../frontend/Components/promokartya.php'; ?>
    </div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <?php include '../../frontend/Components/loginmodal.php'; ?>
    <?php include '../../frontend/Components/registermodal.php'; ?>
    <?php include '../../frontend/Components/registermodal2.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Login/loginmodal.js"></script>
    <script src="../../js/Register/registermodal.js"></script>
    <script src="../../js/Main/auth_ui.js"></script>
    <script src="../../js/Register/registermodal2.js"></script>
    <script src="../../js/Main/language.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>