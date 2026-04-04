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
            <h1>Információbiztonság</h1>

            <div class="help-menu-container">
                A BetMatchBonus rendszere több szintű technikai és működési védelemmel óvja a felhasználói adatokat.
                Célunk, hogy a fiókhasználat, a tranzakciók és a személyes adatok kezelése biztonságos, átlátható és ellenőrizhető legyen.
            </div>

            <h4>Fiókvédelem</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>Erős, egyedi jelszót használj, amit más oldalon nem alkalmazol.</li>
                    <li>Nyilvános vagy közös eszközön mindig jelentkezz ki a használat után.</li>
                    <li>Rendszeresen ellenőrizd a profil- és tranzakciós előzményeidet.</li>
                    <li>Gyanús aktivitás esetén azonnal változtass jelszót.</li>
                </ul>
            </div>

            <h4>Adatkezelési biztonság</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>A rendszer a jogosultsághoz kötött hozzáférés elvét alkalmazza.</li>
                    <li>Az érzékeny műveletek (pl. bejelentkezés, pénzmozgás) naplózva vannak.</li>
                    <li>A személyes adatok kezelését vonatkozó adatvédelmi szabályzat szerint végezzük.</li>
                    <li>Szokatlan viselkedés esetén a fiók védelmi ellenőrzés alá kerülhet.</li>
                </ul>
            </div>

            <h4>Felhasználói biztonsági tippek</h4>
            <div class="help-menu-container">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>Soha ne add meg belépési adataidat üzenetben vagy telefonon.</li>
                    <li>Csak megbízható hálózaton lépj be a fiókodba.</li>
                    <li>Ellenőrizd a böngésző címsorát, és csak hivatalos oldalon jelentkezz be.</li>
                    <li>Ha adathalászatra gyanakszol, ne kattints a linkre, és vedd fel a kapcsolatot az ügyfélszolgálattal.</li>
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
    <script src="../../js/Register/registermodal.js"></script>
    <script src="../../js/Main/auth_ui.js"></script>
    <script src="../../js/Register/registermodal2.js"></script>
    <script src="../../js/Main/language.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>