document.addEventListener("DOMContentLoaded", () => {
    const liveContainer = document.getElementById("matches-container");
    const todayContainer = document.getElementById("today-matches-container");
    let refreshTimer = null;
    let currentMatchId = null;
    let activeTab = 'today';
    const ESPORT_SPORT_ID = 145;

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
            '</button>';
        }

        var isActive = isInBetslip(homeTeam, awayTeam, sel.name, marketFullName);
        var hiddenClass = '';

        // 1X2 piacon: nem zárolunk, csak halványítunk
        if (is1X2OrMatchWinner(marketFullName) && hasOtherInMarket(homeTeam, awayTeam, marketFullName, sel.name)) {
            hiddenClass = ' hidden-other';
        }
        
        // Correct Score: ha van 1X2 választás, halványítsd az ellentétes lehetőségeket
        if (isCorrectScoreMarket(marketFullName) && is1X2SelectedInMatch(homeTeam, awayTeam)) {
            if (isConflictingCorrectScore(sel.name, homeTeam, awayTeam, marketFullName)) {
                hiddenClass = ' hidden-other';
            }
        }

        var matchIdAttr = currentMatchId ? ' data-match-id="' + currentMatchId + '"' : '';
        var activeClass = isActive ? ' active' : '';

        return '<button class="selection-btn' + activeClass + hiddenClass + '" ' +
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

    // ===== SEGÉDFÜGGVÉNYEK =====
    function is1X2OrMatchWinner(marketName) {
        var marketLower = (marketName || '').toLowerCase();
        return marketLower.indexOf('1x2') !== -1 || 
               marketLower.indexOf('winner') !== -1 || 
               marketLower.indexOf('győztes') !== -1 ||
               marketLower.indexOf('match result') !== -1 ||
               marketLower.indexOf('full time result') !== -1 ||
               marketLower.indexOf('moneyline') !== -1;
    }

    function isCorrectScoreMarket(marketName) {
        var marketLower = (marketName || '').toLowerCase();
        return marketLower.indexOf('correct score') !== -1 || 
               marketLower.indexOf('pontos végeredmény') !== -1 ||
               marketLower.indexOf('exact score') !== -1 ||
               marketLower.indexOf('végeredmény') !== -1;
    }

    function is1X2SelectedInMatch(homeTeam, awayTeam) {
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
        return betslip.some(function(item) {
            return item.homeTeam === homeTeam && 
                   item.awayTeam === awayTeam && 
                   is1X2OrMatchWinner(item.market);
        });
    }

    function get1X2Selection(homeTeam, awayTeam) {
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
        var found = betslip.find(function(item) {
            return item.homeTeam === homeTeam && 
                   item.awayTeam === awayTeam && 
                   is1X2OrMatchWinner(item.market);
        });
        return found ? found.pick.toLowerCase() : null;
    }

    function isConflictingCorrectScore(scoreString, homeTeam, awayTeam, marketName) {
        var pick1X2 = get1X2Selection(homeTeam, awayTeam);
        if (!pick1X2) return false;

        // scoreString formátum: "1:0", "0:1", "2:2", stb.
        var parts = scoreString.split(':');
        if (parts.length !== 2) return false;
        
        var homeGoals = parseInt(parts[0]);
        var awayGoals = parseInt(parts[1]);
        
        if (isNaN(homeGoals) || isNaN(awayGoals)) return false;

        // Ellenőrizzük az 1X2 választás alapján
        if (pick1X2 === '1' || pick1X2 === 'home') {
            // Ha az "1. nyer" választva van, az olyan scorek ellentétes amelyek nem home win
            return !(homeGoals > awayGoals);
        } else if (pick1X2 === '2' || pick1X2 === 'away') {
            // Ha az "2. nyer" választva van, az olyan scorek ellentétes amelyek nem away win
            return !(awayGoals > homeGoals);
        } else if (pick1X2 === 'x' || pick1X2 === 'draw' || pick1X2 === 'döntetlen') {
            // Ha a "döntetlen" választva van, az olyan scorek ellentétes amelyek nem döntetlen
            return !(homeGoals === awayGoals);
        }

        return false;
    }

    function hasOtherInMarket(homeTeam, awayTeam, marketName, currentPick) {
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
        return betslip.some(function(item) {
            return item.homeTeam === homeTeam && 
                   item.awayTeam === awayTeam && 
                   item.market === marketName && 
                   item.pick !== currentPick;
        });
    }

    // ===== ODDS GOMBOK KATTINTÁS KEZELŐ - AZONNALI FRISSÍTÉS =====
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.selection-btn');
        if (!btn) return;
        if (btn.classList.contains('disabled')) return;
        if (btn.classList.contains('market-locked')) return;

        e.preventDefault();
        e.stopPropagation();

        var homeTeam = btn.getAttribute('data-home');
        var awayTeam = btn.getAttribute('data-away');
        var pick = btn.getAttribute('data-pick');
        var odds = parseFloat(btn.getAttribute('data-odd'));
        var market = btn.getAttribute('data-market');
        var matchId = parseInt(btn.getAttribute('data-match-id')) || 0;

        if (!homeTeam || !awayTeam || !pick || !market) return;

        console.log('[ESPORT] Click handler fired:', {homeTeam, awayTeam, pick, odds, market});

        // ===== AZONNAL VIZUÁLISAN FRISSÍTJÜK A GOMBOT ÉS A PIACA GOMBOKAT =====
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
        var isInSlip = betslip.some(function(item) {
            return item.homeTeam === homeTeam && item.awayTeam === awayTeam && 
                   item.pick === pick && item.market === market;
        });

        if (isInSlip) {
            // ===== ELTÁVOLÍTÁS =====
            console.log('[ESPORT] Removing from betslip');
            btn.classList.remove('active');
            
            // Azonnal feloldunk más gombokat ebben a piacon
            updateMarketButtonsImmediately(homeTeam, awayTeam, market, betslip, true);
            
            if (typeof window.removeFromBetslip === 'function') {
                window.removeFromBetslip(homeTeam, awayTeam, pick, market);
            }
        } else {
            // ===== HOZZÁADÁS =====
            console.log('[ESPORT] Adding to betslip');
            btn.classList.add('active');
            
            // Azonnal zárolunk más gombokat ebben a piacon
            updateMarketButtonsImmediately(homeTeam, awayTeam, market, betslip, false);
            
            if (typeof window.addToBetslip === 'function') {
                window.addToBetslip(homeTeam, awayTeam, pick, odds, market, matchId);
            }
        }
    });

    // ===== PIACA GOMBÓK AZONNALI FRISSÍTÉSE =====
    function updateMarketButtonsImmediately(homeTeam, awayTeam, marketName, betslip, isRemoving) {
        // Összes selection-btn gomb szűrése
        document.querySelectorAll('.selection-btn').forEach(function(btn) {
            var btnHome = btn.getAttribute('data-home');
            var btnAway = btn.getAttribute('data-away');
            var btnMarket = btn.getAttribute('data-market');
            var btnPick = btn.getAttribute('data-pick');

            // Csak az ugyanabban a piacon és meccsben lévő gombokra koncentrálunk
            if (btnHome !== homeTeam || btnAway !== awayTeam || btnMarket !== marketName) {
                return; // Ez nem ebben a piacon van
            }

            // Ezt a gombot már kezelni fogjuk az addToBetslip/removeFromBetslip-ben
            if (btn.classList.contains('disabled')) {
                return;
            }

            // Ellenőrizzük az új betslip állapotot
            var updatedBetslip = JSON.parse(localStorage.getItem('betslip') || '[]');
            
            var hasAnyInMarket = updatedBetslip.some(function(item) {
                return item.homeTeam === homeTeam && 
                       item.awayTeam === awayTeam && 
                       item.market === marketName;
            });

            var thisButtonInSlip = updatedBetslip.some(function(item) {
                return item.homeTeam === btnHome && 
                       item.awayTeam === btnAway && 
                       item.pick === btnPick && 
                       item.market === btnMarket;
            });

            btn.classList.remove('active', 'market-locked');

            if (thisButtonInSlip) {
                btn.classList.add('active');
            } else if (hasAnyInMarket) {
                btn.classList.add('market-locked');
            }
        });
    }

    // ===== PIACA BELÜLI GOMBOK INTELLIGENS FRISSÍTÉSE =====
    function updateMarketButtons(homeTeam, awayTeam, marketName, betslip) {
        document.querySelectorAll('.market-selections').forEach(function(marketContainer) {
            var buttons = Array.from(marketContainer.querySelectorAll('.selection-btn'));
            var actionableButtons = buttons.filter(function(b) {
                return b.getAttribute('data-market') !== null;
            });
            if (actionableButtons.length === 0) return;

            var firstMarket = actionableButtons[0].getAttribute('data-market') || '';
            var firstHome = actionableButtons[0].getAttribute('data-home') || '';
            var firstAway = actionableButtons[0].getAttribute('data-away') || '';

            if (firstMarket !== marketName || firstHome !== homeTeam || firstAway !== awayTeam) {
                return;
            }

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

                b.classList.remove('active', 'market-locked');
                b.removeAttribute('disabled');

                if (inSlip) {
                    b.classList.add('active');
                    var lockIcon = b.querySelector('.lock-icon');
                    if (lockIcon && odd) {
                        lockIcon.outerHTML = '<span class="selection-odd">' + parseFloat(odd).toFixed(2) + '</span>';
                    }
                } else if (hasActiveInMarket) {
                    b.classList.add('market-locked');
                    var oddSpan = b.querySelector('.selection-odd');
                    if (oddSpan) {
                        oddSpan.outerHTML = '<span class="lock-icon"><i class="fas fa-lock"></i></span>';
                    }
                } else {
                    var lockIcon2 = b.querySelector('.lock-icon');
                    if (lockIcon2 && odd) {
                        lockIcon2.outerHTML = '<span class="selection-odd">' + parseFloat(odd).toFixed(2) + '</span>';
                    }
                }
            });
        });
    }

    window.refreshActiveOddsButtons = function() {
        // Ez már az addToBetslip végzi el
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
            fetch("../../backend/ApiRequest/esport_today.php").then(function(res) { return res.json(); })
        ])
        .then(function(results) {
            var liveResult = results[0];
            var todayResult = results[1];

            if (liveResult && liveResult.sports) {
                var esportLive = liveResult.sports[ESPORT_SPORT_ID] || 0;
                updateLiveCount(esportLive);
            }

            if (todayResult && todayResult.total !== undefined) {
                updateTodayCount(todayResult.total);
            }

            return fetch("../../backend/ApiRequest/esport_today_table.php");
        })
        .then(function(res) { return res.text(); })
        .then(function(html) {
            todayContainer.innerHTML = html;
            attachMatchClickHandlers(todayContainer);
            applyTranslation(todayContainer);
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
            row.addEventListener('click', function() {
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

        document.getElementById('back-to-matches').addEventListener('click', function() {
            currentMatchId = null;
            if (activeTab === 'live') {
                refreshLiveMatches();
            } else {
                refreshTodayMatches();
            }
        });
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
