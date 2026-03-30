<?php
require_once "../../backend/ApiRequest/connect.php";
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSport | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Esport/esport.css">
    <link rel="stylesheet" href="../../css/Betslip/betslip.css">
    <link rel="stylesheet" href="../../css/Main/popup.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Modal/modal.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>

    <header class="header">
        <div class="header-top-row">
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Menü">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="logo-box">
                <a href="../MainMenu/MainMenu.php">
                    <img class="kep" src="../../img/logo.png" alt="logo">
                </a>
                <div class="logo">
                    <a href="../MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a>
                </div>
            </div>

            <div class="right_side">
                <div class="lang-switcher">
                    <button class="translateBtn" id="btn-hu">
                        <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                            <rect width="9" height="2" y="0" fill="#c8102e" />
                            <rect width="9" height="2" y="2" fill="#ffffff" />
                            <rect width="9" height="2" y="4" fill="#436f4d" />
                        </svg>
                    </button>
                    <div class="lang-dropdown" id="lang-dropdown">
                        <button class="lang-btn" id="btn-hu-switch" title="Magyar">
                            <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                                <rect width="9" height="2" y="0" fill="#c8102e" />
                                <rect width="9" height="2" y="2" fill="#ffffff" />
                                <rect width="9" height="2" y="4" fill="#436f4d" />
                            </svg>
                        </button>
                        <button class="lang-btn" id="btn-en" title="English">
                            <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                                <rect width="9" height="6" fill="#ffffff" />
                                <rect x="4" width="1" height="6" fill="#c8102e" />
                                <rect y="2.5" width="9" height="1" fill="#c8102e" />
                            </svg>
                        </button>
                    </div>
                </div>
                <?php include '../../frontend/Components/login.php'; ?>
            </div>
        </div>

        <nav class="nav collapse navbar-collapse" id="mainNavbar">
            <a href="../../frontend/MainMenu/MainMenu.php">Főoldal</a>
            <a href="../../frontend/Live/live.php">Élő</a>
            <a href="../../frontend/Esport/esport.php" class="active">eSport</a>
            <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>
            <a href="../../frontend/Help/help.php">Segítség</a>
        </nav>
    </header>

    <div class="content-parent">
        <div class="right-container">
            <?php include '../../frontend/Components/betslip.php'; ?>
        </div>
        <div class="elo-main">
            <div class="elo-container">
                <h1 class="elo-title"><i class="fas fa-gamepad"></i> <span data-i18n="esport.title">eSport</span></h1>

                <!-- eSport sport szűrő nav -->
                <nav class="esport-sports-nav" id="esportSportsNav">
                    <!-- JS-ből épül fel dinamikusan -->
                </nav>

                <div class="tabs-container">
                    <button class="tab-button active" data-tab="today">
                        <i class="fas fa-calendar-day"></i> <span data-i18n="esport.allTodayMatches">Összes mai meccs</span>
                        <span class="esport-today-count" id="esport-today-badge">-</span>
                    </button>
                    <button class="tab-button" data-tab="live">
                        <i class="fas fa-broadcast-tower"></i> <span data-i18n="esport.liveMatches">Élő meccsek</span>
                        <span class="esport-live-count" id="esport-live-badge">-</span>
                    </button>
                </div>

                <div class="tab-content active" id="tab-today">
                    <div id="today-matches-container">
                        <div class="loading-details"><i class="fas fa-spinner fa-spin"></i> <span data-i18n="esport.loadingToday">Mai meccsek betöltése...</span>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="tab-live">
                    <div id="matches-container">
                        <div class="loading-details"><i class="fas fa-spinner fa-spin"></i> <span data-i18n="esport.loadingLive">Élő meccsek betöltése...</span></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Login/loginmodal.js"></script>
    <script src="../../js/Register/registermodal.js"></script>
    <script src="../../js/Register/registermodal2.js"></script>
    <script src="../../js/Main/language.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <script src="../../js/Main/popup.js"></script>
    <script src="../../js/Main/auth_ui.js"></script>
    <script src="../../js/Betslip/betslip.js"></script>
    <script src="../../js/Esport/esport.js"></script>
    <?php include '../../frontend/Components/loginmodal.php'; ?>
    <?php include '../../frontend/Components/registermodal.php'; ?>
    <?php include '../../frontend/Components/registermodal2.php'; ?>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>