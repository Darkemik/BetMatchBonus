<!DOCTYPE html>
<html lang="hu">
<?php
require_once dirname(dirname(__DIR__)) . '/backend/connect.php';
require_once dirname(dirname(__DIR__)) . '/backend/Auth/settings_helper.php';
$_minDep = get_setting_int('min_deposit', 3000);
$_maxDep = get_setting_int('max_deposit', 600000);
$_minWith = get_setting_int('min_withdrawal', 6000);
?>

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
            <h1 data-i18n="helpStatic.payment.title">Fizetési lehetőségek</h1>

            <div class="help-menu-container" data-i18n="helpStatic.payment.intro">
                A BetMatchBonus felületén a befizetés és a kifizetés biztonságos, ellenőrzött folyamatban történik.
                A tranzakciók aktuális állapotát a profilodban, a Tranzakciótörténet menüpontban követheted.
            </div>

            <h4 data-i18n="helpStatic.payment.depositTitle">Befizetés</h4>
            <div class="additional-info">
                <strong data-i18n="helpStatic.payment.availableMethodLabel">Elérhető mód:</strong> <span data-i18n="helpStatic.payment.depositMethod">bankkártyás fizetés (demo kártya-feldolgozás).</span><br>
                <strong data-i18n="helpStatic.payment.minAmountLabel">Minimum összeg:</strong> <?php echo number_format($_minDep, 0, ',', ' '); ?> FT<br>
                <strong data-i18n="helpStatic.payment.maxAmountLabel">Maximum összeg:</strong> <?php echo number_format($_maxDep, 0, ',', ' '); ?> FT<br>
                <span data-i18n="helpStatic.payment.depositAfterText">A sikeres befizetés után az egyenleg azonnal frissül.</span>
            </div>

            <h4 data-i18n="helpStatic.payment.withdrawalTitle">Kifizetés</h4>
            <div class="additional-info">
                <strong data-i18n="helpStatic.payment.availableMethodLabel">Elérhető mód:</strong> <span data-i18n="helpStatic.payment.withdrawalMethod">banki átutalás.</span><br>
                <strong data-i18n="helpStatic.payment.minWithdrawalLabel">Minimum kifizetés:</strong> <?php echo number_format($_minWith, 0, ',', ' '); ?> FT<br>
                <span data-i18n="helpStatic.payment.withdrawalOnlyPrefix">Kifizetés kizárólag a</span> <strong data-i18n="helpStatic.payment.winningsBalanceBold">nyereményegyenlegből</strong> <span data-i18n="helpStatic.payment.withdrawalOnlySuffix">kezdeményezhető, a bónusz egyenleg közvetlenül nem utalható ki.</span>
            </div>

            <h4 data-i18n="helpStatic.payment.importantTitle">Fontos tudnivalók</h4>
            <div class="help-menu-container">
                <ul style="margin: 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.payment.important1">A számlatulajdonos nevének egyeznie kell a regisztrációkor megadott teljes névvel.</li>
                    <li data-i18n="helpStatic.payment.important2">A hibás vagy hiányos banki adatok késleltethetik vagy elutasíthatják a kifizetést.</li>
                    <li data-i18n="helpStatic.payment.important3">A befizetett és nyereményegyenleg összesített értékét a Befizetés és Kifizetés oldalon is látod.</li>
                    <li data-i18n="helpStatic.payment.important4">Aktív bónusz esetén a kapcsolódó feltételek (forgatás, minimum kötés, minimum odds) kötelezőek.</li>
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