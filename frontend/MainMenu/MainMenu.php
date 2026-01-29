<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <title>Tipp oldal – váz</title>
  <link rel="stylesheet" href="../../css/MainMenu/MainMenu.css">
  <link rel="stylesheet" href="../../css/Main/main.css">
  <link rel="icon" href="../img/logo.png" type="image/x-icon">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=search"/>
</head>
<body>

<header class="header">

  <div class="logo-box">
    <a href="../frontend/index.html"><img class="kep" src="../../img/logo.png" alt="logo"></a>
    <div class="logo"><a href="../frontend/index.html" class="mainpage">BetMatchBonus</a></div>
  </div>

  <nav class="nav">
    <a href="../../frontend/MainMenu/MainMenu.php">Főoldal</a>
    <a href="../../frontend/Live/live.php">Élő</a>
    <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>
    <a href="../../frontend/Help/help.php">Segítség</a>
  </nav>

  <div class="right_side">
    <button class="loginbtn" onclick="location.href='login.html'">Bejelentkezés</button>
    <button class="registrationbtn" onclick="location.href='../register/register.html'">Regisztráció</button>
  </div>

</header>
<div class="main_content">

  <aside class="left-sidebar">
    <div class="time-bar">
      <span class="label">Idő:</span>
      <span id="currentDateTime"></span>
    </div>

    
    <div class="sports-menu-container">
      <form>
        <div class="search">
          <span class="search-icon material-symbols-outlined">search</span>
          <input class="search-input" type="search" placeholder="Keresés">
        </div>

        <div class="sports-menu">

          <details class="level0">
            <summary>🏟️ Sportágak</summary>

            <details class="level1">
              <summary>⚽ Foci</summary>

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
          </details>

        </div>
      </form>
    </div>
  </aside>


  <main class="center-content">
    <h2>Középső rész</h2>
    gatya van javitsam ki kereso közép föntre rakjam át sportágakat fejezzem be apit elötte megnézni 
      milyen sportágakat akarunk jobb oldal fogados rész elkezdése csak borderral külön görgetés mint három containerre időt mshova rakjam át </p>

  </main>

  
  <aside class="right-sidebar">
    <h2>Fogadós rész</h2>
    <p>Itt lesz majd a fogadási blokk.</p>
  </aside>

</div>


<footer class="footer">
  Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2025. Minden jog fenntartva.
</footer>

<script src="../js/main.js"></script>
</body>
</html>






