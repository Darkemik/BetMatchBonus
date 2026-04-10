document.addEventListener("DOMContentLoaded", () => {
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));
    const liveContainer = document.getElementById("matches-container");
    const todayContainer = document.getElementById("today-matches-container");
    const esportSportsNav = document.getElementById("esportSportsNav");
    let refreshTimer = null;
    let currentMatchId = null;
    let activeTab = 'today';

    // All eSport sport IDs
    const ESPORT_SPORT_IDS = [145, 146, 147, 148];
    let currentEsportId = null; // null = "Összes"

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
            var marketFullName = (market.name || '') + specialVal;
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
                    '<span class="selection-name">' + escapeHtml(sel.name) + '</span>' +
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
            currentMatchId = null;
            buildEsportSportsNav(liveCounts, todayCounts);
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
                <span class="esport-sport-name">${escapeHtml(config.name)}</span>
                <span class="esport-sport-count${count > 0 ? ' has-live' : ''}">${count}</span>
            `;
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                currentEsportId = sportId;
                currentMatchId = null;
                buildEsportSportsNav(liveCounts, todayCounts);
                refreshActive();
            });
            esportSportsNav.appendChild(btn);
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
                    updateLiveCount(totalLive);
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
                var fetches = sportIds.map(function(sid) {
                    return fetch("../../backend/ApiRequest/live_table.php?sport_id=" + sid)
                        .then(function(res) { return res.text(); });
                });
                return Promise.all(fetches);
            })
            .then(function(htmlParts) {
                if (!htmlParts) return;
                var combinedHtml = htmlParts.join('');
                if (combinedHtml.trim() === '' || combinedHtml.indexOf('Nincs élő') !== -1 && htmlParts.every(function(h) { return h.indexOf('match-row') === -1; })) {
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

        var liveFetch = fetch("../../backend/ApiRequest/get_matches_live.php").then(function(res) { return res.json(); });
        var todayFetches = sportIds.map(function(sid) {
            return fetch("../../backend/ApiRequest/mainmenu_matches.php?sport_id=" + sid)
                .then(function(res) { return res.text(); });
        });

        Promise.all([liveFetch, Promise.all(todayFetches)])
        .then(function(results) {
            var liveResult = results[0];
            var todayHtmlParts = results[1];

            var liveCounts = {};
            var todayCounts = {};
            if (liveResult && liveResult.sports) {
                ESPORT_SPORT_IDS.forEach(function(id) {
                    liveCounts[id] = liveResult.sports[id] || liveResult.sports[String(id)] || 0;
                });
                var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) { return sum + (liveCounts[id] || 0); }, 0);
                updateLiveCount(totalLive);
            }

            // Save sport details from backend
            if (liveResult && liveResult.sportDetails) {
                ESPORT_SPORT_IDS.forEach(function(id) {
                    if (liveResult.sportDetails[id] || liveResult.sportDetails[String(id)]) {
                        esportDetails[id] = liveResult.sportDetails[id] || liveResult.sportDetails[String(id)];
                    }
                });
            }

            var combinedHtml = todayHtmlParts.join('');
            todayContainer.innerHTML = combinedHtml;

            // Badge frissítés: megszámoljuk a renderelt meccseket
            var matchRows = todayContainer.querySelectorAll('.match-row');
            var todayTotal = matchRows.length;
            updateTodayCount(todayTotal);

            // Today counts for nav
            sportIds.forEach(function(sid, idx) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = todayHtmlParts[idx];
                todayCounts[sid] = tempDiv.querySelectorAll('.match-row').length;
            });
            // If "Összes" is selected, fill all sport counts
            if (currentEsportId === null) {
                // Already filled above
            }

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
                    var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) {
                        return sum + (apiResult.sports[id] || apiResult.sports[String(id)] || 0);
                    }, 0);
                    updateLiveCount(totalLive);
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
                    '<span class="meta-item"><i class="fas fa-globe-europe"></i> ' + escapeHtml(match.country) + '</span>' +
                    '<span class="meta-item"><i class="fas fa-trophy"></i> ' + escapeHtml(match.championship) + '</span>' +
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
    fetch("../../backend/ApiRequest/get_matches_live.php")
        .then(function(res) { return res.json(); })
        .then(function(apiResult) {
            if (apiResult && apiResult.sports) {
                var totalLive = ESPORT_SPORT_IDS.reduce(function(sum, id) {
                    return sum + (apiResult.sports[String(id)] || apiResult.sports[id] || 0);
                }, 0);
                updateLiveCount(totalLive);
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

    refreshTodayMatches();
    startAutoRefresh();
});
