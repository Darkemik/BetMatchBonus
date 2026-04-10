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
    <?php include '../../frontend/Components/disclaimer.php'; ?>
    <?php include '../../frontend/Components/helpheader.php'; ?>

    <div class="help-container">
        <?php include '../../frontend/Components/helpaside.php'; ?>

        <!-- Fő tartalom (középső rész)-->
        <main class="main-content">
            <h1>Kapcsolat</h1>

            <div class="help-menu-container">
                Ügyfélszolgálatunk célja, hogy gyorsan és érthetően segítsen minden felhasználónak.
                Ha kérdésed van a fiókoddal, befizetéssel, kifizetéssel, bónuszokkal vagy fogadásokkal kapcsolatban,
                vedd fel velünk a kapcsolatot.
            </div>

            <h4>Miben tudunk segíteni?</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>regisztrációs és bejelentkezési problémák,</li>
                    <li>tranzakciók és egyenleggel kapcsolatos kérdések,</li>
                    <li>bónuszok és promóciók feltételeinek értelmezése,</li>
                    <li>fogadási események, szelvények és eredmények ellenőrzése.</li>
                </ul>
            </div>

            <h4>Kapcsolatfelvétel előtt javasolt</h4>
            <div class="additional-info">
                A gyorsabb ügyintézés érdekében készítsd elő az alábbi adatokat:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>felhasználóneved vagy regisztrált e-mail címed,</li>
                    <li>érintett tranzakció/szelvény azonosítója,</li>
                    <li>a probléma rövid és pontos leírása,</li>
                    <li>ha van, képernyőkép vagy időpont megjelölése.</li>
                </ul>
            </div>

            <h4>Válaszadási idő</h4>
            <div class="help-menu-container">
                Az egyszerűbb megkeresésekre általában rövid időn belül választ adunk.
                Összetettebb technikai vagy pénzügyi ellenőrzést igénylő ügyek esetén a feldolgozási idő hosszabb lehet.
                Minden esetben törekszünk a lehető leggyorsabb, részletes tájékoztatásra.
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