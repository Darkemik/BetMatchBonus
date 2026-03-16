<?php
require_once "../../backend/ApiRequest/connect.php";
?>
<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Élő meccsek | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Live/live.css">
    <link rel="stylesheet" href="../../css/Betslip/betslip.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="stylesheet" href="../../css/Modal/modal.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

</head>

<body>
    <header class="header">
        <div class="header-top-row">
            <button class="navbar-toggler navbar-dark" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Menü">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="logo-box">
                <a href="../frontend/index.html">
                    <img class="kep" src="../../img/logo.png" alt="logo">
                </a>
                <div class="logo">
                    <a href="../frontend/index.html" class="mainpage">BetMatchBonus</a>
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
            <a href="../../frontend/Live/live.php" class="active">Élő</a>
            <a href="../../frontend/Esport/esport.php">eSport</a>
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
                <h1 class="elo-title" id="elo-title">Élő meccsek</h1>

                <div class="sports-nav-wrapper">
                    <nav class="sports-nav">
                        <a href="#" id="btn-soccer" class="sport-item active" data-sport="soccer">
                            <div class="sport-icon">
                                <i class="fas fa-futbol"></i>
                            </div>
                            <span class="sport-name">Labdarúgás</span>
                            <span class="sport-count" data-sport-id="66">-</span>
                        </a>
                        <a href="#" class="sport-item" data-sport="basketball">
                            <div class="sport-icon">
                                <i class="fas fa-basketball-ball"></i>
                            </div>
                            <span class="sport-name">Kosárlabda</span>
                            <span class="sport-count" data-sport-id="67">-</span>
                        </a>
                        <a href="#" class="sport-item" data-sport="darts">
                            <div class="sport-icon">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <span class="sport-name">Darts</span>
                            <span class="sport-count" data-sport-id="78">-</span>
                        </a>
                        <a href="#" class="sport-item" data-sport="waterpolo">
                            <div class="sport-icon">
                                <i class="fas fa-swimmer"></i>
                            </div>
                            <span class="sport-name">Vízilabda</span>
                            <span class="sport-count" data-sport-id="83">-</span>
                        </a>
                        <a href="#" class="sport-item" data-sport="handball">
                            <div class="sport-icon">
                                <i class="fas fa-hand-rock"></i>
                            </div>
                            <span class="sport-name">Kézilabda</span>
                            <span class="sport-count" data-sport-id="73">-</span>
                        </a>
                        <a href="#" class="sport-item" data-sport="hockey">
                            <div class="sport-icon">
                                <i class="fas fa-hockey-puck"></i>
                            </div>
                            <span class="sport-name">Jégkorong</span>
                            <span class="sport-count" data-sport-id="70">-</span>
                        </a>
                        <a href="#" class="sport-item" data-sport="pingpong">
                            <div class="sport-icon">
                                <i class="fas fa-table-tennis"></i>
                            </div>
                            <span class="sport-name">Pingpong</span>
                            <span class="sport-count" data-sport-id="77">-</span>
                        </a>
                    </nav>
                </div>
                <br>

                <div class="tabs-container">
                    <button class="tab-button active">Élő meccsek</button>
                </div>

                <div id="matches-container">
                    <?php
                    // Csak a táblázatot jelenítjük meg, az API frissítést a live.js AJAX-szal csinálja
                    include '../../backend/ApiRequest/live_table.php';
                    ?>
                </div>

            </div>
        </div>
    </div>

    <?php include '../../frontend/Components/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Login/loginmodal.js"></script>
    <script src="../../js/Register/registermodal.js"></script>
    <script src="../../js/Register/registermodal2.js"></script>
    <script src="../../js/Main/layout.js"></script>
    <script src="../../js/Betslip/betslip.js"></script>
    <script src="../../js/Main/auth_ui.js"></script>
    <script src="../../js/Live/live.js"></script>
    <?php include '../../frontend/Components/loginmodal.php'; ?>
    <?php include '../../frontend/Components/registermodal.php'; ?>
    <?php include '../../frontend/Components/registermodal2.php'; ?>
</body>

</html>