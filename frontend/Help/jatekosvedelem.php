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
            <h1>Játékosvédelem</h1>

            <div class="help-menu-container">
                A BetMatchBonus elkötelezett a felelős játékszervezés mellett.
                A fogadás szórakozás, nem megélhetési forma.
                Kérjük, mindig tudatosan, előre meghatározott kerettel és kontrollált döntésekkel játssz.
            </div>

            <h4>Felelős játék alapelvei</h4>
            <div class="additional-info">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>Csak olyan összeget használj, amelynek elvesztése nem okoz problémát.</li>
                    <li>Állíts be saját napi/heti pénzügyi keretet, és tartsd magad hozzá.</li>
                    <li>Ne próbáld visszanyerni az elvesztett összegeket gyors, impulzív fogadásokkal.</li>
                    <li>Alkohol vagy erős érzelmi állapot alatt ne hozz fogadási döntéseket.</li>
                </ul>
            </div>

            <h4>Figyelmeztető jelek</h4>
            <div class="additional-info">
                Érdemes szünetet tartani, ha az alábbiak közül többet is tapasztalsz:
                <ul style="margin: 10px 0 0; padding-left: 18px;">
                    <li>a játék miatt romlik a koncentrációd, alvásod vagy hangulatod,</li>
                    <li>egyre magasabb tétekkel játszol ugyanazért az élményért,</li>
                    <li>elrejted környezeted elől a fogadási aktivitásodat,</li>
                    <li>pénzügyi nehézség mellett is folytatod a játékot.</li>
                </ul>
            </div>

            <h4>Mit tehetsz azonnal?</h4>
            <div class="help-menu-container">
                <ul style="margin: 0; padding-left: 18px;">
                    <li>Tarts legalább 24-48 órás szünetet, és értékeld újra a játékhoz való viszonyodat.</li>
                    <li>Csökkentsd az aktivitásodat, és használj alacsonyabb téteket.</li>
                    <li>Beszélj valakivel, akiben megbízol (családtag, barát, szakember).</li>
                    <li>Ha szükséges, kérj professzionális segítséget játékfüggőséggel foglalkozó szervezetektől.</li>
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