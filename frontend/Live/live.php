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

            <button data-i18n="nav.login"
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
                    <button class="tab-button" data-tab="favorites" id="tab-favorites">Kedvenc csapatok</button>
                </div>

                <div id="matches-container">
                    <div class="tab-content active" id="all-matches">
                        <div class="loading">Meccsek betöltése...</div>
                    </div>

                    <div class="tab-content" id="favorites">
                        <div class="loading" id="loading-favorites">Kedvenc csapatok betöltése...</div>
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
        // DEMO meccsek tárolása (JSON adatok)
        let allMatches = [];

        // Demo meccsek betöltése JSON fájlból
        async function fetchLiveMatches() {
            try {
                // JSON fájl betöltése (helyezd el a megfelelő mappába!)
                const response = await fetch('../../data/demo_matches.json');
                const data = await response.json();

                if (data.matches && data.matches.length > 0) {
                    allMatches = data.matches;
                    displayMatches(data.matches);
                } else {
                    document.getElementById('all-matches').innerHTML =
                        '<div class="loading">Jelenleg nincs élő meccs</div>';
                }

            } catch (error) {
                console.error('Hiba:', error);
                // Ha a JSON fájl nem található, használjunk beépített demo adatokat
                loadHardcodedMatches();
            }
        }

        // Beépített demo adatok (ha a JSON fájl nem érhető el) - CSAK ÉLŐ MECCSEK, NINCS REAL MADRID-BARCELONA
        function loadHardcodedMatches() {
            allMatches = [
                {
                    fixture: {
                        id: 1001,
                        date: "2026-02-06T19:45:00+01:00",
                        status: { short: "1H", long: "Első félidő", elapsed: 23 }
                    },
                    league: { name: "Premier League", country: "Anglia" },
                    teams: {
                        home: { id: 2001, name: "Manchester United" },
                        away: { id: 2002, name: "Liverpool" }
                    },
                    goals: { home: 1, away: 2 }
                },
                {
                    fixture: {
                        id: 1003,
                        date: "2026-02-06T19:30:00+01:00",
                        status: { short: "HT", long: "Félidő", elapsed: 45 }
                    },
                    league: { name: "Bundesliga", country: "Németország" },
                    teams: {
                        home: { id: 2005, name: "Bayern München" },
                        away: { id: 2006, name: "Borussia Dortmund" }
                    },
                    goals: { home: 2, away: 2 }
                },
                {
                    fixture: {
                        id: 1004,
                        date: "2026-02-06T20:15:00+01:00",
                        status: { short: "2H", long: "Második félidő", elapsed: 67 }
                    },
                    league: { name: "Serie A", country: "Olaszország" },
                    teams: {
                        home: { id: 2007, name: "Juventus" },
                        away: { id: 2008, name: "Inter Milan" }
                    },
                    goals: { home: 1, away: 0 }
                },
                {
                    fixture: {
                        id: 1006,
                        date: "2026-02-06T19:00:00+01:00",
                        status: { short: "2H", long: "Második félidő", elapsed: 58 }
                    },
                    league: { name: "Eredivisie", country: "Hollandia" },
                    teams: {
                        home: { id: 2011, name: "Ajax Amsterdam" },
                        away: { id: 2012, name: "PSV Eindhoven" }
                    },
                    goals: { home: 2, away: 1 }
                },
                {
                    fixture: {
                        id: 1007,
                        date: "2026-02-06T20:30:00+01:00",
                        status: { short: "1H", long: "Első félidő", elapsed: 15 }
                    },
                    league: { name: "NB I", country: "Magyarország" },
                    teams: {
                        home: { id: 2013, name: "Ferencváros" },
                        away: { id: 2014, name: "Debrecen" }
                    },
                    goals: { home: 0, away: 0 }
                },
                {
                    fixture: {
                        id: 1008,
                        date: "2026-02-06T19:15:00+01:00",
                        status: { short: "2H", long: "Második félidő", elapsed: 72 }
                    },
                    league: { name: "Premier League", country: "Anglia" },
                    teams: {
                        home: { id: 2015, name: "Arsenal" },
                        away: { id: 2016, name: "Chelsea" }
                    },
                    goals: { home: 2, away: 2 }
                },
                {
                    fixture: {
                        id: 1009,
                        date: "2026-02-06T21:00:00+01:00",
                        status: { short: "1H", long: "Első félidő", elapsed: 5 }
                    },
                    league: { name: "La Liga", country: "Spanyolország" },
                    teams: {
                        home: { id: 2017, name: "Atletico Madrid" },
                        away: { id: 2018, name: "Sevilla" }
                    },
                    goals: { home: 0, away: 0 }
                },
                {
                    fixture: {
                        id: 1011,
                        date: "2026-02-06T19:45:00+01:00",
                        status: { short: "2H", long: "Második félidő", elapsed: 63 }
                    },
                    league: { name: "Ligue 1", country: "Franciaország" },
                    teams: {
                        home: { id: 2019, name: "Paris Saint-Germain" },
                        away: { id: 2020, name: "Lyon" }
                    },
                    goals: { home: 1, away: 1 }
                },
                {
                    fixture: {
                        id: 1012,
                        date: "2026-02-06T20:00:00+01:00",
                        status: { short: "1H", long: "Első félidő", elapsed: 31 }
                    },
                    league: { name: "Serie A", country: "Olaszország" },
                    teams: {
                        home: { id: 2021, name: "AC Milan" },
                        away: { id: 2022, name: "Napoli" }
                    },
                    goals: { home: 0, away: 1 }
                }
            ];

            displayMatches(allMatches);
        }

        // LocalStorage kezelés a CSAPAT kedvencekhez
        function getFavoriteTeams() {
            const favorites = localStorage.getItem('favoriteTeams');
            return favorites ? JSON.parse(favorites) : [];
        }

        function saveFavoriteTeams(favorites) {
            localStorage.setItem('favoriteTeams', JSON.stringify(favorites));
        }

        function addFavoriteTeam(teamId, teamName) {
            const favorites = getFavoriteTeams();
            if (!favorites.some(fav => fav.id === teamId)) {
                favorites.push({ id: teamId, name: teamName });
                saveFavoriteTeams(favorites);
                return true;
            }
            return false;
        }

        function removeFavoriteTeam(teamId) {
            const favorites = getFavoriteTeams();
            const newFavorites = favorites.filter(fav => fav.id !== teamId);
            saveFavoriteTeams(newFavorites);
            return newFavorites;
        }

        function isFavoriteTeam(teamId) {
            const favorites = getFavoriteTeams();
            return favorites.some(fav => fav.id === teamId);
        }

        // Tab kezelés
        function initTabs() {
            const tabButtons = document.querySelectorAll('.tab-button');

            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    // Összes tab inaktív
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    // Aktív tab
                    this.classList.add('active');

                    // Tab tartalom elrejtése
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });

                    // Aktív tab tartalom mutatása
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');

                    // Ha a kedvencek fülre kattintottunk
                    if (tabId === 'favorites') {
                        displayFavoriteTeamMatches();
                    }
                });
            });
        }

        // Meccsek megjelenítése táblázatban
        function displayMatches(matches) {
            let tableHTML = `
                <table class="matches-table">
                    <thead>
                        <tr class="matches-head">
                            <th>Bajnokság</th>
                            <th>Hazai csapat</th>
                            <th>Vendég csapat</th>
                            <th>Eredmény</th>
                            <th>Állapot</th>
                            <th>Idő</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            matches.forEach(match => {
                const league = match.league;
                const homeTeam = match.teams.home;
                const awayTeam = match.teams.away;
                const goals = match.goals;
                const status = match.fixture.status;
                const date = new Date(match.fixture.date);

                const isHomeFav = isFavoriteTeam(homeTeam.id);
                const isAwayFav = isFavoriteTeam(awayTeam.id);

                // Státusz szöveg
                let statusText = '';
                let statusClass = '';

                if (status.short === '1H' || status.short === '2H' || status.short === 'HT') {
                    statusText = `${status.elapsed}'`;
                    statusClass = 'live-badge';
                } else {
                    statusText = status.long;
                }

                // Idő formázás
                const timeString = date.toLocaleTimeString('hu-HU', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                tableHTML += `
                    <tr>
                        <td>${league.name} (${league.country})</td>
                        <td>
                            ${homeTeam.name}
                            <button class="favorite-btn ${isHomeFav ? 'active' : ''}" 
                                    data-team-id="${homeTeam.id}"
                                    data-team-name="${homeTeam.name}"
                                    title="${isHomeFav ? 'Eltávolítás a kedvencekből' : 'Hozzáadás a kedvencekhez'}">
                                ${isHomeFav ? '★' : '☆'}
                            </button>
                        </td>
                        <td>
                            ${awayTeam.name}
                            <button class="favorite-btn ${isAwayFav ? 'active' : ''}" 
                                    data-team-id="${awayTeam.id}"
                                    data-team-name="${awayTeam.name}"
                                    title="${isAwayFav ? 'Eltávolítás a kedvencekből' : 'Hozzáadás a kedvencekhez'}">
                                ${isAwayFav ? '★' : '☆'}
                            </button>
                        </td>
                        <td><strong>${goals.home} - ${goals.away}</strong></td>
                        <td class="${statusClass}">${statusText}</td>
                        <td>${timeString}</td>
                    </tr>
                `;
            });


            document.getElementById('all-matches').innerHTML = tableHTML;

            // Kedvenc gombok eseménykezelői
            setupFavoriteButtons();
        }

        // Kedvenc gombok beállítása
        function setupFavoriteButtons() {
            const favoriteButtons = document.querySelectorAll('.favorite-btn');

            favoriteButtons.forEach(button => {
                button.addEventListener('click', function (e) {
                    e.stopPropagation();

                    const teamId = parseInt(this.getAttribute('data-team-id'));
                    const teamName = this.getAttribute('data-team-name');
                    const isActive = this.classList.contains('active');

                    if (isActive) {
                        // Eltávolítás
                        removeFavoriteTeam(teamId);
                        this.classList.remove('active');
                        this.innerHTML = '☆';
                        this.title = 'Hozzáadás a kedvencekhez';
                    } else {
                        // Hozzáadás
                        addFavoriteTeam(teamId, teamName);
                        this.classList.add('active');
                        this.innerHTML = '★';
                        this.title = 'Eltávolítás a kedvencekből';
                    }

                    // Ha a kedvencek fülön vagyunk, frissítsük
                    if (document.getElementById('tab-favorites').classList.contains('active')) {
                        displayFavoriteTeamMatches();
                    }
                });
            });
        }

        // Kedvenc csapatok meccseinek megjelenítése
        function displayFavoriteTeamMatches() {
            const favoriteTeams = getFavoriteTeams();

            if (favoriteTeams.length === 0) {
                document.getElementById('favorites').innerHTML =
                    '<div class="no-matches">Még nincsenek kedvenc csapatok. Kattints a ★ gombra egy csapat neve mellett!</div>';
                return;
            }

            // Szűrjük a meccseket - csak azok, ahol van kedvenc csapat
            const favoriteMatches = allMatches.filter(match => {
                const homeTeamId = match.teams.home.id;
                const awayTeamId = match.teams.away.id;
                return favoriteTeams.some(fav => fav.id === homeTeamId || fav.id === awayTeamId);
            });

            if (favoriteMatches.length === 0) {
                document.getElementById('favorites').innerHTML =
                    '<div class="no-matches">Jelenleg nincs élő meccs a kedvenc csapataidnak.</div>';
                return;
            }

            let tableHTML = `
                <table class="matches-table">
                    <thead>
                        <tr class="matches-head">
                            <th>Bajnokság</th>
                            <th>Hazai csapat</th>
                            <th>Vendég csapat</th>
                            <th>Eredmény</th>
                            <th>Állapot</th>
                            <th>Idő</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            favoriteMatches.forEach(match => {
                const league = match.league;
                const homeTeam = match.teams.home;
                const awayTeam = match.teams.away;
                const goals = match.goals;
                const status = match.fixture.status;
                const date = new Date(match.fixture.date);

                const isHomeFav = isFavoriteTeam(homeTeam.id);
                const isAwayFav = isFavoriteTeam(awayTeam.id);

                // Státusz szöveg
                let statusText = '';
                let statusClass = '';

                if (status.short === '1H' || status.short === '2H' || status.short === 'HT') {
                    statusText = `${status.elapsed}'`;
                    statusClass = 'live-badge';
                } else {
                    statusText = status.long;
                }

                // Idő formázás
                const timeString = date.toLocaleTimeString('hu-HU', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                tableHTML += `
                    <tr>
                        <td>${league.name} (${league.country})</td>
                        <td ${isHomeFav ? 'style="font-weight: bold; color: #4CAF50;"' : ''}>
                            ${homeTeam.name}
                            <button class="favorite-btn active" 
                                    data-team-id="${homeTeam.id}"
                                    data-team-name="${homeTeam.name}"
                                    title="Eltávolítás a kedvencekből">
                                ★
                            </button>
                        </td>
                        <td ${isAwayFav ? 'style="font-weight: bold; color: #4CAF50;"' : ''}>
                            ${awayTeam.name}
                            <button class="favorite-btn ${isAwayFav ? 'active' : ''}" 
                                    data-team-id="${awayTeam.id}"
                                    data-team-name="${awayTeam.name}"
                                    title="${isAwayFav ? 'Eltávolítás a kedvencekből' : 'Hozzáadás a kedvencekhez'}">
                                ${isAwayFav ? '★' : '☆'}
                            </button>
                        </td>
                        <td><strong>${goals.home} - ${goals.away}</strong></td>
                        <td class="${statusClass}">${statusText}</td>
                        <td>${timeString}</td>
                    </tr>
                `;
            });


            document.getElementById('favorites').innerHTML = tableHTML;

            // Kedvenc gombok beállítása
            setupFavoriteButtons();
        }

        // Oldal betöltésekor
        document.addEventListener('DOMContentLoaded', function () {
            // Tabok inicializálása
            initTabs();

            // Meccsek betöltése
            fetchLiveMatches();

            // Nyelvváltás után a tabokat is frissíteni kell
            const huBtn = document.getElementById('lang-hu');
            const enBtn = document.getElementById('lang-en');


        });

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