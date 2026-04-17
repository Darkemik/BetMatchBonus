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
            <h1>Adatkezelési tájékoztató</h1>

            <div class="help-menu-container">
                Jelen tájékoztató célja, hogy közérthetően bemutassa, hogyan kezeljük a felhasználói adatokat a
                BetMatchBonus rendszerében. Az adatkezelés során törekszünk az átláthatóságra, a biztonságra és a
                jogszabályi megfelelésre.
            </div>

            <h4>1. Milyen adatokat kezelünk?</h4>
            <div class="additional-info">
                A szolgáltatás működtetéséhez és a felhasználói fiók kezeléséhez az alábbi adatkörök kerülhetnek
                kezelésre:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>fiókadatok: felhasználónév, e-mail cím, jelszóhoz kapcsolódó technikai adatok,</li>
                    <li>profiladatok: teljes név, kapcsolódó azonosító adatok,</li>
                    <li>pénzügyi adatok: befizetés/kifizetés tranzakciók, egyenlegek,</li>
                    <li>fogadási adatok: szelvények, tétek, oddsok, eredmények,</li>
                    <li>technikai adatok: munkamenet- és naplóadatok, biztonsági események.</li>
                </ul>
            </div>

            <h4>2. Az adatkezelés céljai</h4>
            <div class="additional-info">
                Az adatokat különösen az alábbi célokból kezeljük:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>felhasználói fiók létrehozása és kezelése,</li>
                    <li>fogadási szolgáltatás biztosítása és tranzakciók feldolgozása,</li>
                    <li>jogi kötelezettségek teljesítése (pl. számviteli, panaszkezelési kötelezettségek),</li>
                    <li>visszaélések megelőzése, rendszerbiztonság fenntartása,</li>
                    <li>ügyfélszolgálati megkeresések kezelése.</li>
                </ul>
            </div>

            <h4>3. Az adatkezelés jogalapja</h4>
            <div class="help-menu-container">
                Az adatkezelés jogalapja az eset jellegétől függően:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>szerződés teljesítése (felhasználói fiók és szolgáltatás nyújtása),</li>
                    <li>jogi kötelezettség teljesítése,</li>
                    <li>jogos érdek (pl. csalásmegelőzés, rendszerbiztonság),</li>
                    <li>hozzájárulás, ahol a jogszabály ezt megköveteli.</li>
                </ul>
            </div>

            <h4>4. Adatmegőrzési idő</h4>
            <div class="additional-info">
                Az adatokat csak a szükséges ideig tároljuk. Az adatmegőrzés időtartamát a szolgáltatás működése,
                a jogszabályi kötelezettségek, valamint a jogos érdek alapján határozzuk meg.
                A megőrzési idő lejárta után az adatokat töröljük vagy anonimizáljuk.
            </div>

            <h4>5. Adatbiztonság</h4>
            <div class="additional-info">
                Megfelelő technikai és szervezési intézkedésekkel védjük az adatokat az illetéktelen hozzáférés,
                módosítás, nyilvánosságra hozatal vagy elvesztés ellen. A biztonsági kontrollok folyamatosan
                felülvizsgálatra kerülnek.
            </div>

            <h4>6. Érintetti jogok</h4>
            <div class="help-menu-container">
                A felhasználókat az adatkezeléssel kapcsolatban több jog is megilleti, különösen:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>tájékoztatáshoz való jog,</li>
                    <li>hozzáféréshez való jog,</li>
                    <li>helyesbítéshez való jog,</li>
                    <li>törléshez való jog (jogszabályi keretek között),</li>
                    <li>adatkezelés korlátozásához való jog,</li>
                    <li>tiltakozáshoz való jog,</li>
                    <li>adathordozhatósághoz való jog.</li>
                </ul>
            </div>

            <h4>7. Kapcsolat és jogérvényesítés</h4>
            <div class="additional-info">
                Adatkezeléssel kapcsolatos kérdés vagy kérelem esetén kérjük, vedd fel a kapcsolatot ügyfélszolgálatunkkal.
                Panasz esetén jogosult vagy a hatáskörrel rendelkező felügyeleti hatósághoz fordulni.
            </div>

            <div class="help-menu-container">
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