<?php
require_once "../../backend/ApiRequest/connect.php";

// Mai meccsek lekérdezése időrendi sorrendben
$today = date('Y-m-d');

$sql = "
SELECT 
    e.name AS match_name,
    e.start_time,
    e.is_live,
    e.live_time,
    e.home_score,
    e.away_score,
    c.name AS country_name,
    comp.name AS competition_name,
    s.api_id AS sport_api_id
FROM Events e
JOIN Competitions comp ON e.competition_id = comp.id
JOIN Sports s ON e.sport_id = s.id
LEFT JOIN Countries c ON comp.country_id = c.id
WHERE DATE(e.start_time) = ?
ORDER BY e.is_live DESC, e.start_time ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $today);
$stmt->execute();
$matchesResult = $stmt->get_result();

// Sportok listája a nav gombokhoz
$sportsSql = "
SELECT s.api_id, s.name, COUNT(e.id) AS match_count
FROM Sports s
LEFT JOIN Events e ON e.sport_id = s.id AND DATE(e.start_time) = ?
WHERE s.is_active = 1
GROUP BY s.id
ORDER BY match_count DESC, s.name
";
$stmtSports = $conn->prepare($sportsSql);
$stmtSports->bind_param("s", $today);
$stmtSports->execute();
$sportsResult = $stmtSports->get_result();

$sportIcons = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    78  => 'fa-bullseye',
    83  => 'fa-swimmer',
    73  => 'fa-hand-rock',
    70  => 'fa-hockey-puck',
    145 => 'fa-gamepad',
    77  => 'fa-table-tennis'
];

$sportNames = [
    66  => 'Labdarúgás',
    67  => 'Kosárlabda',
    78  => 'Darts',
    83  => 'Vízilabda',
    73  => 'Kézilabda',
    70  => 'Jégkorong',
    145 => 'eSport',
    77  => 'Pingpong'
];

// Meccsek csoportosítása
$liveMatches = [];
$upcomingMatches = [];
$allMatches = [];
$now = new DateTime('now', new DateTimeZone('Europe/Budapest'));
$twoHoursLater = clone $now;
$twoHoursLater->modify('+2 hours');

if ($matchesResult && $matchesResult->num_rows > 0) {
    while ($row = $matchesResult->fetch_assoc()) {
        $allMatches[] = $row;
        if ($row['is_live']) {
            $liveMatches[] = $row;
        } else {
            $matchTime = new DateTime($row['start_time'], new DateTimeZone('Europe/Budapest'));
            if ($matchTime >= $now && $matchTime <= $twoHoursLater) {
                $upcomingMatches[] = $row;
            }
        }
    }
}
$stmt->close();
$stmtSports->close();
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
  <link rel="stylesheet" href="../../css/RootColor/root.css">
  <link rel="stylesheet" href="../../css/Modal/modal.css">
  <link rel="icon" href="../../img/logo.png" type="image/x-icon">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
  </head>
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=search" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
        <div id="userMenu" class="dropdown" style="display:none;">
  <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
    <span id="userMenuUsername">Fiókom</span>
  </button>

  <ul class="dropdown-menu dropdown-menu-end">
    <li class="dropdown-header">
      <div><strong id="userFullName">-</strong></div>
      <div style="font-size: 12px;" id="userEmail">-</div>
    </li>
    <li><hr class="dropdown-divider"></li>
    <li><button class="dropdown-item" id="logoutBtn" type="button">Kijelentkezés</button></li>
  </ul>
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

  <div class="main_content">

    <!-- ===== BAL OLDALI SIDEBAR ===== -->
    <aside class="left-sidebar">
      <div class="time-bar">
        <span id="currentDateTime"></span>
      </div>

      <div class="sports-menu-container">
        <form onsubmit="return false;">
          <div class="search">
            <span class="search-icon material-symbols-outlined">search</span>
            <input class="search-input" id="sidebarSearch" type="search" placeholder="Keresés...">
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

            <!-- TODO: További sportágak hozzáadása -->

          </div>
        </form>
      </div>
    </aside>

    <!-- ===== KÖZÉPSŐ TARTALOM ===== -->
    <main class="center-content">
      <!-- TODO: Keresőt középre, sportágak befejezése, jobb oldali fogadás rész -->

      <div class="temp_cont">
      </div>
      <h2 class="text-center mb-4">Mai meccsek</h2>

    <?php if ($matchesResult && $matchesResult->num_rows > 0): ?>
        <table class="table table-striped table-bordered w-75 mx-auto">
            <thead class="table-dark">
                <tr>
                    <th>Ország</th>
                    <th>Bajnokság</th>
                    <th>Meccs</th>
                    <th>Kezdés</th>
                    <th>Élő?</th>
                    <th>Élő idő</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $matchesResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['country_name'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($row['competition_name']) ?></td>
                        <td><?= htmlspecialchars($row['match_name']) ?></td>
                        <td><?= htmlspecialchars($row['start_time']) ?></td>
                        <td><?= $row['is_live'] ? 'Igen' : 'Nem' ?></td>
                        <td><?= htmlspecialchars($row['live_time'] ?? '–') ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="no-matches">
            <i class="fas fa-calendar-times" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>
            Jelenleg nincs megjeleníthető meccs a mai napra.
          </div>
        <?php endif; ?>
        </div>
      </div>

      <?php include '../../frontend/Components/footer.php';?>

    </main>

    <!-- ===== JOBB OLDALI SIDEBAR ===== -->
    <aside class="right-sidebar">
      <?php include '../../frontend/Components/betslip.php'; ?>
    </aside>

  </div>

  <?php include '../../frontend/Components/loginmodal.php';?>
  <?php include '../../frontend/Components/registermodal.php';?>
  <?php include '../../frontend/Components/registermodal2.php'; ?>
  <script src="../../js/Main/auth_ui.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../js/Login/loginmodal.js"></script>
  <script src="../../js/Register/registermodal.js"></script>
  <script src="../../js/Register/registermodal2.js"></script>
  <script src="../../js/Main/layout.js"></script>
  <script src="../../js/Betslip/betslip.js"></script>
  <script src="../../js/MainMenu/main.js"></script>

</body>
</html>