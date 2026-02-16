<!DOCTYPE html>
<html lang="hu">

<head>
  <meta charset="UTF-8">
  <title>Online fogadás | BetMatchBonus</title>
  <link rel="stylesheet" href="../../css/MainMenu/MainMenu.css">
  <link rel="stylesheet" href="../../css/Main/layout.css">
  <link rel="stylesheet" href="../../css/RootColor/root.css">
  <link rel="icon" href="../../img/logo.png" type="image/x-icon">
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=search" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
  </head>

<body>

  <header class="header">

    <div class="logo-box">
      <a href="../../frontend/MainMenu/MainMenu.php"><img class="kep" src="../../img/logo.png" alt="logo"></a>
      <div class="logo"><a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a></div>
    </div>


    <!-- <nav class="nav">
      <a href="../../frontend/MainMenu/MainMenu.php" class="active">Főoldal</a>

      <a href="../../frontend/Live/live.php">Élő</a>

      <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>

      <a href="../../frontend/Help/help.php">Segítség</a>
    </nav> -->
    <nav class="navbar navbar-expand-lg p-0">

  <!-- Hamburger BAL OLDALON -->
  <button class="navbar-toggler me-auto" type="button"
    data-bs-toggle="collapse"
    data-bs-target="#mainNavbar"
    aria-controls="mainNavbar"
    aria-expanded="false"
    aria-label="Menü">
    <span class="navbar-toggler-icon"></span>
  </button>

  <!-- Menü -->
  <div class="collapse navbar-collapse" id="mainNavbar">
    <ul class="navbar-nav flex-column flex-lg-row gap-lg-4">

      <li class="nav-item">
        <a class="nav-link active text-warning"
           href="../../frontend/MainMenu/MainMenu.php">
           Főoldal
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link"
           href="../../frontend/Live/live.php">
           Élő
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link"
           href="../../frontend/Bonus/bonus.php">
           Bónuszok
        </a>
      </li>

      <li class="nav-item">
        <a class="nav-link"
           href="../../frontend/Help/help.php">
           Segítség
        </a>
      </li>

    </ul>
  </div>

</nav>


    <div class="right_side">
      <button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
      <button class="registrationbtn"
        onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
    </div>

  </header>
  <div class="main_content">

    <aside class="left-sidebar">
      <div class="time-bar">

        <span id="currentDateTime"></span>
      </div>


      <div class="sports-menu-container">
        <form>
          <div class="search">
            <span class="search-icon material-symbols-outlined">search</span>
            <input class="search-input" type="search" placeholder="Keresés">
          </div>

          <div class="sports-menu">



            <details class="level1">
              <summary>&#x26BD; Foci</summary>

              <details class="level2">
                <summary>NB I</summary>
                <ul class="level3">
                  <li><a href="#">Fradi – Újpest</a></li>
                  <li><a href="#">PAFC – Debrecen</a></li>
                </ul>
              </details>

              <details class="level2">
                <summary>Premier League</summary>
                <ul class="level3">
                  <li><a href="#">Arsenal – Chelsea</a></li>
                  <li><a href="#">Liverpool – City</a></li>
                </ul>
              </details>

            </details>

            <h1>Ide jönnek még a sportok csak angular js el csinalom meg </h1>




          </div>

        </form>
      </div>
      <div class="temp_cont">

      </div>
    </aside>


    <main class="center-content">
      <h2>Középső rész</h2>
      gatya van javitsam ki kereso közép föntre rakjam át sportágakat fejezzem be apit elötte megnézni
      milyen sportágakat akarunk jobb oldal fogados rész elkezdése csak borderral külön görgetés mint három containerre
      időt mshova rakjam át </p>
      <p>footer ide aljra többi görgetősre egész oldam mére</p>
      <h1>Ide jönnek még a sportok csak angular js el csinalom meg </h1>
      <h1>Az alap szinek meg betűtipus :root al csinaljuk meg holnap megbeszelni az alap szint és navbar szint</h1>

      <div class="temp_cont">

      </div>

        <footer class="simple-footer">
    <div class="footer-top">
      <div class="footer-links">
        <a href="../Help/adatkezelesi_tajekoztatok.php" class="footer-link">ADATKEZELÉSI TÁJÉKOZTATÓ</a>
        <a href="../Help/reszveteli-szabalyzat.php" class="footer-link">RÉSZVÉTELI SZABÁLYZAT</a>
        <a href="../Help/kapcsolat.php" class="footer-link">UGYFELSZOLGALAT@BETMATCHBONUS.COM</a>
        <a href="../Help/GYIK.php" class="footer-link">GYIK</a>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="footer-content">
        <div class="responsible-text">
          <h2>Ajánlott felelős szervező!</h2>
          <p>Maradjon játék! 18+. A túlzásba vitt szerencsejáték ártalmas, függőséget okozhat! Kérje
            bejegyzését a játékosvédelmi nyilvántartásba!
            <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank">Tudj meg többet!</a>
          </p>
        </div>
        <p>Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>
      </div>
    </div>
  </footer>

    </main>


    <aside class="right-sidebar">
      <h2>Fogadós rész</h2>
      <p>Itt lesz majd a fogadási blokk.</p>

      <div class="temp_cont">

      </div>
    </aside>

  </div>

      <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 10px;">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2"> <a
                            href="../../frontend/MainMenu/MainMenu.php">
                            <img src="../../img/logo.png" alt="logo" style="height: 28px; cursor: pointer;"></a>Bejelentkezés</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body"> <label class="form-label">Felhasználónév vagy e-mail cím</label> <input
                        type="text" class="form-control mb-3"> <label class="form-label">Jelszó</label> <input
                        type="password" class="form-control mb-2"> <a href="#" class="small"
                        style="color:#3498db;">Elfelejtettem a jelszavam</a> </div>
                <div class="modal-footer d-flex flex-column"> <button
                        class="btn btn-success w-100 mb-2">Bejelentkezés</button>
                    <p class="m-0"> Még nincs fiókod? <a href="../../frontend/Register/register.php"
                            style="color:#3498db;">Regisztrálj!</a> </p>
                </div>
            </div>
        </div>
    </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../js/MainMenu/main.js"></script>
</body>

</html>