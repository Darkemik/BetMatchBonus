<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Élő meccsek | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Live/live.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

</head>

<body>
    <header class="header">
        <div class="logo-box">
            <a href="../frontend/index.html"><img class="kep" src="../../img/logo.png" alt="logo"></a>
            <div class="logo"><a href="../frontend/index.html" class="mainpage">BetMatchBonus</a></div>
        </div>

        <nav class="nav">
            <a href="../../frontend/MainMenu/MainMenu.php" data-i18n="nav.home">Főoldal</a>
            <a href="../../frontend/Live/live.php" data-i18n="nav.live" class="active">Élő</a>
            <a href="../../frontend/Bonus/bonus.php" data-i18n="nav.bonuses">Bónuszok</a>
            <a href="../../frontend/Help/help.php" data-i18n="nav.help">Segítség</a>
        </nav>
        <div class="right_side">
            <div class="lang-switcher">
                <button class="lang-btn active" id="lang-current">
                    <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                        <rect width="9" height="2" y="0" fill="#c8102e" />
                        <rect width="9" height="2" y="2" fill="#ffffff" />
                        <rect width="9" height="2" y="4" fill="#436f4d" />
                    </svg>
                </button>

                <div class="lang-dropdown">
                    <button class="lang-btn" data-lang="en" title="English">
                        <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                            <rect width="9" height="6" fill="#ffffff" />
                            <rect x="4" width="1" height="6" fill="#c8102e" />
                            <rect y="2.5" width="9" height="1" fill="#c8102e" />
                        </svg>
                    </button>
                </div>
            </div>

            <button data-i18n="loginbtn" class="loginbtn"
                data-bs-toggle="modal" data-bs-target="#loginModal">Bejelentkezés</button>
            <button class="registrationbtn" onclick="location.href='../../frontend/Register/register.php'"
                data-i18n="nav.register">Regisztráció</button>
        </div>
    </header>

    <div class="content-parent">
        <div class="right-container">
            <aside class="right-sidebar">
                <h2>Fogadási szelvény</h2>
                <p>Itt lesz majd a fogadási blokk.</p>
            </aside>
        </div>
        <div class="elo-main">
            <div class="elo-container">
                <h1 class="elo-title" id="elo-title">Élő meccsek</h1>

                <div class="sports-nav-wrapper">
                    <nav class="sports-nav">
                        <a href="#" class="sport-item active">
                            <div class="sport-icon">
                                <i class="fas fa-futbol"></i> <!-- Football ikon -->
                            </div>
                            <span class="sport-name">Labdarúgás</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-basketball-ball"></i> <!-- Basketball ikon -->
                            </div>
                            <span class="sport-name">Kosárlabda</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-bullseye"></i> <!-- Darts ikon -->
                            </div>
                            <span class="sport-name">Darts</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-swimmer"></i> <!-- Vizilabda ikon -->
                            </div>
                            <span class="sport-name">Vízilabda</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-hand-rock"></i> <!-- Kézilabda ikon -->
                            </div>
                            <span class="sport-name">Kézilabda</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-hockey-puck"></i> <!-- Jégkorong ikon -->
                            </div>
                            <span class="sport-name">Jégkorong</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-gamepad"></i> <!-- eSport ikon -->
                            </div>
                            <span class="sport-name">eSport</span>
                        </a>
                        <a href="#" class="sport-item">
                            <div class="sport-icon">
                                <i class="fas fa-table-tennis"></i> <!-- Pingpong ikon -->
                            </div>
                            <span class="sport-name">Pingpong</span>
                        </a>
                    </nav>
                </div>
                <br>

                <div class="tabs-container">
                    <button class="tab-button active" data-tab="all-matches" id="tab-all">Összes meccs</button>
                </div>


                <div id="matches-container">

                </div>

                <div class="tab-content" id="favorites">

                </div>
            </div>
        </div>

    </div>

    </div>


    <?php include '../../frontend/Components/footer.php';?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../js/Live/live.js"></script>



    <script>
        const switcher = document.querySelector('.lang-switcher');
        const currentBtn = document.getElementById('lang-current');

        currentBtn.addEventListener('click', () => {
            switcher.classList.toggle('open');
        });

        document.querySelectorAll('.lang-dropdown .lang-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.dataset.lang;

                // 🔁 Google Translate váltás
                const select = document.querySelector('select.goog-te-combo');
                if (select) {
                    select.value = lang;
                    select.dispatchEvent(new Event('change'));
                }

                switcher.classList.remove('open');
            });
        });

        // kattintás oldalra → bezár
        document.addEventListener('click', e => {
            if (!switcher.contains(e.target)) {
                switcher.classList.remove('open');
            }
        });



    </script>
    <?php include '../../frontend/Components/modal.php';?>
</body>

</html>