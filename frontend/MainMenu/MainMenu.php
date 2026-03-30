<?php
require_once "../../backend/ApiRequest/connect.php";
?>
<!DOCTYPE html>
<html lang="hu">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Online fogadás | BetMatchBonus</title>
  <link rel="stylesheet" href="../../css/MainMenu/MainMenu.css">
  <link rel="stylesheet" href="../../css/Main/layout.css">
  <link rel="stylesheet" href="../../css/Betslip/betslip.css">
  <link rel="stylesheet" href="../../css/Main/popup.css">
  <link rel="stylesheet" href="../../css/Modal/modal.css">
  <link rel="stylesheet" href="../../css/RootColor/root.css">
  <link rel="icon" href="../../img/logo.png" type="image/x-icon">
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=search" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>

<body>
<?php include '../../frontend/Components/cookie_consent.php'; ?>

<header class="header">
    <div class="header-top-row">
        <button class="navbar-toggler navbar-dark" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                aria-controls="mainNavbar" aria-expanded="false" aria-label="Menü">
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
        <a href="../../frontend/MainMenu/MainMenu.php" class="active">Főoldal</a>
        <a href="../../frontend/Live/live.php">Élő</a>
        <a href="../../frontend/Esport/esport.php">eSport</a>
        <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>
        <a href="../../frontend/Help/help.php">Segítség</a>
    </nav>
</header>

  <div class="content-parent">
    <div class="right-container">
      <?php include '../../frontend/Components/betslip.php'; ?>
    </div>

    <div class="main_content">

      <aside class="left-sidebar">
        <div class="time-bar">
          <span id="currentDateTime"></span>
        </div>

        <div class="sports-menu-container">
            <div class="specialis-menu">
              <div class="specialis-menu-header">
                <i class="fas fa-star"></i> <span data-i18n="mainMenu.special">Speciális</span>
              </div>
              <div class="specialis-menu-items">
                <a href="../../frontend/Bonus/bonus.php" class="specialis-menu-item">
                  <i class="fas fa-rocket"></i>
                  <span data-i18n="mainMenu.oddsShip">Oddsűrhajó</span>
                </a>
              </div>
            </div>

            <div class="sports-list" id="sportsList">
              <div class="sidebar-loading"><i class="fas fa-spinner fa-spin"></i> <span data-i18n="mainMenu.loading">Betöltés...</span></div>
            </div>

            <div class="sport-detail-panel" id="sportDetailPanel" style="display:none;">
              <button class="sidebar-back-btn" id="sidebarBackBtn"><i class="fas fa-arrow-left"></i> <span data-i18n="mainMenu.allSports">Összes sport</span></button>
              <div id="sportDetailContent"></div>
            </div>
        </div>
      </aside>

      <main class="center-content">
        <div class="center-header">
          <h2 class="section-title" id="centerTitle"><i class="fas fa-calendar-day"></i> <span data-i18n="mainMenu.todayMatches">Mai meccsek</span></h2>
          <div class="center-search">
            <input type="search" id="matchSearch" class="match-search-input" placeholder="Meccs keresése..." data-i18n-placeholder="mainMenu.searchPlaceholder">
          </div>
        </div>

        <div id="matches-container">
          <div class="loading-details"><i class="fas fa-spinner fa-spin"></i> <span data-i18n="mainMenu.loadingMatches">Meccsek betöltése...</span></div>
        </div>
      </main>

      <!-- remove the old right-sidebar block entirely -->
      <?php /* right-sidebar removed: betslip is rendered in .content-parent like on live.php */ ?>

    </div>
  </div>

  <?php include '../../frontend/Components/footer.php';?>
  <?php include '../../frontend/Components/loginmodal.php';?>
  <?php include '../../frontend/Components/registermodal.php';?>
  <?php include '../../frontend/Components/registermodal2.php'; ?>
  <?php include '../../frontend/Components/forgotmypassword.php'; ?>
  <script src="../../js/Main/auth_ui.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../js/Login/loginmodal.js"></script>
  <script src="../../js/Register/registermodal.js"></script>
  <script src="../../js/Register/registermodal2.js"></script>
  <script src="../../js/Forgotmypassword/forgotmypassword.js"></script>
  <script src="../../js/Main/language.js"></script>
  <script src="../../js/Main/layout.js"></script>
  <script src="../../js/Main/popup.js"></script>
  <script src="../../js/Betslip/betslip.js"></script>
  <script src="../../js/MainMenu/main.js"></script>

  <?php include '../../frontend/Components/chatbot.php'; ?>
</body>
</html>