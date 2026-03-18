document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("matches-container");
    let refreshTimer = null;
    let currentMatchId = null;
    let currentSportId = 66;

    const sportIdMap = {
        'soccer': 66,
        'basketball': 67,
        'darts': 78,
        'waterpolo': 83,
        'handball': 73,
        'hockey': 70,
        'pingpong': 77
    };

    // ===== ODDS GOMB ÉPÍTÉS =====
    function buildSelectionButton(sel, homeTeam, awayTeam, marketFullName) {
        if (sel.odd <= 1.0) {
            return '<button class="selection-btn disabled" disabled>' +
                '<span class="selection-name">' + escapeHtml(sel.name) + '</span>' +
            '</button>';
        }

        var state = window.BetslipLogic.getButtonState(homeTeam, awayTeam, sel.name, marketFullName);
        var stateClass = state ? ' ' + state : '';
        var isDisabled = state === 'disabled' ? ' disabled' : '';

        var matchIdAttr = currentMatchId ? ' data-match-id="' + currentMatchId + '"' : '';

        return '<button class="selection-btn' + stateClass + '"' + isDisabled + ' ' +
            'data-home="' + escapeHtml(homeTeam) + '" ' +
            'data-away="' + escapeHtml(awayTeam) + '" ' +
            'data-pick="' + escapeHtml(sel.name) + '" ' +
            'data-odd="' + sel.odd + '" ' +
            'data-market="' + escapeHtml(marketFullName) + '"' +
            matchIdAttr + '>' +
            '<span class="selection-name">' + escapeHtml(sel.name) + '</span>' +
            '<span class="selection-odd">' + sel.odd.toFixed(2) + '</span>' +
        '</button>';
    }

    // ===== ODDS GOMBOK KATTINTÁS KEZELŐ =====
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.selection-btn');
        if (!btn) return;
        if (btn.classList.contains('disabled')) return;

        e.preventDefault();
        e.stopPropagation();

        var homeTeam = btn.getAttribute('data-home');
        var awayTeam = btn.getAttribute('data-away');
        var pick = btn.getAttribute('data-pick');
        var odds = parseFloat(btn.getAttribute('data-odd'));
        var market = btn.getAttribute('data-market');
        var matchId = parseInt(btn.getAttribute('data-match-id')) || 0;

        if (!homeTeam || !awayTeam || !pick || !market) return;

        console.log('[LIVE] Toggle odds:', {homeTeam, awayTeam, pick, odds, market});

        if (typeof window.toggleOdds === 'function') {
            window.toggleOdds(homeTeam, awayTeam, pick, odds, market, matchId);
            
            // AZONNAL frissítjük az összes gombot
            setTimeout(function() {
                if (typeof window.refreshAllOddsButtons === 'function') {
                    window.refreshAllOddsButtons();
                }
            }, 50);
        }
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
                html += buildSelectionButton(sel, homeTeam, awayTeam, marketFullName);
            });
            html += '</div></div>';
        });
        return html;
    }

    // ===== SPORT NAV =====
    document.querySelectorAll('.sport-item').forEach(function(item) {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            document.querySelectorAll('.sport-item').forEach(function(s) { s.classList.remove('active'); });
            item.classList.add('active');
            var sportKey = item.getAttribute('data-sport');
            currentSportId = sportIdMap[sportKey] || 66;
            currentMatchId = null;
            refreshLiveMatches();
        });
    });

    // ===== BADGE =====
    function updateSportCounts(sportCounts) {
        document.querySelectorAll('.sport-count').forEach(function(badge) {
            var sportId = badge.getAttribute('data-sport-id');
            var count = (sportCounts && sportCounts[sportId]) ? sportCounts[sportId] : 0;
            badge.textContent = count;
            if (count > 0) {
                badge.classList.add('has-live');
            } else {
                badge.classList.remove('has-live');
            }
        });
    }

    // ===== MECCSEK FRISSÍTÉS =====
    function refreshLiveMatches() {
        if (currentMatchId) {
            refreshMatchDetails(currentMatchId);
            return;
        }
        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(function(res) { return res.json(); })
            .then(function(apiResult) {
                if (apiResult && apiResult.sports) {
                    updateSportCounts(apiResult.sports);
                }
                return fetch("../../backend/ApiRequest/live_table.php?sport_id=" + currentSportId);
            })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                container.innerHTML = html;
                attachMatchClickHandlers();
                var savedLang = localStorage.getItem('lang') || 'hu';
                if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
                    changeLanguageForContainer(container, savedLang);
                }
                if (typeof window.refreshAllOddsButtons === 'function') {
                    window.refreshAllOddsButtons();
                }
            })
            .catch(function(err) {
                console.error("Hiba a meccsek frissítésekor:", err);
            });
    }

    function attachMatchClickHandlers() {
        document.querySelectorAll('.match-row.clickable').forEach(function(row) {
            row.addEventListener('click', function() {
                var matchId = row.getAttribute('data-match-id');
                if (matchId) loadMatchDetails(matchId);
            });
        });
    }

    function loadMatchDetails(eventId) {
        currentMatchId = eventId;
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
                if (apiResult && apiResult.sports) updateSportCounts(apiResult.sports);
                return fetch("../../backend/ApiRequest/get_match_details.php?eventId=" + eventId);
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.error) return;
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
        var startTime = match.startUtc ? new Date(match.startUtc).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' }) : '-';

        var marketsHtml = buildMarketsHtml(markets, homeTeam, awayTeam);

        container.innerHTML = '<div class="match-details">' +
            '<button class="back-btn" id="back-to-matches"><i class="fas fa-arrow-left"></i> Vissza az élő meccsekhez</button>' +
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
                        '<div class="live-badge"><span class="live-dot-big"></span><span class="live-time-big">' + escapeHtml(liveTime) + '</span></div>' +
                    '</div>' +
                    '<div class="team-side away-side"><span class="team-name-big">' + escapeHtml(awayTeam) + '</span></div>' +
                '</div>' +
            '</div>' +
            '<h3 class="markets-title"><i class="fas fa-chart-bar"></i> Fogadási piacok</h3>' +
            '<div class="markets-container">' + marketsHtml + '</div>' +
        '</div>';

        document.getElementById('back-to-matches').addEventListener('click', function() {
            currentMatchId = null;
            refreshLiveMatches();
        });

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

    function startAutoRefresh() {
        stopAutoRefresh();
        refreshTimer = setInterval(refreshLiveMatches, 5000);
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
            refreshLiveMatches();
            startAutoRefresh();
        }
    });

    attachMatchClickHandlers();
    startAutoRefresh();
});