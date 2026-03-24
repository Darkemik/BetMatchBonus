document.addEventListener("DOMContentLoaded", () => {
    const liveContainer = document.getElementById("matches-container");
    const todayContainer = document.getElementById("today-matches-container");
    let refreshTimer = null;
    let currentMatchId = null;
    let activeTab = 'today';
    const ESPORT_SPORT_ID = 145;

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
            return '<div class="no-markets">Jelenleg nincsenek elérhető fogadási piacok ehhez a meccshez.</div>';
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

            if (target === 'live') {
                refreshLiveMatches();
            } else if (target === 'today') {
                refreshTodayMatches();
            }
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
                if (apiResult && apiResult.sports) {
                    var esportCount = apiResult.sports[ESPORT_SPORT_ID] || 0;
                    updateLiveCount(esportCount);
                }
                return fetch("../../backend/ApiRequest/live_table.php?sport_id=" + ESPORT_SPORT_ID);
            })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                liveContainer.innerHTML = html;
                attachMatchClickHandlers(liveContainer);
                applyTranslation(liveContainer);
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
        Promise.all([
            fetch("../../backend/ApiRequest/get_matches_live.php").then(function(res) { return res.json(); }),
            fetch("../../backend/ApiRequest/mainmenu_matches.php?sport_id=" + ESPORT_SPORT_ID).then(function(res) { return res.text(); })
        ])
        .then(function(results) {
            var liveResult = results[0];
            var todayHtml = results[1];

            if (liveResult && liveResult.sports) {
                var esportLive = liveResult.sports[ESPORT_SPORT_ID] || 0;
                updateLiveCount(esportLive);
            }

            todayContainer.innerHTML = todayHtml;

            // Badge frissítés: megszámoljuk a renderelt meccseket
            var matchRows = todayContainer.querySelectorAll('.match-row');
            updateTodayCount(matchRows.length);

            attachMatchClickHandlers(todayContainer);
            applyTranslation(todayContainer);
            if (typeof window.refreshAllOddsButtons === 'function') {
                window.refreshAllOddsButtons();
            }
        })
        .catch(function(err) {
            console.error("Hiba a mai eSport meccsek frissítésekor:", err);
        });
    }

    // ===== NYELV ALKALMAZÁS =====
    function applyTranslation(container) {
        var savedLang = localStorage.getItem('lang') || 'hu';
        if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
            changeLanguageForContainer(container, savedLang);
        }
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
        container.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Meccs adatok betöltése...</div>';
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
                container.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Hiba történt.</div>';
            });
    }

    function refreshMatchDetails(eventId) {
        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(function(res) { return res.json(); })
            .then(function(apiResult) {
                if (apiResult && apiResult.sports) {
                    updateLiveCount(apiResult.sports[ESPORT_SPORT_ID] || 0);
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

        var backLabel = activeTab === 'live' ? 'Vissza az élő meccsekhez' : 'Vissza a mai meccsekhez';

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
            '<h3 class="markets-title"><i class="fas fa-chart-bar"></i> Fogadási piacok</h3>' +
            '<div class="markets-container">' + marketsHtml + '</div>' +
        '</div>';

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

    // ===== INDÍTÁS =====
    fetch("../../backend/ApiRequest/get_matches_live.php")
        .then(function(res) { return res.json(); })
        .then(function(apiResult) {
            if (apiResult && apiResult.sports) {
                var esportLive = apiResult.sports[String(ESPORT_SPORT_ID)] || apiResult.sports[ESPORT_SPORT_ID] || 0;
                updateLiveCount(esportLive);
            }
        })
        .catch(function() {});

    refreshTodayMatches();
    startAutoRefresh();
});
