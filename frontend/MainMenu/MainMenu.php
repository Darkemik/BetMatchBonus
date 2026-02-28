<?php
require_once "../../backend/ApiRequest/connect.php";            

$sql = "
SELECT 
    m.name AS match_name,
    m.start_utc,
    m.is_live,
    m.live_time,
    c.name AS country_name,
    ch.name AS championship_name
FROM Matches m
JOIN Championships ch ON m.championship_id = ch.id
JOIN Countries c ON ch.country_code = c.code
ORDER BY m.start_utc
";

$matchesResult = $mysqli->query($sql);
?>
<!DOCTYPE html>
<html lang="hu">

<head>
  <meta charset="UTF-8">
  <title>Online fogadás | BetMatchBonus</title>
  <link rel="stylesheet" href="../../css/MainMenu/MainMenu.css">
  <link rel="stylesheet" href="../../css/Main/layout.css">
  <link rel="stylesheet" href="../../css/Betslip/betslip.css">
  <link rel="stylesheet" href="../../css/RootColor/root.css">
  <link rel="stylesheet" href="../../css/Modal/modal.css">
  <link rel="icon" href="../../img/logo.png" type="image/x-icon">
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=search" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
  </head>

<body>

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
            <button class="loginbtn" data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
            <button class="registrationbtn" data-bs-toggle="modal" data-bs-target="#registerModal">Regisztráció</button>
        </div>
    </div>

    <nav class="nav collapse navbar-collapse" id="mainNavbar">
        <a href="../../frontend/MainMenu/MainMenu.php" class="active">Főoldal</a>
        <a href="../../frontend/Live/live.php">Élő</a>
        <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>
        <a href="../../frontend/Help/help.php">Segítség</a>
    </nav>
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
      <h2 class="text-center mb-4">Mai meccsek (teszt)</h2>

    <?php if ($matchesResult && $matchesResult->num_rows > 0): ?>
        <table class="table table-striped table-bordered w-75 mx-auto">
            <thead class="table-dark">
                <tr>
                    <th>Ország</th>
                    <th>Bajnokság</th>
                    <th>Meccs</th>
                    <th>Kezdés (UTC)</th>
                    <th>Élő?</th>
                    <th>Élő idő</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $matchesResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['country_name']) ?></td>
                        <td><?= htmlspecialchars($row['championship_name']) ?></td>
                        <td><?= htmlspecialchars($row['match_name']) ?></td>
                        <td><?= htmlspecialchars($row['start_utc']) ?></td>
                        <td><?= $row['is_live'] ? 'Igen' : 'Nem' ?></td>
                        <td><?= htmlspecialchars($row['live_time']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-center">Jelenleg nincs megjeleníthető meccs az adatbázisban.</p>
    <?php endif; ?>

        <?php include '../../frontend/Components/footer.php';?>

    </main>


    <aside class="right-sidebar">
      <?php include '../../frontend/Components/betslip.php'; ?>

      <div class="temp_cont">

      </div>
    </aside>

  </div>

  <?php include '../../frontend/Components/loginmodal.php';?>
  <?php include '../../frontend/Components/registermodal.php';?>
    <?php include '../../frontend/Components/registermodal2.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../js/Login/loginmodal.js"></script>
  <script src="../../js/Register/registermodal.js"></script>
  <script src="../../js/Register/registermodal2.js"></script>
  <script src="../../js/Main/layout.js"></script>
  <script src="../../js/Betslip/betslip.js"></script>
  <script src="../../js/MainMenu/main.js"></script>
</body>

</html>