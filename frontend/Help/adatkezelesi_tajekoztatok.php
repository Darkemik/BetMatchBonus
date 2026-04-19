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
            <h1 data-i18n="helpStatic.privacy.title">Adatkezelési tájékoztató</h1>

            <div class="help-menu-container" data-i18n="helpStatic.privacy.intro">
                Jelen tájékoztató célja, hogy közérthetően bemutassa, hogyan kezeljük a felhasználói adatokat a
                BetMatchBonus rendszerében. Az adatkezelés során törekszünk az átláthatóságra, a biztonságra és a
                jogszabályi megfelelésre.
            </div>

            <h4 data-i18n="helpStatic.privacy.s1Title">1. Milyen adatokat kezelünk?</h4>
            <div class="additional-info">
                <span data-i18n="helpStatic.privacy.s1Intro">A szolgáltatás működtetéséhez és a felhasználói fiók kezeléséhez az alábbi adatkörök kerülhetnek kezelésre:</span>
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.privacy.s1Item1">fiókadatok: felhasználónév, e-mail cím, jelszóhoz kapcsolódó technikai adatok,</li>
                    <li data-i18n="helpStatic.privacy.s1Item2">profiladatok: teljes név, kapcsolódó azonosító adatok,</li>
                    <li data-i18n="helpStatic.privacy.s1Item3">pénzügyi adatok: befizetés/kifizetés tranzakciók, egyenlegek,</li>
                    <li data-i18n="helpStatic.privacy.s1Item4">fogadási adatok: szelvények, tétek, oddsok, eredmények,</li>
                    <li data-i18n="helpStatic.privacy.s1Item5">technikai adatok: munkamenet- és naplóadatok, biztonsági események.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.privacy.s2Title">2. Az adatkezelés céljai</h4>
            <div class="additional-info">
                <span data-i18n="helpStatic.privacy.s2Intro">Az adatokat különösen az alábbi célokból kezeljük:</span>
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.privacy.s2Item1">felhasználói fiók létrehozása és kezelése,</li>
                    <li data-i18n="helpStatic.privacy.s2Item2">fogadási szolgáltatás biztosítása és tranzakciók feldolgozása,</li>
                    <li data-i18n="helpStatic.privacy.s2Item3">jogi kötelezettségek teljesítése (pl. számviteli, panaszkezelési kötelezettségek),</li>
                    <li data-i18n="helpStatic.privacy.s2Item4">visszaélések megelőzése, rendszerbiztonság fenntartása,</li>
                    <li data-i18n="helpStatic.privacy.s2Item5">ügyfélszolgálati megkeresések kezelése.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.privacy.s3Title">3. Az adatkezelés jogalapja</h4>
            <div class="help-menu-container">
                <span data-i18n="helpStatic.privacy.s3Intro">Az adatkezelés jogalapja az eset jellegétől függően:</span>
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.privacy.s3Item1">szerződés teljesítése (felhasználói fiók és szolgáltatás nyújtása),</li>
                    <li data-i18n="helpStatic.privacy.s3Item2">jogi kötelezettség teljesítése,</li>
                    <li data-i18n="helpStatic.privacy.s3Item3">jogos érdek (pl. csalásmegelőzés, rendszerbiztonság),</li>
                    <li data-i18n="helpStatic.privacy.s3Item4">hozzájárulás, ahol a jogszabály ezt megköveteli.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.privacy.s4Title">4. Adatmegőrzési idő</h4>
            <div class="additional-info" data-i18n="helpStatic.privacy.s4Text">
                Az adatokat csak a szükséges ideig tároljuk. Az adatmegőrzés időtartamát a szolgáltatás működése,
                a jogszabályi kötelezettségek, valamint a jogos érdek alapján határozzuk meg.
                A megőrzési idő lejárta után az adatokat töröljük vagy anonimizáljuk.
            </div>

            <h4 data-i18n="helpStatic.privacy.s5Title">5. Adatbiztonság</h4>
            <div class="additional-info" data-i18n="helpStatic.privacy.s5Text">
                Megfelelő technikai és szervezési intézkedésekkel védjük az adatokat az illetéktelen hozzáférés,
                módosítás, nyilvánosságra hozatal vagy elvesztés ellen. A biztonsági kontrollok folyamatosan
                felülvizsgálatra kerülnek.
            </div>

            <h4 data-i18n="helpStatic.privacy.s6Title">6. Érintetti jogok</h4>
            <div class="help-menu-container">
                <span data-i18n="helpStatic.privacy.s6Intro">A felhasználókat az adatkezeléssel kapcsolatban több jog is megilleti, különösen:</span>
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li data-i18n="helpStatic.privacy.s6Item1">tájékoztatáshoz való jog,</li>
                    <li data-i18n="helpStatic.privacy.s6Item2">hozzáféréshez való jog,</li>
                    <li data-i18n="helpStatic.privacy.s6Item3">helyesbítéshez való jog,</li>
                    <li data-i18n="helpStatic.privacy.s6Item4">törléshez való jog (jogszabályi keretek között),</li>
                    <li data-i18n="helpStatic.privacy.s6Item5">adatkezelés korlátozásához való jog,</li>
                    <li data-i18n="helpStatic.privacy.s6Item6">tiltakozáshoz való jog,</li>
                    <li data-i18n="helpStatic.privacy.s6Item7">adathordozhatósághoz való jog.</li>
                </ul>
            </div>

            <h4 data-i18n="helpStatic.privacy.s7Title">7. Kapcsolat és jogérvényesítés</h4>
            <div class="additional-info" data-i18n="helpStatic.privacy.s7Text">
                Adatkezeléssel kapcsolatos kérdés vagy kérelem esetén kérjük, vedd fel a kapcsolatot ügyfélszolgálatunkkal.
                Panasz esetén jogosult vagy a hatáskörrel rendelkező felügyeleti hatósághoz fordulni.
            </div>

            <div class="help-menu-container" data-i18n="helpStatic.privacy.footerNote">
                A tájékoztató tartalmát időről időre frissíthetjük, hogy az megfeleljen a szolgáltatás fejlődésének
                és a mindenkori jogszabályi környezetnek.
            </div>
        </main>

        <?php include '../../frontend/Components/promokartya.php'; ?>
    </div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <?php include '../../frontend/Components/loginmodal.php'; ?>
    <?php include '../../frontend/Components/registermodal.php'; ?>
    <?php include '../../frontend/Components/registermodal2.php'; ?>
    <script src="../../js/Main/auth_ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Login/loginmodal.js"></script>
    <script src="../../js/Register/registermodal.js"></script>
    <script src="../../js/Register/registermodal2.js"></script>
    <script src="../../js/Main/language.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>