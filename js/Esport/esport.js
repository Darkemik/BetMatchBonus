document.addEventListener("DOMContentLoaded", () => {
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));
    const td = (text) => (typeof window.i18nDynamic === 'function' ? window.i18nDynamic(text) : text);
    const liveContainer = document.getElementById("matches-container");
    const todayContainer = document.getElementById("today-matches-container");
    const esportSportsNav = document.getElementById("esportSportsNav");
    let refreshTimer = null;
    let currentMatchId = null;
    let activeTab = 'today';

    // All eSport sport IDs
    const ESPORT_SPORT_IDS = [145, 146, 147, 148];
    let currentEsportId = null; // null = "Összes"
    let currentGameTag = null;  // null = összes játék (csak sport_id=145 esetén)
    let gameTagsData = {};      // { lol: { name, icon, liveCount, todayCount }, ... }

    // eSport sport config (names + icons)
    const ESPORT_CONFIG = {
        145: { name: 'E-sportok',       icon: 'fa-gamepad' },
        146: { name: 'e-Labdarúgás',    icon: 'fa-futbol' },
        147: { name: 'e-Kosárlabda',    icon: 'fa-basketball-ball' },
        148: { name: 'e-Jégkorong',     icon: 'fa-hockey-puck' }
    };

    // Dynamic sport details from backend
    let esportDetails = {};

    // ===== GLOBÁLIS EVENT DELEGATION az odds gombokhoz (live.js-hez hasonló) =====
    document.addEventListener('click', function(e) {
        const selectionBtn = e.target.closest('.selection-btn');
        if (!selectionBtn) return;

        if (selectionBtn.classList.contains('disabled') || selectionBtn.classList.contains('market-locked')) {
            console.log('[ESPORT] Disabled/locked selection-btn, ignored');
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const homeTeam = selectionBtn.getAttribute('data-home');
        const awayTeam = selectionBtn.getAttribute('data-away');
        const pick = selectionBtn.getAttribute('data-pick');
        const odds = parseFloat(selectionBtn.getAttribute('data-odd'));
        const market = selectionBtn.getAttribute('data-market');
        const matchId = parseInt(selectionBtn.getAttribute('data-match-id')) || 0;

        console.log('[ESPORT] selection-btn delegation kattintás:', {
            homeTeam, awayTeam, pick, odds, market, matchId
        });

        if (!homeTeam || !awayTeam || !pick || !market) {
            console.error('[ESPORT] Hiányzó adatok a selection-btn-ben');
            return;
        }

        if (typeof window.toggleOdds === 'function') {
            window.toggleOdds(homeTeam, awayTeam, pick, odds, market, matchId);
            
            // Azonnal frissítjük az összes gombot
            setTimeout(() => {
                if (typeof window.refreshAllOddsButtons === 'function') {
                    window.refreshAllOddsButtons();
                    console.log('[ESPORT] Odds gombok azonnal frissítve (delegation után)');
                }
            }, 0);
        } else {
            console.error('[ESPORT] toggleOdds függvény nem érhető el');
        }
    });

    // ===== GLOBÁLIS EVENT DELEGATION a btn-add-bet gombokhoz =====
    // Ez működik az AJAX után új gombok esetén is
    document.addEventListener('click', function(e) {
        const addBetBtn = e.target.closest('.btn-add-bet');
        if (!addBetBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const matchId = parseInt(addBetBtn.getAttribute('data-match-id'));
        console.log('[ESPORT] btn-add-bet kattintás (delegation), matchId:', matchId);

        if (isNaN(matchId)) {
            console.error('[ESPORT] Érvénytelen matchId a btn-add-bet-ben');
            return;
        }

        loadMatchDetails(matchId);
    });

    // ===== MARKET HTML ÉPÍTÉS =====
    function buildMarketsHtml(markets, homeTeam, awayTeam) {
        var validMarkets = markets.filter(function(m) { return m.selections && m.selections.length > 0; });
        if (validMarkets.length === 0) {
            return '<div class="no-markets">' + t('esport.noMarkets', 'Jelenleg nincsenek elérhető fogadási piacok ehhez a meccshez.') + '</div>';
        }
        var html = '';
        validMarkets.forEach(function(market) {
            var specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
            var marketFullName = td(market.name || '') + specialVal;
            html += '<div class="market-card">';
            html += '<div class="market-header"><span class="market-name">' + escapeHtml(marketFullName) + '</span></div>';
            html += '<div class="market-selections">';
            market.selections.forEach(function(sel) {
                const oddsValue = parseFloat(sel.odds) || 0;
                const state = window.BetslipLogic ? window.BetslipLogic.getButtonState(homeTeam, awayTeam, sel.name, marketFullName) : null;
                const stateClass = state ? ' ' + state : '';
                const isDisabled = state === 'disabled' ? ' disabled' : '';
                
                html += '<button class="selection-btn' + stateClass + '"' + isDisabled + ' ' +
                    'data-home="' + escapeHtml(homeTeam) + '" ' +
                    'data-away="' + escapeHtml(awayTeam) + '" ' +
                    'data-pick="' + escapeHtml(sel.name) + '" ' +
                    'data-odd="' + oddsValue + '" ' +
                    'data-market="' + escapeHtml(marketFullName) + '">' +
                    '<span class="selection-name">' + escapeHtml(td(sel.name)) + '</span>' +
                    '<span class="selection-odd">' + oddsValue.toFixed(2) + '</span>' +
                '</button>';
            });
            html += '</div></div>';
        });
        return html;
    }

    // ===== ESPORT SPORT NAV FELÉPÍTÉSE =====
    function buildEsportSportsNav(liveCounts, todayCounts) {
        if (!esportSportsNav) return;
        esportSportsNav.innerHTML = '';

        // "Összes" gomb
        const allCount = activeTab === 'live'
            ? ESPORT_SPORT_IDS.reduce((sum, id) => sum + (liveCounts[id] || 0), 0)
            : ESPORT_SPORT_IDS.reduce((sum, id) => sum + (todayCounts[id] || 0), 0);

        const allBtn = document.createElement('a');
        allBtn.href = '#';
        allBtn.className = 'esport-sport-item' + (currentEsportId === null ? ' active' : '');
        allBtn.innerHTML = `
            <div class="esport-sport-icon"><i class="fas fa-gamepad"></i></div>
            <span class="esport-sport-name">${t('esport.all', 'Összes')}</span>
            <span class="esport-sport-count${allCount > 0 ? ' has-live' : ''}">${allCount}</span>
        `;
        allBtn.addEventListener('click', function(e) {
            e.preventDefault();
            currentEsportId = null;
            currentGameTag = null;
            currentMatchId = null;
            buildEsportSportsNav(liveCounts, todayCounts);
            updateGameNav();
            refreshActive();
        });
        esportSportsNav.appendChild(allBtn);

        // Egyedi sport gombok
        ESPORT_SPORT_IDS.forEach(sportId => {
            const count = activeTab === 'live'
                ? (liveCounts[sportId] || 0)
                : (todayCounts[sportId] || 0);
            const config = esportDetails[sportId] || ESPORT_CONFIG[sportId] || { name: 'Sport #' + sportId, icon: 'fa-gamepad' };

            const btn = document.createElement('a');
            btn.href = '#';
            btn.className = 'esport-sport-item' + (currentEsportId === sportId ? ' active' : '');
            btn.innerHTML = `
                <div class="esport-sport-icon"><i class="fas ${config.icon}"></i></div>
                <span class="esport-sport-name">${escapeHtml(td(config.name))}</span>
                <span class="esport-sport-count${count > 0 ? ' has-live' : ''}">${count}</span>
            `;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                currentEsportId = sportId;
                currentGameTag = null;
                currentMatchId = null;
                buildEsportSportsNav(liveCounts, todayCounts);
                updateGameNav();
                refreshActive();
            });
            esportSportsNav.appendChild(btn);
        });

        updateGameNav();
    }

    // ===== JÁTÉK AL-SZŰRŐ NAV =====
    const esportGamesNav = document.getElementById('esportGamesNav');

    function fetchGameTags() {
        return fetch('../../backend/ApiRequest/get_esport_game_tags.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                gameTagsData = data.tags || {};
            })
            .catch(function(err) { console.error('[ESPORT] Game tags hiba:', err); });
    }

    function updateGameNav() {
        if (!esportGamesNav) return;
        // Csak sport_id=145 (E-sportok) esetén mutatjuk
        if (currentEsportId !== 145) {
            esportGamesNav.style.display = 'none';
            return;
        }
        esportGamesNav.style.display = 'flex';
        esportGamesNav.innerHTML = '';

        var tags = gameTagsData;
        var allTags = Object.keys(tags);
        if (allTags.length === 0) return;

        // "Összes" gomb
        var totalCount = allTags.reduce(function(sum, tag) {
            return sum + (activeTab === 'live' ? (tags[tag].liveCount || 0) : (tags[tag].todayCount || 0));
        }, 0);

        var allGameBtn = document.createElement('a');
        allGameBtn.href = '#';
        allGameBtn.className = 'esport-game-item' + (currentGameTag === null ? ' active' : '');
        allGameBtn.innerHTML =
            '<div class="esport-game-icon"><i class="fas fa-layer-group"></i></div>' +
            '<span>' + t('esport.allGames', 'Összes') + '</span>' +
            '<span class="esport-game-count' + (totalCount > 0 ? ' has-live' : '') + '">' + totalCount + '</span>';
        allGameBtn.addEventListener('click', function(e) {
            e.preventDefault();
            currentGameTag = null;
            currentMatchId = null;
            updateGameNav();
            refreshActive();
        });
        esportGamesNav.appendChild(allGameBtn);

        // Egyedi játék gombok
        allTags.forEach(function(tag) {
            var info = tags[tag];
            var count = activeTab === 'live' ? (info.liveCount || 0) : (info.todayCount || 0);

            var btn = document.createElement('a');
            btn.href = '#';
            btn.className = 'esport-game-item' + (currentGameTag === tag ? ' active' : '');
            btn.innerHTML =
                '<div class="esport-game-icon"><i class="fas ' + escapeHtml(info.icon || 'fa-gamepad') + '"></i></div>' +
                '<span>' + escapeHtml(td(info.name)) + '</span>' +
                '<span class="esport-game-count' + (count > 0 ? ' has-live' : '') + '">' + count + '</span>';
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                currentGameTag = tag;
                currentMatchId = null;
                updateGameNav();
                refreshActive();
            });
            esportGamesNav.appendChild(btn);
        });
    }

    // ===== TAB VÁLTÁS =====
    var tabButtons = document.querySelectorAll('.tab-button');
    var tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            tabButtons.forEach(function(b) { b.classList.remove('active'); });
            tabContents.forEach(function(c) { c.classList.remove('active'); });
            btn.classList.add('active');
            var target = btn.getAttribute('data-tab');
            activeTab = target;
            var targetContent = document.getElementById('tab-' + target);
            if (targetContent) targetContent.classList.add('active');

            currentMatchId = null;
            refreshActive();
        });
    });

    // ===== BADGE FRISSÍTÉS =====
    function updateLiveCount(count) {
        var badge = document.getElementById('esport-live-badge');
        if (badge) {
            badge.textContent = count;
            if (count > 0) {
                badge.classList.add('has-live');
            } else {
                badge.classList.remove('has-live');
            }
        }
    }

    function updateTodayCount(count) {
        var badge = document.getElementById('esport-today-badge');
        if (badge) {
            badge.textContent = count;
            if (count > 0) {
                badge.classList.add('has-live');
            } else {
                badge.classList.remove('has-live');
            }
        }
    }

    // ===== AKTÍV KONTÉNER =====
    function getActiveContainer() {
        if (activeTab === 'live') return liveContainer;
        return todayContainer;
    }

    // ===== GET SPORT IDS TO FETCH =====
    function getSportIdsToFetch() {
        if (currentEsportId !== null) {
            return [currentEsportId];
        }
        return ESPORT_SPORT_IDS;
    }

    // ===== GAME TAG QUERY PARAM =====
    function getGameTagParam() {
        if (currentEsportId === 145 && currentGameTag !== null) {
            return '&game_tag=' + encodeURIComponent(currentGameTag);
        }
        return '';
    }

    // ===== ÉLŐ MECCSEK FRISSÍTÉS =====
    function refreshLiveMatches() {
        if (activeTab !== 'live') return;
        if (currentMatchId) {
            refreshMatchDetails(currentMatchId);
            return;
        }

        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(function(res) { return res.json(); })
            .then(function(apiResult) {
                var liveCounts = {};
                var todayCounts = {};
                if (apiResult && apiResult.sports) {
                    ESPORT_SPORT_IDS.forEach(function(id) {
                        liveCounts[id] = apiResult.sports[id] || apiResult.sports[String(id)] || 0;
                    });
                    var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) { return sum + (liveCounts[id] || 0); }, 0);
                    // Badge: ha egy sport ki van választva, annak a számát mutassuk
                    var displayLiveCount = currentEsportId !== null ? (liveCounts[currentEsportId] || 0) : totalLive;
                    updateLiveCount(displayLiveCount);
                }

                // Save sport details from backend
                if (apiResult && apiResult.sportDetails) {
                    ESPORT_SPORT_IDS.forEach(function(id) {
                        if (apiResult.sportDetails[id] || apiResult.sportDetails[String(id)]) {
                            esportDetails[id] = apiResult.sportDetails[id] || apiResult.sportDetails[String(id)];
                        }
                    });
                }

                buildEsportSportsNav(liveCounts, todayCounts);

                // Fetch live tables for the selected sport(s)
                var sportIds = getSportIdsToFetch();
                var gameParam = getGameTagParam();
                var fetches = sportIds.map(function(sid) {
                    return fetch("../../backend/ApiRequest/live_table.php?sport_id=" + sid + gameParam)
                        .then(function(res) { return res.text(); });
                });
                return Promise.all(fetches);
            })
            .then(function(htmlParts) {
                if (!htmlParts) return;
                // Kiszűrjük a "nincs meccs" placeholder div-eket, csak a tényleges tartalmat tartjuk meg
                var filteredParts = htmlParts.map(function(html) {
                    var temp = document.createElement('div');
                    temp.innerHTML = html;
                    // Ha csak no-matches div van benne (nincs league-group), üresre cseréljük
                    if (temp.querySelectorAll('.league-group').length === 0) return '';
                    // Ha van league-group, de no-matches div is, csak a no-matches-t töröljük
                    temp.querySelectorAll('.no-matches').forEach(function(el) { el.remove(); });
                    return temp.innerHTML;
                });
                var combinedHtml = filteredParts.join('');
                if (combinedHtml.trim() === '') {
                    liveContainer.innerHTML = '<div class="no-matches"><i class="fas fa-gamepad" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>' + t('esport.noLiveEsport', 'Jelenleg nincs élő eSport meccs.') + '</div>';
                } else {
                    liveContainer.innerHTML = combinedHtml;
                }
                attachMatchClickHandlers(liveContainer);
                if (typeof window.applyI18n === 'function') window.applyI18n(liveContainer);
                if (typeof window.refreshAllOddsButtons === 'function') {
                    window.refreshAllOddsButtons();
                }
            })
            .catch(function(err) {
                console.error("Hiba az eSport élő meccsek frissítésekor:", err);
            });
    }

    // ===== MAI MECCSEK FRISSÍTÉS =====
    function refreshTodayMatches() {
        if (activeTab !== 'today') return;
        if (currentMatchId) {
            refreshMatchDetails(currentMatchId);
            return;
        }

        var sportIds = getSportIdsToFetch();
        var gameParam = getGameTagParam();

        var liveFetch = fetch("../../backend/ApiRequest/get_matches_live.php").then(function(res) { return res.json(); });
        // Mindig lekérjük az összes sport adatait a számláló navhoz (szűrés nélkül)
        var allTodayFetches = ESPORT_SPORT_IDS.map(function(sid) {
            return fetch("../../backend/ApiRequest/mainmenu_matches.php?sport_id=" + sid)
                .then(function(res) { return res.text(); });
        });
        // Ha game_tag szűrés aktív, külön lekérjük a szűrt adatot is
        var filteredFetch = (currentEsportId === 145 && currentGameTag !== null)
            ? fetch("../../backend/ApiRequest/mainmenu_matches.php?sport_id=145" + gameParam).then(function(res) { return res.text(); })
            : Promise.resolve(null);

        Promise.all([liveFetch, Promise.all(allTodayFetches), filteredFetch])
        .then(function(results) {
            var liveResult = results[0];
            var allHtmlParts = results[1];
            var filteredHtml = results[2]; // null ha nincs game_tag szűrés

            var liveCounts = {};
            var todayCounts = {};
            if (liveResult && liveResult.sports) {
                ESPORT_SPORT_IDS.forEach(function(id) {
                    liveCounts[id] = liveResult.sports[id] || liveResult.sports[String(id)] || 0;
                });
                var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) { return sum + (liveCounts[id] || 0); }, 0);
                // Badge: ha egy sport ki van választva, annak a számát mutassuk
                var displayLiveCount = currentEsportId !== null ? (liveCounts[currentEsportId] || 0) : totalLive;
                updateLiveCount(displayLiveCount);
            }

            // Save sport details from backend
            if (liveResult && liveResult.sportDetails) {
                ESPORT_SPORT_IDS.forEach(function(id) {
                    if (liveResult.sportDetails[id] || liveResult.sportDetails[String(id)]) {
                        esportDetails[id] = liveResult.sportDetails[id] || liveResult.sportDetails[String(id)];
                    }
                });
            }

            // Today counts for nav — mindig minden sporthoz kiszámoljuk
            ESPORT_SPORT_IDS.forEach(function(sid, idx) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = allHtmlParts[idx];
                todayCounts[sid] = tempDiv.querySelectorAll('.match-row').length;
            });

            // Csak a kiválasztott sportok HTML-jét rendereljük
            var displayHtmlParts = [];
            if (filteredHtml !== null) {
                // Game tag szűrt adat használata
                displayHtmlParts.push(filteredHtml);
            } else {
                ESPORT_SPORT_IDS.forEach(function(sid, idx) {
                    if (currentEsportId === null || currentEsportId === sid) {
                        displayHtmlParts.push(allHtmlParts[idx]);
                    }
                });
            }
            var combinedHtml = displayHtmlParts.join('');
            todayContainer.innerHTML = combinedHtml;

            // Badge frissítés: megszámoljuk a renderelt meccseket
            var matchRows = todayContainer.querySelectorAll('.match-row');
            var todayTotal = matchRows.length;
            updateTodayCount(todayTotal);

            buildEsportSportsNav(liveCounts, todayCounts);

            attachMatchClickHandlers(todayContainer);
            if (typeof window.applyI18n === 'function') window.applyI18n(todayContainer);
            if (typeof window.refreshAllOddsButtons === 'function') {
                window.refreshAllOddsButtons();
            }
        })
        .catch(function(err) {
            console.error("Hiba a mai eSport meccsek frissítésekor:", err);
        });
    }

    // ===== MECCS KATTINTÁS KEZELŐ =====
    function attachMatchClickHandlers(container) {
        container.querySelectorAll('.match-row.clickable').forEach(function(row) {
            row.addEventListener('click', function(e) {
                // Ne legyen kattintható az odds gomb
                if (e.target.closest('.btn-add-bet') || e.target.closest('.selection-btn')) {
                    console.log('[ESPORT] Add-bet vagy selection-btn gombra kattintás');
                    return;
                }
                
                var matchId = row.getAttribute('data-match-id');
                if (matchId) loadMatchDetails(matchId);
            });
        });

        // League-header kattintás → accordion (collapse sibling groups)
        container.querySelectorAll('.league-header').forEach(function(header) {
            // Remove inline onclick to avoid double-toggle conflict
            header.removeAttribute('onclick');
            header.addEventListener('click', function() {
                var group = header.closest('.league-group');
                if (!group) return;
                var wasExpanded = group.classList.contains('expanded');
                // Collapse all siblings
                container.querySelectorAll('.league-group.expanded').forEach(function(g) {
                    g.classList.remove('expanded');
                });
                // Toggle the clicked one
                if (!wasExpanded) {
                    group.classList.add('expanded');
                }
            });
        });

        // Load-more bajnokság gomb kezelés (első 10 látszik)
        applyLeagueLimitEsport(container);
    }

    function loadMatchDetails(eventId) {
        currentMatchId = eventId;
        var container = getActiveContainer();
        container.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> ' + t('esport.loadingMatch', 'Meccs adatok betöltése...') + '</div>';
        fetch("../../backend/ApiRequest/get_match_details.php?eventId=" + eventId)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) {
                    container.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + data.error + '</div>';
                    return;
                }
                renderMatchDetails(data);
            })
            .catch(function(err) {
                console.error("Hiba:", err);
                container.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('esport.errorOccurred', 'Hiba történt.') + '</div>';
            });
    }

    function refreshMatchDetails(eventId) {
        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(function(res) { return res.json(); })
            .then(function(apiResult) {
                if (apiResult && apiResult.sports) {
                    var liveCounts = {};
                    ESPORT_SPORT_IDS.forEach(function(id) {
                        liveCounts[id] = apiResult.sports[id] || apiResult.sports[String(id)] || 0;
                    });
                    var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) { return sum + (liveCounts[id] || 0); }, 0);
                    var displayLiveCount = currentEsportId !== null ? (liveCounts[currentEsportId] || 0) : totalLive;
                    updateLiveCount(displayLiveCount);
                }
                return fetch("../../backend/ApiRequest/get_match_details.php?eventId=" + eventId);
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) return;
                var container = getActiveContainer();
                var scoreEl = container.querySelector('.score-big');
                if (scoreEl) scoreEl.textContent = data.match.score || '0 - 0';
                var liveTimeEl = container.querySelector('.live-time-big');
                if (liveTimeEl) liveTimeEl.textContent = data.match.liveTime || '-';

                var marketsContainer = container.querySelector('.markets-container');
                if (marketsContainer) {
                    var match = data.match;
                    var nameParts = match.name.split(' - ');
                    var homeTeam = match.homeTeam || nameParts[0] || '';
                    var awayTeam = match.awayTeam || (nameParts[1] || '');
                    marketsContainer.innerHTML = buildMarketsHtml(data.markets || [], homeTeam, awayTeam);
                    if (typeof window.refreshAllOddsButtons === 'function') {
                        window.refreshAllOddsButtons();
                    }
                }
            })
            .catch(function(err) {
                console.error("Hiba a meccs részletek frissítésekor:", err);
            });
    }

    window.loadMatchDetails = loadMatchDetails;

    function renderMatchDetails(data) {
        var match = data.match;
        var markets = data.markets || [];
        var nameParts = match.name.split(' - ');
        var homeTeam = match.homeTeam || nameParts[0] || '';
        var awayTeam = match.awayTeam || (nameParts[1] || '');
        var score = match.score || '0 - 0';
        var liveTime = match.liveTime || '-';
        var isLive = match.isLive;
        var startTime = match.startUtc ? new Date(match.startUtc).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' }) : '-';

        console.log('[ESPORT] renderMatchDetails - Markets count:', markets.length);
        console.log('[ESPORT] Match data:', match);
        console.log('[ESPORT] Markets:', markets);

        var marketsHtml = buildMarketsHtml(markets, homeTeam, awayTeam);

        var backLabel = activeTab === 'live' ? t('esport.backToLive', 'Vissza az élő meccsekhez') : t('esport.backToToday', 'Vissza a mai meccsekhez');

        var liveSection = '';
        if (isLive) {
            liveSection = '<div class="live-badge"><span class="live-dot-big"></span><span class="live-time-big">' + escapeHtml(liveTime) + '</span></div>';
        } else {
            liveSection = '<div class="not-started-badge"><i class="fas fa-clock"></i> <span>' + startTime + '</span></div>';
        }

        var container = getActiveContainer();
        container.innerHTML = '<div class="match-details">' +
            '<button class="back-btn" id="back-to-matches"><i class="fas fa-arrow-left"></i> ' + backLabel + '</button>' +
            '<div class="match-header-card">' +
                '<div class="match-meta">' +
                    '<span class="meta-item"><i class="fas fa-globe-europe"></i> ' + escapeHtml(td(match.country)) + '</span>' +
                    '<span class="meta-item"><i class="fas fa-trophy"></i> ' + escapeHtml(td(match.championship)) + '</span>' +
                    '<span class="meta-item"><i class="fas fa-clock"></i> ' + startTime + '</span>' +
                '</div>' +
                '<div class="match-scoreboard">' +
                    '<div class="team-side home-side"><span class="team-name-big">' + escapeHtml(homeTeam) + '</span></div>' +
                    '<div class="score-center">' +
                        '<div class="score-big">' + escapeHtml(score) + '</div>' +
                        liveSection +
                    '</div>' +
                    '<div class="team-side away-side"><span class="team-name-big">' + escapeHtml(awayTeam) + '</span></div>' +
                '</div>' +
            '</div>' +
            '<h3 class="markets-title"><i class="fas fa-chart-bar"></i> ' + t('mainMenu.bettingMarkets', 'Fogadási piacok') + '</h3>' +
            '<div class="markets-container">' + marketsHtml + '</div>' +
        '</div>';

        if (typeof window.applyI18n === 'function') window.applyI18n(container);

        var backBtn = document.getElementById('back-to-matches');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                currentMatchId = null;
                if (activeTab === 'live') {
                    refreshLiveMatches();
                } else {
                    refreshTodayMatches();
                }
            });
        }

        if (typeof window.refreshAllOddsButtons === 'function') {
            window.refreshAllOddsButtons();
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ===== LEAGUE LIMIT (első 10 bajnokság, utána "Több betöltése" gomb) =====
    var esportVisibleLeagueCount = 10;

    function applyLeagueLimitEsport(container) {
        if (!container) return;
        var allGroups = container.querySelectorAll('.league-group');
        var totalLeagues = allGroups.length;
        allGroups.forEach(function(group, idx) {
            if (idx < esportVisibleLeagueCount) {
                group.style.display = '';
            } else {
                group.style.display = 'none';
            }
        });

        // Remove old load-more button if present
        var oldBtn = container.querySelector('.load-more-leagues-btn');
        if (oldBtn) oldBtn.remove();

        var stillHidden = totalLeagues - esportVisibleLeagueCount;
        if (stillHidden > 0) {
            var loadBtn = document.createElement('button');
            loadBtn.className = 'load-more-leagues-btn';
            loadBtn.innerHTML = '<i class="fas fa-chevron-down"></i> ' + t('esport.loadMore', 'Többi bajnokság betöltése') + ' (<span class="load-more-count">' + stillHidden + '</span>)';
            loadBtn.addEventListener('click', function() {
                esportVisibleLeagueCount += 10;
                applyLeagueLimitEsport(container);
            });
            container.appendChild(loadBtn);
        }
    }

    // ===== AUTO REFRESH =====
    function refreshActive() {
        if (activeTab === 'live') {
            refreshLiveMatches();
        } else {
            refreshTodayMatches();
        }
    }

    function startAutoRefresh() {
        stopAutoRefresh();
        refreshTimer = setInterval(refreshActive, 5000);
    }

    function stopAutoRefresh() {
        if (refreshTimer) {
            clearInterval(refreshTimer);
            refreshTimer = null;
        }
    }

    document.addEventListener("visibilitychange", function() {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            refreshActive();
            startAutoRefresh();
        }
    });

    // ===== HÁTTÉR SZINKRON (API → DB) =====
    function syncFromApi() {
        fetch('../../backend/refresh_all.php', { method: 'GET' })
            .catch(function(err) { console.warn('[SYNC] Hálózati hiba:', err); });
    }
    syncFromApi();
    setInterval(syncFromApi, 60000);

    // ===== INDÍTÁS =====
    // Játék tag-ek betöltése, majd meccsek frissítése
    fetchGameTags().then(function() {
        return fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(function(res) { return res.json(); })
            .then(function(apiResult) {
                if (apiResult && apiResult.sports) {
                    var liveCounts = {};
                    ESPORT_SPORT_IDS.forEach(function(id) {
                        liveCounts[id] = apiResult.sports[String(id)] || apiResult.sports[id] || 0;
                    });
                    var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) { return sum + (liveCounts[id] || 0); }, 0);
                    var displayLiveCount = currentEsportId !== null ? (liveCounts[currentEsportId] || 0) : totalLive;
                    updateLiveCount(displayLiveCount);
                }
                if (apiResult && apiResult.sportDetails) {
                    ESPORT_SPORT_IDS.forEach(function(id) {
                        if (apiResult.sportDetails[id] || apiResult.sportDetails[String(id)]) {
                            esportDetails[id] = apiResult.sportDetails[id] || apiResult.sportDetails[String(id)];
                        }
                    });
                }
            })
            .catch(function() {});
    }).then(function() {
        refreshTodayMatches();
        startAutoRefresh();
    });

    // Game tags frissítése percenként (szinkronban a meccsekkel)
    setInterval(fetchGameTags, 60000);

    window.addEventListener('languageChanged', function() {
        refreshActive();
        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(function(res) { return res.json(); })
            .then(function(apiResult) {
                var liveCounts = {};
                var todayCounts = {};
                if (apiResult && apiResult.sports) {
                    ESPORT_SPORT_IDS.forEach(function(id) {
                        liveCounts[id] = apiResult.sports[String(id)] || apiResult.sports[id] || 0;
                        todayCounts[id] = liveCounts[id];
                    });
                }
                buildEsportSportsNav(liveCounts, todayCounts);
            })
            .catch(function() {});
    });
});
