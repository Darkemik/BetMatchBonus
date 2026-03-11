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
        'esport': 145,
        'pingpong': 77
    };

    // ===== BETSLIP ELLENŐRZÉS =====
    function isInBetslip(homeTeam, awayTeam, pickName, marketName) {
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
        return betslip.some(function(item) {
            return item.homeTeam === homeTeam && item.awayTeam === awayTeam && item.pick === pickName && item.market === marketName;
        });
    }

    // ===== ODDS GOMB ÉPÍTÉS =====
    function buildSelectionButton(sel, homeTeam, awayTeam, marketFullName) {
        if (sel.odd <= 1.0) {
            return '<button class="selection-btn disabled" disabled>' +
                '<span class="selection-name">' + escapeHtml(sel.name) + '</span>' +
                '<span class="lock-icon"><i class="fas fa-lock"></i></span>' +
            '</button>';
        }

        var isActive = isInBetslip(homeTeam, awayTeam, sel.name, marketFullName);
        var isMarketLocked = false;

        if (!isActive) {
            var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
            isMarketLocked = betslip.some(function(item) {
                return item.homeTeam === homeTeam && item.awayTeam === awayTeam && item.market === marketFullName && item.pick !== sel.name;
            });
        }

        if (isMarketLocked) {
            return '<button class="selection-btn market-locked" ' +
                'data-home="' + escapeHtml(homeTeam) + '" ' +
                'data-away="' + escapeHtml(awayTeam) + '" ' +
                'data-pick="' + escapeHtml(sel.name) + '" ' +
                'data-odd="' + sel.odd + '" ' +
                'data-market="' + escapeHtml(marketFullName) + '">' +
                '<span class="selection-name">' + escapeHtml(sel.name) + '</span>' +
                '<span class="lock-icon"><i class="fas fa-lock"></i></span>' +
            '</button>';
        }

        var activeClass = isActive ? ' active' : '';
        return '<button class="selection-btn' + activeClass + '" ' +
            'data-home="' + escapeHtml(homeTeam) + '" ' +
            'data-away="' + escapeHtml(awayTeam) + '" ' +
            'data-pick="' + escapeHtml(sel.name) + '" ' +
            'data-odd="' + sel.odd + '" ' +
            'data-market="' + escapeHtml(marketFullName) + '">' +
            '<span class="selection-name">' + escapeHtml(sel.name) + '</span>' +
            '<span class="selection-odd">' + sel.odd.toFixed(2) + '</span>' +
        '</button>';
    }

    // ===== ODDS GOMBOK KATTINTÁS KEZELŐ =====
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.selection-btn');
        if (!btn) return;
        if (btn.classList.contains('disabled')) return;
        if (btn.classList.contains('market-locked')) return;

        var homeTeam = btn.getAttribute('data-home');
        var awayTeam = btn.getAttribute('data-away');
        var pick = btn.getAttribute('data-pick');
        var odds = parseFloat(btn.getAttribute('data-odd'));
        var market = btn.getAttribute('data-market');

        if (!homeTeam || !awayTeam || !pick || !market) return;

        if (isInBetslip(homeTeam, awayTeam, pick, market)) {
            if (typeof window.removeFromBetslip === 'function') {
                window.removeFromBetslip(homeTeam, awayTeam, pick, market);
            }
            btn.classList.remove('active');
        } else {
            if (typeof window.addToBetslip === 'function') {
                window.addToBetslip(homeTeam, awayTeam, pick, odds, market);
            }
            btn.classList.add('active');
        }

        refreshMarketButtons();
    });

    // ===== PIACON BELÜLI GOMBOK FRISSÍTÉSE =====
    function refreshMarketButtons() {
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');

        document.querySelectorAll('.market-selections').forEach(function(marketContainer) {
            var buttons = Array.from(marketContainer.querySelectorAll('.selection-btn'));
            // Szűrjük ki az 1.00 disabled gombokat (nincs data-market attribútumuk)
            var actionableButtons = buttons.filter(function(b) {
                return b.getAttribute('data-market') !== null;
            });
            if (actionableButtons.length === 0) return;

            var marketName = actionableButtons[0].getAttribute('data-market') || '';
            var firstHome = actionableButtons[0].getAttribute('data-home') || '';
            var firstAway = actionableButtons[0].getAttribute('data-away') || '';

            var hasActiveInMarket = betslip.some(function(item) {
                return item.market === marketName && item.homeTeam === firstHome && item.awayTeam === firstAway;
            });

            actionableButtons.forEach(function(b) {
                var home = b.getAttribute('data-home');
                var away = b.getAttribute('data-away');
                var pick = b.getAttribute('data-pick');
                var bMarket = b.getAttribute('data-market');
                var odd = b.getAttribute('data-odd');

                var inSlip = betslip.some(function(item) {
                    return item.homeTeam === home && item.awayTeam === away && item.pick === pick && item.market === bMarket;
                });

                // Először reseteljük a gombot
                b.classList.remove('active', 'market-locked');
                b.removeAttribute('disabled');

                if (inSlip) {
                    b.classList.add('active');
                    // Győződjünk meg, hogy odds van megjelenítve, nem lakat
                    var lockIcon = b.querySelector('.lock-icon');
                    if (lockIcon && odd) {
                        lockIcon.outerHTML = '<span class="selection-odd">' + parseFloat(odd).toFixed(2) + '</span>';
                    }
                } else if (hasActiveInMarket) {
                    b.classList.add('market-locked');
                    // Odds cseréje lakatra
                    var oddSpan = b.querySelector('.selection-odd');
                    if (oddSpan) {
                        oddSpan.outerHTML = '<span class="lock-icon"><i class="fas fa-lock"></i></span>';
                    }
                } else {
                    // Szabad gomb - lakat visszacserélése oddsra
                    var lockIcon2 = b.querySelector('.lock-icon');
                    if (lockIcon2 && odd) {
                        lockIcon2.outerHTML = '<span class="selection-odd">' + parseFloat(odd).toFixed(2) + '</span>';
                    }
                }
            });
        });
    }

    window.refreshActiveOddsButtons = function() {
        refreshMarketButtons();
    };

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