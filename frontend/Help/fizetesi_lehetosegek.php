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
            <h1>Fizetési lehetőségek</h1>

            <div class="help-menu-container">
                A BetMatchBonus felületén a befizetés és a kifizetés biztonságos, ellenőrzött folyamatban történik.
                A tranzakciók aktuális állapotát a profilodban, a Tranzakciótörténet menüpontban követheted.
            </div>

            <h4>Befizetés</h4>
            <div class="additional-info">
                <strong>Elérhető mód:</strong> bankkártyás fizetés (demo kártya-feldolgozás).<br>
                <strong>Minimum összeg:</strong> 3 000 FT<br>
                <strong>Maximum összeg:</strong> 600 000 FT<br>
                A sikeres befizetés után az egyenleg azonnal frissül.
            </div>

            <h4>Kifizetés</h4>
            <div class="additional-info">
                <strong>Elérhető mód:</strong> banki átutalás.<br>
                <strong>Minimum kifizetés:</strong> 6 000 FT<br>
                Kifizetés kizárólag a <strong>nyereményegyenlegből</strong> kezdeményezhető,
                a bónusz egyenleg közvetlenül nem utalható ki.
            </div>

            <h4>Fontos tudnivalók</h4>
            <div class="help-menu-container">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>A számlatulajdonos nevének egyeznie kell a regisztrációkor megadott teljes névvel.</li>
                    <li>A hibás vagy hiányos banki adatok késleltethetik vagy elutasíthatják a kifizetést.</li>
                    <li>A befizetett és nyereményegyenleg összesített értékét a Befizetés és Kifizetés oldalon is látod.</li>
                    <li>Aktív bónusz esetén a kapcsolódó feltételek (forgatás, minimum kötés, minimum odds) kötelezőek.</li>
                </ul>
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
    <script src="../../js/Main/auth_ui.js"></script>
    <script src="../../js/Register/registermodal.js"></script>
    <script src="../../js/Register/registermodal2.js"></script>
    <script src="../../js/Main/language.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>