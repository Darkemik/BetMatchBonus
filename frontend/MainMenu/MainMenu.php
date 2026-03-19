<?php
require_once "../../backend/ApiRequest/connect.php";

// Mai meccsek lekérdezése időrendi sorrendben
$today = date('Y-m-d');

$sql = "
SELECT 
    e.api_id,
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
  <link rel="stylesheet" href="../../css/Main/popup.css">
  <link rel="stylesheet" href="../../css/RootColor/root.css">
  <link rel="icon" href="../../img/logo.png" type="image/x-icon">
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
            <?php include '../../frontend/Components/login.php'; ?>
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

          <div class="sports-menu" id="sports-menu">
            <div class="sidebar-loading">
              <i class="fas fa-spinner fa-spin"></i> Sportok betöltése...
            </div>
          </div>
        </form>
      </div>
    </aside>

    <!-- ===== KÖZÉPSŐ TARTALOM ===== -->
    <main class="center-content">

      <!-- Kereső sáv -->
      <div class="main-search-bar">
        <div class="search-wrapper">
          <i class="fas fa-search search-bar-icon"></i>
          <input type="search" id="mainSearch" class="main-search-input" placeholder="Keresés meccsek, csapatok között...">
        </div>
      </div>

      <!-- Sport navigáció (live.php stílusú) -->
      <div class="sports-nav-wrapper">
        <nav class="sports-nav" id="sports-nav">
          <a href="#" class="sport-item active" data-sport="all" data-sport-id="0">
            <div class="sport-icon"><i class="fas fa-list"></i></div>
            <span class="sport-name">Összes</span>
            <span class="sport-count"><?= count($allMatches) ?></span>
          </a>
          <?php
          // Sportok megjelenítése (reset pointer)
          $matchesResult = null; // already consumed
          $sportsNav = $conn->query("
            SELECT s.api_id, s.name, COUNT(e.id) AS mc
            FROM Sports s
            LEFT JOIN Events e ON e.sport_id = s.id AND DATE(e.start_time) = '$today'
            WHERE s.is_active = 1
            GROUP BY s.id
            HAVING mc > 0
            ORDER BY mc DESC
          ");
          if ($sportsNav) {
              while ($sp = $sportsNav->fetch_assoc()) {
                  $apiId = (int)$sp['api_id'];
                  $icon = $sportIcons[$apiId] ?? 'fa-trophy';
                  $name = $sportNames[$apiId] ?? htmlspecialchars($sp['name']);
                  $count = (int)$sp['mc'];
                  echo '<a href="#" class="sport-item" data-sport="' . $apiId . '" data-sport-id="' . $apiId . '">';
                  echo '<div class="sport-icon"><i class="fas ' . $icon . '"></i></div>';
                  echo '<span class="sport-name">' . $name . '</span>';
                  echo '<span class="sport-count">' . $count . '</span>';
                  echo '</a>';
              }
          }
          ?>
        </nav>
      </div>

      <!-- ===== ÉLŐ MOST szekció ===== -->
      <?php if (!empty($liveMatches)): ?>
      <div class="section-block">
        <h2 class="section-title"><span class="live-indicator"></span> Élő most</h2>
        <table class="matches-table">
          <thead>
            <tr>
              <th><i class="fas fa-globe-europe"></i> Ország</th>
              <th><i class="fas fa-trophy"></i> Bajnokság</th>
              <th><i class="fas fa-futbol"></i> Meccs</th>
              <th><i class="fas fa-star"></i> Állás</th>
              <th><i class="fas fa-stopwatch"></i> Élő idő</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($liveMatches as $row):
              $matchParts = explode(' - ', $row['match_name'], 2);
              $home = htmlspecialchars($matchParts[0]);
              $away = isset($matchParts[1]) ? htmlspecialchars($matchParts[1]) : '';
              $score = ($row['home_score'] !== null ? $row['home_score'] : '0') . ' - ' . ($row['away_score'] !== null ? $row['away_score'] : '0');
              $liveTime = !empty($row['live_time']) ? htmlspecialchars($row['live_time']) : '-';
              $apiId = (int)($row['api_id'] ?? 0);
            ?>
            <tr class="match-row clickable" data-match-id="<?= $apiId ?>" data-sport="<?= (int)$row['sport_api_id'] ?>">
              <td><span class="country-name"><?= htmlspecialchars($row['country_name'] ?? '–') ?></span></td>
              <td><span class="league-name"><?= htmlspecialchars($row['competition_name']) ?></span></td>
              <td class="match-cell">
                <?php if ($away !== ''): ?>
                  <span class="team home-team"><?= $home ?></span>
                  <span class="vs">vs</span>
                  <span class="team away-team"><?= $away ?></span>
                <?php else: ?>
                  <span class="team"><?= $home ?></span>
                <?php endif; ?>
              </td>
              <td><span class="match-score"><?= $score ?></span></td>
              <td class="live-time-cell">
                <span class="live-dot"></span>
                <span class="live-time-value"><?= $liveTime ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <!-- ===== HAMAROSAN KEZDŐDIK szekció ===== -->
      <?php if (!empty($upcomingMatches)): ?>
      <div class="section-block">
        <h2 class="section-title"><i class="fas fa-clock"></i> Hamarosan kezdődik</h2>
        <table class="matches-table">
          <thead>
            <tr>
              <th><i class="fas fa-globe-europe"></i> Ország</th>
              <th><i class="fas fa-trophy"></i> Bajnokság</th>
              <th><i class="fas fa-futbol"></i> Meccs</th>
              <th><i class="fas fa-clock"></i> Kezdés</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($upcomingMatches as $row):
              $matchParts = explode(' - ', $row['match_name'], 2);
              $home = htmlspecialchars($matchParts[0]);
              $away = isset($matchParts[1]) ? htmlspecialchars($matchParts[1]) : '';
              $startFormatted = date('H:i', strtotime($row['start_time']));
              $apiId = (int)($row['api_id'] ?? 0);
            ?>
            <tr class="match-row clickable" data-match-id="<?= $apiId ?>" data-sport="<?= (int)$row['sport_api_id'] ?>">
              <td><span class="country-name"><?= htmlspecialchars($row['country_name'] ?? '–') ?></span></td>
              <td><span class="league-name"><?= htmlspecialchars($row['competition_name']) ?></span></td>
              <td class="match-cell">
                <?php if ($away !== ''): ?>
                  <span class="team home-team"><?= $home ?></span>
                  <span class="vs">vs</span>
                  <span class="team away-team"><?= $away ?></span>
                <?php else: ?>
                  <span class="team"><?= $home ?></span>
                <?php endif; ?>
              </td>
              <td><span class="start-time"><?= $startFormatted ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <!-- ===== MAI MECCSEK szekció ===== -->
      <div class="section-block">
        <h2 class="section-title"><i class="fas fa-calendar-day"></i> Mai meccsek</h2>
        <div id="matches-container">
        <?php if (!empty($allMatches)): ?>
          <table class="matches-table" id="main-matches-table">
            <thead>
              <tr>
                <th><i class="fas fa-globe-europe"></i> Ország</th>
                <th><i class="fas fa-trophy"></i> Bajnokság</th>
                <th><i class="fas fa-futbol"></i> Meccs</th>
                <th><i class="fas fa-star"></i> Állás</th>
                <th><i class="fas fa-clock"></i> Kezdés</th>
                <th><i class="fas fa-stopwatch"></i> Státusz</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allMatches as $row):
                $matchParts = explode(' - ', $row['match_name'], 2);
                $home = htmlspecialchars($matchParts[0]);
                $away = isset($matchParts[1]) ? htmlspecialchars($matchParts[1]) : '';
                $startFormatted = date('H:i', strtotime($row['start_time']));
                $score = ($row['home_score'] !== null ? $row['home_score'] : '0') . ' - ' . ($row['away_score'] !== null ? $row['away_score'] : '0');
                $isLive = (bool)$row['is_live'];
                $liveTime = !empty($row['live_time']) ? htmlspecialchars($row['live_time']) : '';
                $apiId = (int)($row['api_id'] ?? 0);
              ?>
              <tr class="match-row clickable <?= $isLive ? 'live-match' : '' ?>" data-match-id="<?= $apiId ?>" data-sport="<?= (int)$row['sport_api_id'] ?>">
                <td><span class="country-name"><?= htmlspecialchars($row['country_name'] ?? '–') ?></span></td>
                <td><span class="league-name"><?= htmlspecialchars($row['competition_name']) ?></span></td>
                <td class="match-cell">
                  <?php if ($away !== ''): ?>
                    <span class="team home-team"><?= $home ?></span>
                    <span class="vs">vs</span>
                    <span class="team away-team"><?= $away ?></span>
                  <?php else: ?>
                    <span class="team"><?= $home ?></span>
                  <?php endif; ?>
                </td>
                <td><span class="match-score"><?= $score ?></span></td>
                <td><span class="start-time"><?= $startFormatted ?></span></td>
                <td>
                  <?php if ($isLive): ?>
                    <span class="live-badge-inline"><span class="live-dot"></span> <?= $liveTime ?: 'Élő' ?></span>
                  <?php else: ?>
                    <span class="status-upcoming">Várható</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
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
  <script src="../../js/Main/popup.js"></script>
  <script src="../../js/Betslip/betslip.js"></script>
  <script src="../../js/MainMenu/main.js"></script>

</body>
</html>