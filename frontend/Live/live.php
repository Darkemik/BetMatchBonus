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
            <a href="../../frontend/MainMenu/MainMenu.php" data-i18n="nav.home">Főoldal</a>
            <a href="../../frontend/Live/live.php" data-i18n="nav.live">Élő</a>
            <a href="../../frontend/Bonus/bonus.php" data-i18n="nav.bonuses">Bónuszok</a>
            <a href="../../frontend/Help/help.php" data-i18n="nav.help">Segítség</a>
        </nav>
        <div class="right_side">
            <div class="lang-switcher">
                <button class="lang-btn active" id="lang-hu" title="Magyar">
                    <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                        <rect width="9" height="2" y="0" fill="#c8102e" />
                        <rect width="9" height="2" y="2" fill="#ffffff" />
                        <rect width="9" height="2" y="4" fill="#436f4d" />
                    </svg>
                </button>
                <button class="lang-btn" id="lang-en" title="English">
                    <svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
                        <rect width="9" height="6" fill="#ffffff" />
                        <rect x="4" width="1" height="6" fill="#c8102e" />
                        <rect y="2.5" width="9" height="1" fill="#c8102e" />
                    </svg>
                </button>
            </div>
            <button data-i18n="nav.login"
                onclick="location.href='../../frontend/Login/login.php' ">Bejelentkezés</button>
            <button class="registrationbtn" onclick="location.href='../../frontend/Register/register.php'"
                data-i18n="nav.register">Regisztráció</button>
        </div>
    </header>

    <div class="elo-main">
        <div class="elo-container">
            <h1 class="elo-title" id="elo-title">Élő meccsek</h1>

            <div class="tabs-container">
                <button class="tab-button active" data-tab="all-matches" id="tab-all">Összes meccs</button>
                <button class="tab-button" data-tab="favorites" id="tab-favorites">Kedvencek</button>
            </div>

            <div id="matches-container">
                <div class="tab-content active" id="all-matches">
                    <div class="loading">Meccsek betöltése...</div>
                </div>

                <div class="tab-content" id="favorites">
                    <div class="loading" id="loading-favorites">Kedvencek betöltése...</div>
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
        // A TE API KULCSOD
        const API_KEY = 'a12934fb7f8e9e4cfd3073de92b0994d';

        // Élő meccsek lekérése
        async function fetchLiveMatches() {
            try {
                const response = await fetch('https://v3.football.api-sports.io/fixtures?live=all&timezone=Europe/Budapest', {
                    headers: {
                        'x-rapidapi-host': 'v3.football.api-sports.io',
                        'x-rapidapi-key': API_KEY
                    }
                });

                const data = await response.json();

                if (data.response && data.response.length > 0) {
                    displayMatches(data.response);
                } else {
                    document.getElementById('all-matches').innerHTML =
                        '<div class="loading">Jelenleg nincs élő meccs</div>';
                }

            } catch (error) {
                console.error('Hiba:', error);
                document.getElementById('all-matches').innerHTML =
                    '<div class="loading" style="color: #e74c3c;">Hiba történt az adatok betöltésekor</div>';
            }
        }

        // LocalStorage kezelés a kedvencekhez
        function getFavorites() {
            const favorites = localStorage.getItem('favoriteMatches');
            return favorites ? JSON.parse(favorites) : [];
        }

        function saveFavorites(favorites) {
            localStorage.setItem('favoriteMatches', JSON.stringify(favorites));
        }

        function addFavorite(matchId, matchData) {
            const favorites = getFavorites();
            if (!favorites.some(fav => fav.fixture.id === matchId)) {
                favorites.push(matchData);
                saveFavorites(favorites);
                return true;
            }
            return false;
        }

        function removeFavorite(matchId) {
            const favorites = getFavorites();
            const newFavorites = favorites.filter(fav => fav.fixture.id !== matchId);
            saveFavorites(newFavorites);
            return newFavorites;
        }

        function isFavorite(matchId) {
            const favorites = getFavorites();
            return favorites.some(fav => fav.fixture.id === matchId);
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
                        displayFavoriteMatches();
                    }
                });
            });
        }

        // Meccsek megjelenítése táblázatban
        function displayMatches(matches) {
            let tableHTML = `
                <table class="matches-table">
                    <thead>
                        <tr>
                            <th>Bajnokság</th>
                            <th>Mérkőzés</th>
                            <th>Eredmény</th>
                            <th>Állapot</th>
                            <th>Idő</th>
                            <th>Kedvenc</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            matches.forEach(match => {
                const matchId = match.fixture.id;
                const league = match.league;
                const teams = match.teams;
                const goals = match.goals;
                const status = match.fixture.status;
                const date = new Date(match.fixture.date);
                const isFav = isFavorite(matchId);

                // Státusz szöveg
                let statusText = '';
                let statusClass = '';

                if (status.short === '1H' || status.short === '2H' || status.short === 'HT') {
                    statusText = `${status.elapsed}'`;
                    statusClass = 'live-badge';
                } else if (status.short === 'FT') {
                    statusText = 'Vége';
                } else {
                    statusText = status.long;
                }

                // Idő formázás
                const timeString = date.toLocaleTimeString('hu-HU', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Kedvenc gomb címke
                const favoriteTitle = isFav ? 'Eltávolítás a kedvencekből' : 'Hozzáadás a kedvencekhez';

                tableHTML += `
                    <tr>
                        <td>${league.name} (${league.country})</td>
                        <td>${teams.home.name} - ${teams.away.name}</td>
                        <td><strong>${goals.home} - ${goals.away}</strong></td>
                        <td class="${statusClass}">${statusText}</td>
                        <td>${timeString}</td>
                        <td>
                            <button class="favorite-btn ${isFav ? 'active' : ''}" 
                                    data-match-id="${matchId}"
                                    title="${favoriteTitle}">
                                ${isFav ? '★' : '☆'}
                            </button>
                        </td>
                    </tr>
                `;
            });

            tableHTML += `
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="fetchLiveMatches()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        🔄 Frissítés
                    </button>
                </div>
            `;

            document.getElementById('all-matches').innerHTML = tableHTML;

            // Kedvenc gombok eseménykezelői
            setupFavoriteButtons();
        }

        // Kedvenc gombok beállítása
        function setupFavoriteButtons() {
            const favoriteButtons = document.querySelectorAll('.favorite-btn');

            favoriteButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const matchId = parseInt(this.getAttribute('data-match-id'));
                    const isActive = this.classList.contains('active');

                    if (isActive) {
                        // Eltávolítás
                        removeFavorite(matchId);
                        this.classList.remove('active');
                        this.innerHTML = '☆';
                        this.title = 'Hozzáadás a kedvencekhez';
                    } else {
                        // Hozzáadás
                        // Megkeressük a meccset a DOM-ból
                        const matchRow = this.closest('tr');
                        const matchData = {
                            fixture: {
                                id: matchId,
                                date: new Date().toISOString(), // Itt pontosítani kellene
                                status: {
                                    short: matchRow.querySelector('.live-badge') ? 'LIVE' : 'FT',
                                    long: matchRow.querySelector('td:nth-child(4)').textContent
                                }
                            },
                            league: {
                                name: matchRow.querySelector('td:nth-child(1)').textContent.split(' (')[0],
                                country: matchRow.querySelector('td:nth-child(1)').textContent.match(/\(([^)]+)\)/)?.[1] || ''
                            },
                            teams: {
                                home: {
                                    name: matchRow.querySelector('td:nth-child(2)').textContent.split(' - ')[0]
                                },
                                away: {
                                    name: matchRow.querySelector('td:nth-child(2)').textContent.split(' - ')[1]
                                }
                            },
                            goals: {
                                home: parseInt(matchRow.querySelector('td:nth-child(3) strong').textContent.split(' - ')[0]),
                                away: parseInt(matchRow.querySelector('td:nth-child(3) strong').textContent.split(' - ')[1])
                            }
                        };

                        addFavorite(matchId, matchData);
                        this.classList.add('active');
                        this.innerHTML = '★';
                        this.title = 'Eltávolítás a kedvencekből';
                    }

                    // Ha a kedvencek fülön vagyunk, frissítsük
                    if (document.getElementById('tab-favorites').classList.contains('active')) {
                        displayFavoriteMatches();
                    }
                });
            });
        }

        // Kedvenc meccsek megjelenítése
        function displayFavoriteMatches() {
            const favorites = getFavorites();

            if (favorites.length === 0) {
                document.getElementById('favorites').innerHTML =
                    '<div class="no-matches">Még nincsenek kedvenc meccsek</div>';
                return;
            }

            let tableHTML = `
                <table class="matches-table">
                    <thead>
                        <tr>
                            <th>Bajnokság</th>
                            <th>Mérkőzés</th>
                            <th>Eredmény</th>
                            <th>Állapot</th>
                            <th>Idő</th>
                            <th>Kedvenc</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            favorites.forEach(match => {
                const matchId = match.fixture.id;
                const league = match.league;
                const teams = match.teams;
                const goals = match.goals;
                const status = match.fixture.status;
                const date = new Date(match.fixture.date);

                // Idő formázás
                const timeString = date.toLocaleTimeString('hu-HU', {
                    hour: '2-digit',
                    minute: '2-digit'
                });

                // Eredmény
                const homeScore = goals.home !== null ? goals.home : '0';
                const awayScore = goals.away !== null ? goals.away : '0';

                tableHTML += `
                    <tr>
                        <td>${league.name} ${league.country ? '(' + league.country + ')' : ''}</td>
                        <td>${teams.home.name} - ${teams.away.name}</td>
                        <td><strong>${homeScore} - ${awayScore}</strong></td>
                        <td>${status.long || 'Ismeretlen'}</td>
                        <td>${timeString}</td>
                        <td>
                            <button class="favorite-btn active" 
                                    data-match-id="${matchId}"
                                    title="Eltávolítás a kedvencekből">
                                ★
                            </button>
                        </td>
                    </tr>
                `;
            });

            tableHTML += `
                    </tbody>
                </table>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="displayFavoriteMatches()" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        🔄 Frissítés
                    </button>
                </div>
            `;

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

            // Automatikus frissítés (60 másodpercenként)
            setInterval(fetchLiveMatches, 60000);

            // Nyelvváltás után a tabokat is frissíteni kell
            const huBtn = document.getElementById('lang-hu');
            const enBtn = document.getElementById('lang-en');

            if (huBtn) huBtn.addEventListener('click', function () {
                setTimeout(() => {
                    // Ha a kedvencek fülön vagyunk, frissítjük
                    if (document.getElementById('tab-favorites').classList.contains('active')) {
                        displayFavoriteMatches();
                    }
                }, 100);
            });

            if (enBtn) enBtn.addEventListener('click', function () {
                setTimeout(() => {
                    // Ha a kedvencek fülön vagyunk, frissítjük
                    if (document.getElementById('tab-favorites').classList.contains('active')) {
                        displayFavoriteMatches();
                    }
                }, 100);
            });
        });
    </script>
</body>

</html>