<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segítség | BetMatchBonus</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../css/Main/layout.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../css/Help/help.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Modal/modal.css">
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
            <h1 data-i18n="helpStatic.infoSecurity.title">Információbiztonság</h1>

            <div class="help-menu-container" data-i18n="helpStatic.infoSecurity.intro">
                A BetMatchBonus rendszere több szintű technikai és működési védelemmel óvja a felhasználói adatokat.
                Célunk, hogy a fiókhasználat, a tranzakciók és a személyes adatok kezelése biztonságos, átlátható és ellenőrizhető legyen.
            </div>

            <h4 data-i18n="helpStatic.infoSecurity.accountTitle">Fiókvédelem</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.infoSecurity.account1">Erős, egyedi jelszót használj, amit más oldalon nem alkalmazol.</li>
                    <li data-i18n="helpStatic.infoSecurity.account2">Nyilvános vagy közös eszközön mindig jelentkezz ki a használat után.</li>
                    <li data-i18n="helpStatic.infoSecurity.account3">Rendszeresen ellenőrizd a profil- és tranzakciós előzményeidet.</li>
                    <li data-i18n="helpStatic.infoSecurity.account4">Gyanús aktivitás esetén azonnal változtass jelszót.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.infoSecurity.dataTitle">Adatkezelési biztonság</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.infoSecurity.data1">A rendszer a jogosultsághoz kötött hozzáférés elvét alkalmazza.</li>
                    <li data-i18n="helpStatic.infoSecurity.data2">Az érzékeny műveletek (pl. bejelentkezés, pénzmozgás) naplózva vannak.</li>
                    <li data-i18n="helpStatic.infoSecurity.data3">A személyes adatok kezelését vonatkozó adatvédelmi szabályzat szerint végezzük.</li>
                    <li data-i18n="helpStatic.infoSecurity.data4">Szokatlan viselkedés esetén a fiók védelmi ellenőrzés alá kerülhet.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.infoSecurity.tipsTitle">Felhasználói biztonsági tippek</h4>
            <div class="help-menu-container">
                <ul style="margin: 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.infoSecurity.tip1">Soha ne add meg belépési adataidat üzenetben vagy telefonon.</li>
                    <li data-i18n="helpStatic.infoSecurity.tip2">Csak megbízható hálózaton lépj be a fiókodba.</li>
                    <li data-i18n="helpStatic.infoSecurity.tip3">Ellenőrizd a böngésző címsorát, és csak hivatalos oldalon jelentkezz be.</li>
                    <li data-i18n="helpStatic.infoSecurity.tip4">Ha adathalászatra gyanakszol, ne kattints a linkre, és vedd fel a kapcsolatot az ügyfélszolgálattal.</li>
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