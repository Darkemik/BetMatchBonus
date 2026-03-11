document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("matches-container");
    let refreshTimer = null;
    let currentMatchId = null;
    let currentSportId = 66;

    // Sport ID-k mapping (API alapján)
    const sportIdMap = {
        'soccer': 66,
        'basketball': 67,
        'darts': 78,
        'waterpolo': 83,
        'handball': 73,
        'hockey': 70,
        'esport': 145,
        'pingpong': 77
    };

    // ===== SPORT NAV KATTINTÁS =====
    document.querySelectorAll('.sport-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            // Active osztály váltás
            document.querySelectorAll('.sport-item').forEach(s => s.classList.remove('active'));
            item.classList.add('active');

            // Sport ID beállítása
            const sportKey = item.getAttribute('data-sport');
            currentSportId = sportIdMap[sportKey] || 66;

            // Ha meccs részleteket nézünk, visszalépünk a listára
            currentMatchId = null;

            // Azonnali frissítés
            refreshLiveMatches();
        });
    });

    function refreshLiveMatches() {
        if (currentMatchId) {
            refreshMatchDetails(currentMatchId);
            return;
        }

        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(() => {
                return fetch("../../backend/ApiRequest/live_table.php?sport_id=" + currentSportId);
            })
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
                attachMatchClickHandlers();
                const savedLang = localStorage.getItem('lang') || 'hu';
                if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
                    changeLanguageForContainer(container, savedLang);
                }
            })
            .catch(err => {
                console.error("Hiba a meccsek frissítésekor:", err);
                container.innerHTML = '<p class="text-center mt-3">Hiba történt a meccsek betöltésekor.</p>';
            });
    }

    function attachMatchClickHandlers() {
        document.querySelectorAll('.match-row.clickable').forEach(row => {
            row.addEventListener('click', () => {
                const matchId = row.getAttribute('data-match-id');
                if (matchId) {
                    loadMatchDetails(matchId);
                }
            });
        });
    }

    function loadMatchDetails(eventId) {
        currentMatchId = eventId;
        container.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Meccs adatok betöltése...</div>';

        fetch("../../backend/ApiRequest/get_match_details.php?eventId=" + eventId)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    container.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + data.error + '</div>';
                    return;
                }
                renderMatchDetails(data);
                const savedLang = localStorage.getItem('lang') || 'hu';
                if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
                    changeLanguageForContainer(container, savedLang);
                }
            })
            .catch(err => {
                console.error("Hiba:", err);
                container.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Hiba történt az adatok betöltésekor.</div>';
            });
    }

    function refreshMatchDetails(eventId) {
        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(() => fetch("../../backend/ApiRequest/get_match_details.php?eventId=" + eventId))
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                var scoreEl = container.querySelector('.score-big');
                if (scoreEl) {
                    scoreEl.textContent = data.match.score || '0 - 0';
                }

                var liveTimeEl = container.querySelector('.live-time-big');
                if (liveTimeEl) {
                    liveTimeEl.textContent = data.match.liveTime || '-';
                }

                var marketsContainer = container.querySelector('.markets-container');
                if (marketsContainer) {
                    var markets = data.markets || [];
                    var match = data.match;
                    var nameParts = match.name.split(' - ');
                    var homeTeam = match.homeTeam || nameParts[0] || '';
                    var awayTeam = match.awayTeam || (nameParts[1] || '');
                    var marketsHtml = '';
                    var validMarkets = markets.filter(function(m) { return m.selections && m.selections.length > 0; });

                    if (validMarkets.length === 0) {
                        marketsHtml = '<div class="no-markets">Jelenleg nincsenek elérhető fogadási piacok ehhez a meccshez.</div>';
                    } else {
                        validMarkets.forEach(function(market) {
                            var specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                            marketsHtml += '<div class="market-card">';
                            marketsHtml += '<div class="market-header"><span class="market-name">' + escapeHtml(market.name) + escapeHtml(specialVal) + '</span></div>';
                            marketsHtml += '<div class="market-selections">';
                            market.selections.forEach(function(sel) {
                                marketsHtml += '<button class="selection-btn" onclick="addToBetslip(\'' + escapeJs(homeTeam) + '\', \'' + escapeJs(awayTeam) + '\', \'' + escapeJs(sel.name) + '\', ' + sel.odd + ')">';
                                marketsHtml += '<span class="selection-name">' + escapeHtml(sel.name) + '</span>';
                                marketsHtml += '<span class="selection-odd">' + sel.odd.toFixed(2) + '</span>';
                                marketsHtml += '</button>';
                            });
                            marketsHtml += '</div></div>';
                        });
                    }

                    marketsContainer.innerHTML = marketsHtml;

                    var savedLang = localStorage.getItem('lang') || 'hu';
                    if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
                        changeLanguageForContainer(marketsContainer, savedLang);
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

        var marketsHtml = '';
        var validMarkets = markets.filter(function(m) { return m.selections && m.selections.length > 0; });

        if (validMarkets.length === 0) {
            marketsHtml = '<div class="no-markets">Jelenleg nincsenek elérhető fogadási piacok ehhez a meccshez.</div>';
        } else {
            validMarkets.forEach(function(market) {
                var specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                marketsHtml += '<div class="market-card">';
                marketsHtml += '<div class="market-header"><span class="market-name">' + escapeHtml(market.name) + escapeHtml(specialVal) + '</span></div>';
                marketsHtml += '<div class="market-selections">';
                market.selections.forEach(function(sel) {
                    marketsHtml += '<button class="selection-btn" onclick="addToBetslip(\'' + escapeJs(homeTeam) + '\', \'' + escapeJs(awayTeam) + '\', \'' + escapeJs(sel.name) + '\', ' + sel.odd + ')">';
                    marketsHtml += '<span class="selection-name">' + escapeHtml(sel.name) + '</span>';
                    marketsHtml += '<span class="selection-odd">' + sel.odd.toFixed(2) + '</span>';
                    marketsHtml += '</button>';
                });
                marketsHtml += '</div></div>';
            });
        }

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
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeJs(str) {
        if (!str) return '';
        return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
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