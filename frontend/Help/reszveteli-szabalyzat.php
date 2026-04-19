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
            <h1 data-i18n="helpStatic.participation.title">Részvételi szabályzat</h1>

            <div class="help-menu-container" data-i18n="helpStatic.participation.intro">
                Jelen szabályzat a BetMatchBonus felület használatának alapvető feltételeit rögzíti.
                A szolgáltatás használatával a felhasználó elfogadja a szabályzat rendelkezéseit,
                és vállalja, hogy a platformot a vonatkozó jogszabályokkal összhangban, jóhiszeműen használja.
            </div>

            <h4 data-i18n="helpStatic.participation.s1Title">1. Részvételi jogosultság</h4>
            <div class="additional-info" data-i18n="helpStatic.participation.s1Text">
                A szolgáltatás igénybevételéhez érvényes regisztráció szükséges.
                A felhasználó köteles valós és pontos adatokat megadni, valamint azok változását naprakészen tartani.
                A felhasználói fiók személyhez kötött, átadása vagy megosztása nem engedélyezett.
            </div>

            <h4 data-i18n="helpStatic.participation.s2Title">2. Fiókhasználat és felelősség</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.participation.s2Item1">A belépési adatok bizalmas kezelése a felhasználó felelőssége.</li>
                    <li data-i18n="helpStatic.participation.s2Item2">A fiókból indított műveletek a felhasználóhoz köthetők.</li>
                    <li data-i18n="helpStatic.participation.s2Item3">Gyanús tevékenység esetén a szolgáltató biztonsági ellenőrzést alkalmazhat.</li>
                    <li data-i18n="helpStatic.participation.s2Item4">A szabályzat megsértése a fiók korlátozását vagy felfüggesztését eredményezheti.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.participation.s3Title">3. Fogadások leadása</h4>
            <div class="help-menu-container" data-i18n="helpStatic.participation.s3Text">
                A fogadás akkor tekinthető érvényesen leadottnak, ha a rendszer visszaigazolja annak rögzítését.
                A felhasználó felelőssége, hogy a fogadás leadása előtt ellenőrizze:
                tét összegét, kiválasztott eseményeket, oddsokat és a fogadás típusát.
                A már elfogadott fogadások utólagos módosítása általános esetben nem lehetséges.
            </div>

            <h4 data-i18n="helpStatic.participation.s4Title">4. Bónuszok és promóciók</h4>
            <div class="additional-info" data-i18n="helpStatic.participation.s4Text">
                A bónuszok felhasználása minden esetben feltételekhez kötött.
                Ide tartozhat különösen a minimum befizetés, minimum kötés, minimum odds, lejárat és forgatási követelmény.
                A bónuszra vonatkozó feltételek teljesítése nélkül az adott előny nem érvényesíthető.
            </div>

            <h4 data-i18n="helpStatic.participation.s5Title">5. Kifizetések</h4>
            <div class="additional-info" data-i18n="helpStatic.participation.s5Text">
                A kifizetés banki átutalással történik, a rendszerben beállított minimum összeg figyelembevételével.
                Kifizetés kizárólag a nyereményegyenleg terhére kezdeményezhető.
                A szolgáltató jogosult ellenőrizni a kifizetési adatok pontosságát és egyezőségét.
            </div>

            <h4 data-i18n="helpStatic.participation.s6Title">6. Tiltott magatartások</h4>
            <div class="additional-info" data-i18n="helpStatic.participation.s6Text">
                Tiltott minden olyan tevékenység, amely a rendszer működésének megzavarására,
                a promóciós feltételek kijátszására, vagy jogosulatlan előny megszerzésére irányul.
                Visszaélés gyanúja esetén a szolgáltató ideiglenes vagy végleges intézkedést hozhat.
            </div>

            <h4 data-i18n="helpStatic.participation.s7Title">7. Felelősségkorlátozás</h4>
            <div class="help-menu-container" data-i18n="helpStatic.participation.s7Text">
                A szolgáltató minden tőle elvárhatót megtesz a folyamatos működés biztosításáért,
                ugyanakkor nem vállal felelősséget olyan külső körülményekből eredő kiesésekért,
                amelyek rajta kívül álló okból következnek be.
            </div>

            <h4 data-i18n="helpStatic.participation.s8Title">8. A szabályzat módosítása</h4>
            <div class="additional-info" data-i18n="helpStatic.participation.s8Text">
                A szolgáltató fenntartja a jogot a részvételi szabályzat módosítására.
                A módosított feltételek közzétételét követően a szolgáltatás további használata
                a frissített szabályok elfogadását jelenti.
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