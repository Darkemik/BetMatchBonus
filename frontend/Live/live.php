<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BetMatchBonus - Élő meccsek</title>
    <link rel="stylesheet" href="../../css/Main/main.css">
    <link rel="stylesheet" href="../../css/Live/live.css">
    <link rel="stylesheet" href="../../css/Footer/footer.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>

<body>
    <header class="header">
        <div class="logo-box">
            <a href="../frontend/index.html"><img class="kep" src="../../img/logo.png" alt="logo"></a>
            <div class="logo"><a href="../frontend/index.html" class="mainpage">BetMatchBonus</a></div>
        </div>

        <nav class="nav">
            <a href="../../frontend/MainMenu/MainMenu.php" data-i18n="nav.home" id="fooldalszoveg">Főoldal</a>
            <a href="../../frontend/Live/live.php" data-i18n="nav.live" id="eloszoveg">Élő</a>
            <a href="../../frontend/Bonus/bonus.php" data-i18n="nav.bonuses" id="bonuszszoveg">Bónuszok</a>
            <a href="../../frontend/Help/help.php" data-i18n="nav.help" id="segitsegszoveg">Segítség</a>
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
                onclick="location.href='../../frontend/Login/login.php' ">Bejelentkezés</button>
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

                <div class="tabs-container">
                    <button class="tab-button active" data-tab="all-matches" id="tab-all">Összes meccs</button>
                </div>

                <div class="sports-nav-wrapper">
        <nav class="sports-nav">
            <a href="#" class="sport-item active">
                <div class="sport-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 3.1c2.7.4 4.9 2.2 5.8 4.6l-2.1.4c-.6-1.6-1.9-2.8-3.7-3.2V3.1zM11 3.1v3.8C9.2 7.3 7.9 8.5 7.3 10l-2.1-.4c.9-2.4 3.1-4.2 5.8-4.5zM4.3 13c-.1-.3-.1-.7-.1-1s0-.7.1-1l2.2.1c0 .3-.1.6-.1.9s0 .6.1.9l-2.2.1zm2.4 5.4c-1-1.4-1.6-3.1-1.7-4.9l2.2-.1c.1 1.3.5 2.5 1.2 3.5l-1.7 1.5zM11 20.9c-2.5-.3-4.6-1.8-5.7-4l1.9-1c.8 1.5 2.3 2.6 4.1 2.9l-.3 2.1zm1 0v-2.1c1.8-.3 3.3-1.4 4.1-2.9l1.9 1c-1.1 2.2-3.2 3.7-5.7 4zm5.9-5.5l-1.7-1.5c.7-1 1.1-2.2 1.2-3.5l2.2.1c-.1 1.8-.7 3.5-1.7 4.9zm1.8-6.4l-2.2-.1c0-.3.1-.6.1-.9s0-.6-.1-.9l2.2-.1c.1.3.1.7.1 1s0 .7-.1 1z"/>
                    </svg>
                </div>
                <span class="sport-name">Football</span>
            </a>
        </nav>
    </div>

                <div id="matches-container">
                    
                    </div>

                    <div class="tab-content" id="favorites">
                        
                    </div>
                </div>
            </div>

        </div>

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
</body>

</html>