<?php
require_once "../../backend/connect.php";
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Élő meccsek | BetMatchBonus</title>
    <!-- Vendor CSS first -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- App CSS after vendors -->
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Main/layout.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../css/Live/live.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../css/Betslip/betslip.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../css/Main/popup.css">
    <link rel="stylesheet" href="../../css/Modal/modal.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">

</head>

<body>
    <?php include '../../frontend/Components/cookie_consent.php'; ?>
    <?php include '../../frontend/Components/disclaimer.php'; ?>

    <header class="header">
        <div class="header-top-row">
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Menü">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="logo-box">
                <a href="../../frontend/MainMenu/MainMenu.php">
                    <img class="kep" src="../../img/logo.png" alt="logo">
                </a>
                <div class="logo">
                    <a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a>
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
            <a href="../../frontend/MainMenu/MainMenu.php"><span data-i18n="nav.home">Főoldal</span></a>
            <a href="../../frontend/Live/live.php" class="active"><span data-i18n="nav.live">Élő</span></a>
            <a href="../../frontend/Esport/esport.php"><span data-i18n="nav.esport">eSport</span></a>
            <a href="../../frontend/Bonus/bonus.php"><span data-i18n="nav.bonuses">Bónuszok</span></a>
            <a href="../../frontend/Help/help.php"><span data-i18n="nav.help">Segítség</span></a>
        </nav>
    </header>

    <div class="content-parent">
        <div class="right-container">
            <div class="live-betslip-slot" id="live-betslip-slot" aria-hidden="true">
                <button type="button" class="mobile-betslip-close" id="mobile-betslip-close" aria-label="Szelvény bezárása">
                    <i class="fas fa-times"></i>
                </button>
                <?php include '../../frontend/Components/betslip.php'; ?>
            </div>
        </div>
        <div class="elo-main">
            <div class="elo-container">
                <h1 class="elo-title" id="elo-title" data-i18n="live.title">Élő meccsek</h1>

                <div class="sports-nav-wrapper">
                    <nav class="sports-nav" id="liveSportsNav">
                        <div class="sports-nav-loading"><i class="fas fa-spinner fa-spin"></i> <span data-i18n="live.loadingSports">Sportok betöltése...</span></div>
                    </nav>
                </div>
                <br>

                <!-- Kereső -->
                <div class="live-search-wrapper">
                    <div class="live-search-box">
                        <i class="fas fa-search live-search-icon"></i>
                        <input type="text" id="liveSearchInput" class="live-search-input" placeholder="Keresés csapat vagy bajnokság neve alapján..." autocomplete="off">
                        <button type="button" id="liveSearchClear" class="live-search-clear" style="display:none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                
                <div class="tabs-container">
                    <button class="tab-button active" data-i18n="live.liveMatches">Élő meccsek</button>
                </div>

                <div id="matches-container">
                    <?php
                    // Csak a táblázatot jelenítjük meg, az API frissítést a live.js AJAX-szal csinálja
                    include '../../backend/ApiRequest/live_table.php';
                    ?>
                </div>

                <!-- Eredmény feed + Közelgő meccsek egymás mellett -->
                <div class="live-bottom-row">
                    <!-- Bal: Eredmény feed -->
                    <div class="score-feed-panel" id="score-feed-panel">
                        <div class="panel-header panel-header-live">
                            <span class="live-ticker-dot"></span>
                            <span>📊 Eredmény feed</span>
                        </div>
                        <div class="score-feed-list" id="goal-toast-container"></div>
                    </div>

                    <!-- Jobb: Közelgő meccsek -->
                    <div class="upcoming-section" id="upcoming-section">
                        <div class="panel-header panel-header-upcoming">
                            <i class="fas fa-clock"></i>
                            <span data-i18n="live.upcomingTitle">Hamarosan kezdődik</span>
                        </div>
                        <div class="upcoming-list" id="upcoming-list"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <button type="button" class="mobile-betslip-fab" id="mobile-betslip-fab" aria-expanded="false" aria-controls="live-betslip-slot">
        <span class="mobile-betslip-fab-icon"><i class="fas fa-cart-shopping"></i></span>
        <span class="mobile-betslip-fab-label" data-i18n="betslip.ticket">Szelvény</span>
        <span class="mobile-betslip-fab-count" id="mobile-betslip-fab-count">0</span>
    </button>
    <div class="mobile-betslip-backdrop" id="mobile-betslip-backdrop" aria-hidden="true"></div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Login/loginmodal.js?v=<?= time() ?>"></script>
    <script src="../../js/Register/registermodal.js?v=<?= time() ?>"></script>
    <script src="../../js/Register/registermodal2.js?v=<?= time() ?>"></script>
    <script src="../../js/Main/language.js?v=<?= time() ?>"></script>
    <script src="../../js/Main/layout.js?v=<?= time() ?>"></script>
    <script src="../../js/Main/popup.js?v=<?= time() ?>"></script>
    <script src="../../js/Betslip/betslip.js?v=<?= time() ?>"></script>
    <script src="../../js/Main/auth_ui.js?v=<?= time() ?>"></script>
    <script src="../../js/Live/live.js?v=<?= time() ?>"></script>
    <?php include '../../frontend/Components/loginmodal.php'; ?>
    <?php include '../../frontend/Components/registermodal.php'; ?>
    <?php include '../../frontend/Components/registermodal2.php'; ?>
    <?php include '../../frontend/Components/chatbot.php'; ?>
</body>

</html>