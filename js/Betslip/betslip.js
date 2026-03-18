/**
 * BETSLIP.JS - Szelvény/Ticket kezelés
 * Adatbázis: Tickets + TicketSelections táblák
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[BETSLIP] Inicializálás...');

    let ticketItems = [];
    let bettingHistory = [];
    let historyCheckTimer = null;

    // ===== CENTRÁLIS LOGIKA =====
    window.BetslipLogic = {
        is1X2Market: function(marketName) {
            var marketLower = (marketName || '').toLowerCase();
            return marketLower.indexOf('1x2') !== -1 || 
                   marketLower.indexOf('winner') !== -1 || 
                   marketLower.indexOf('győztes') !== -1 ||
                   marketLower.indexOf('match result') !== -1 ||
                   marketLower.indexOf('full time result') !== -1 ||
                   marketLower.indexOf('moneyline') !== -1;
        },

        isCorrectScoreMarket: function(marketName) {
            var marketLower = (marketName || '').toLowerCase();
            return marketLower.indexOf('correct score') !== -1 || 
                   marketLower.indexOf('pontos végeredmény') !== -1 ||
                   marketLower.indexOf('exact score') !== -1 ||
                   marketLower.indexOf('végeredmény') !== -1;
        },

        hasSelectionInMarket: function(homeTeam, awayTeam, market) {
            return ticketItems.some(function(item) {
                return item.homeTeam === homeTeam && 
                       item.awayTeam === awayTeam && 
                       item.market === market;
            });
        },

        get1X2Selection: function(homeTeam, awayTeam) {
            var found = ticketItems.find(function(item) {
                return item.homeTeam === homeTeam && 
                       item.awayTeam === awayTeam && 
                       window.BetslipLogic.is1X2Market(item.market);
            });
            return found ? found.pick.toLowerCase() : null;
        },

        isConflictingScore: function(scoreStr, homeTeam, awayTeam) {
            var pick1X2 = window.BetslipLogic.get1X2Selection(homeTeam, awayTeam);
            if (!pick1X2) return false;

            var parts = scoreStr.split(':');
            if (parts.length !== 2) return false;
            
            var homeGoals = parseInt(parts[0]);
            var awayGoals = parseInt(parts[1]);
            
            if (isNaN(homeGoals) || isNaN(awayGoals)) return false;

            if (pick1X2 === '1' || pick1X2 === 'home') {
                return !(homeGoals > awayGoals);
            } else if (pick1X2 === '2' || pick1X2 === 'away') {
                return !(awayGoals > homeGoals);
            } else if (pick1X2 === 'x' || pick1X2 === 'draw' || pick1X2 === 'döntetlen') {
                return !(homeGoals === awayGoals);
            }
            return false;
        },

        getButtonState: function(homeTeam, awayTeam, pick, market) {
            var inTicket = ticketItems.some(function(item) {
                return item.homeTeam === homeTeam && 
                       item.awayTeam === awayTeam && 
                       item.pick === pick && 
                       item.market === market;
            });
            
            if (inTicket) return 'active';

            var hasOther = window.BetslipLogic.hasSelectionInMarket(homeTeam, awayTeam, market);
            if (hasOther) return 'disabled';

            if (window.BetslipLogic.isCorrectScoreMarket(market)) {
                if (window.BetslipLogic.isConflictingScore(pick, homeTeam, awayTeam)) {
                    return 'disabled';
                }
            }

            return null;
        }
    };

    // ===== ADATOK BETÖLTÉSE =====
    function loadFromStorage() {
        ticketItems = JSON.parse(localStorage.getItem('betslip') || '[]');
        bettingHistory = JSON.parse(localStorage.getItem('bettingHistory') || '[]');
    }

    function saveToStorage() {
        localStorage.setItem('betslip', JSON.stringify(ticketItems));
        localStorage.setItem('bettingHistory', JSON.stringify(bettingHistory));
    }

    // ===== TOGGLE: HOZZÁADÁS / ELTÁVOLÍTÁS =====
    window.toggleOdds = function(homeTeam, awayTeam, pick, odds, market, matchId) {
        var existingIndex = ticketItems.findIndex(function(item) {
            return item.homeTeam === homeTeam && 
                   item.awayTeam === awayTeam && 
                   item.pick === pick && 
                   item.market === market;
        });

        if (existingIndex >= 0) {
            ticketItems.splice(existingIndex, 1);
        } else {
            if (window.BetslipLogic.hasSelectionInMarket(homeTeam, awayTeam, market)) {
                return;
            }

            if (window.BetslipLogic.isCorrectScoreMarket(market) && 
                window.BetslipLogic.isConflictingScore(pick, homeTeam, awayTeam)) {
                return;
            }

            ticketItems.push({
                homeTeam: homeTeam,
                awayTeam: awayTeam,
                pick: pick,
                odds: odds,
                market: market,
                matchId: matchId || 0,
                addedAt: new Date().toISOString()
            });
        }

        saveToStorage();
        renderTicket();
        refreshAllOddsButtons();
    };

    // ===== TICKET RENDERELÉS =====
    function renderTicket() {
        const empty = document.getElementById('betslip-empty');
        const betsContainer = document.getElementById('betslip-bets');
        const submitBtn = document.getElementById('place-bet-btn');
        const clearBtn = document.getElementById('clear-bets-btn');

        if (!empty || !betsContainer) return;

        if (ticketItems.length === 0) {
            empty.style.display = 'flex';
            betsContainer.style.display = 'none';
            submitBtn.disabled = true;
            clearBtn.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        betsContainer.style.display = 'flex';
        submitBtn.disabled = false;
        clearBtn.style.display = 'block';

        betsContainer.innerHTML = '';
        let totalOdds = 1;

        ticketItems.forEach((item, idx) => {
            totalOdds *= item.odds;
            const el = document.createElement('div');
            el.className = 'betslip-item';
            el.innerHTML = `
                <div class="betslip-item-header">
                    <span>${escapeHtml(item.homeTeam)} vs ${escapeHtml(item.awayTeam)}</span>
                    <button class="betslip-remove" data-index="${idx}" title="Eltávolítás">×</button>
                </div>
                <div class="betslip-item-market">${escapeHtml(item.market)}</div>
                <div class="betslip-item-pick">${escapeHtml(item.pick)}</div>
                <div class="betslip-item-odds">${item.odds.toFixed(2)}</div>
            `;
            betsContainer.appendChild(el);
        });

        document.querySelectorAll('.betslip-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-index'));
                ticketItems.splice(idx, 1);
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();
            });
        });

        document.getElementById('betslip-count').textContent = ticketItems.length;
        document.getElementById('total-odds').textContent = totalOdds.toFixed(3);
        updatePotentialWin(totalOdds);
    }

    function updatePotentialWin(totalOdds) {
        const stakeInput = document.getElementById('stake-input');
        const stake = parseFloat(stakeInput.value) || 0;
        const win = Math.round(stake * totalOdds);
        document.getElementById('potential-payout').textContent = 
            win.toLocaleString('hu-HU') + ' Ft';
    }

    const stakeInput = document.getElementById('stake-input');
    if (stakeInput) {
        stakeInput.addEventListener('input', () => {
            let totalOdds = 1;
            ticketItems.forEach(item => totalOdds *= item.odds);
            updatePotentialWin(totalOdds);
        });
    }

    // ===== TICKET ELKÜLDÉSE =====
    const submitBtn = document.getElementById('place-bet-btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (ticketItems.length === 0) return;
            
            const stake = parseFloat(stakeInput.value) || 0;
            if (stake < 100) {
                alert('Minimum tét: 100 Ft');
                return;
            }

            let totalOdds = 1;
            ticketItems.forEach(item => totalOdds *= item.odds);

            const payload = {
                stake: stake,
                totalOdds: totalOdds,
                potentialWin: Math.round(stake * totalOdds),
                items: ticketItems
            };

            fetch('../../backend/ApiRequest/submit_ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    alert('✅ Ticket sikeresen leadva!');
                    ticketItems = [];
                    saveToStorage();
                    renderTicket();
                    loadBettingHistory();
                } else {
                    alert('❌ Hiba: ' + (data.message || 'Ismeretlen hiba'));
                }
            })
            .catch(e => {
                console.error('Hiba:', e);
                alert('❌ Szerverhiba!');
            });
        });
    }

    // ===== CLEAR BUTTON =====
    const clearBtn = document.getElementById('clear-bets-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (confirm('Biztosan törlöd az összes fogadást?')) {
                ticketItems = [];
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();
            }
        });
    }

    // ===== FOGADÁSI ELŐZMÉNYEK BETÖLTÉSE =====
    function loadBettingHistory() {
        fetch('../../backend/ApiRequest/get_betting_history.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    bettingHistory = data.history || [];
                    renderHistory();
                }
            })
            .catch(e => console.error('Előzmények hiba:', e));
    }

    // ===== FOGADÁSI ELŐZMÉNYEK RENDERELÉS =====
    function renderHistory() {
        const container = document.getElementById('elozmeny-items');
        const empty = document.getElementById('elozmeny-empty');

        if (!container || !empty) return;

        if (bettingHistory.length === 0) {
            empty.style.display = 'flex';
            container.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        container.style.display = 'flex';
        container.innerHTML = '';

        bettingHistory.forEach(ticket => {
            const statusText = ticket.status === 'OPEN' ? '⏳ Függőben' : 
                              ticket.status === 'WON' ? '✅ Nyertes' : 
                              ticket.status === 'LOST' ? '❌ Vesztes' : '❓ Ismeretlen';
            
            const statusClass = ticket.status.toLowerCase();
            
            let itemsHtml = '';
            if (ticket.items && ticket.items.length > 0) {
                ticket.items.forEach(item => {
                    itemsHtml += `
                        <div class="elozmeny-item-entry">
                            <div class="elozmeny-match">${escapeHtml(item.homeTeam)} vs ${escapeHtml(item.awayTeam)}</div>
                            <div class="elozmeny-market">${escapeHtml(item.market)}</div>
                            <div class="elozmeny-pick">${escapeHtml(item.pick)} @ ${parseFloat(item.odds).toFixed(2)}</div>
                        </div>
                    `;
                });
            }

            const el = document.createElement('div');
            el.className = 'elozmeny-item';
            el.innerHTML = `
                <div class="elozmeny-header">
                    <span class="elozmeny-date">${new Date(ticket.created_at).toLocaleString('hu-HU')}</span>
                    <span class="elozmeny-status ${statusClass}">${statusText}</span>
                </div>
                <div class="elozmeny-items-list">${itemsHtml}</div>
                <div class="elozmeny-summary">
                    <span><strong>Tét:</strong> ${parseFloat(ticket.stake).toLocaleString('hu-HU')} Ft</span>
                    <span><strong>Odds:</strong> ${parseFloat(ticket.total_odds).toFixed(3)}</span>
                    <span><strong>Potenciális:</strong> ${parseFloat(ticket.potential_win).toLocaleString('hu-HU')} Ft</span>
                </div>
            `;
            container.appendChild(el);
        });
    }

    // ===== ODDS GOMBOK FRISSÍTÉSE =====
    window.refreshAllOddsButtons = function() {
        document.querySelectorAll('.selection-btn').forEach(btn => {
            const home = btn.getAttribute('data-home');
            const away = btn.getAttribute('data-away');
            const pick = btn.getAttribute('data-pick');
            const market = btn.getAttribute('data-market');

            if (!home || !away || !pick || !market) return;

            const state = window.BetslipLogic.getButtonState(home, away, pick, market);
            
            btn.classList.remove('active', 'disabled');
            btn.removeAttribute('disabled');

            if (state === 'active') {
                btn.classList.add('active');
            } else if (state === 'disabled') {
                btn.classList.add('disabled');
                btn.setAttribute('disabled', 'disabled');
            }
        });
    };

    // ===== TAB VÁLTÁS =====
    document.querySelectorAll('.betslip-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.betslip-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.betslip-content').forEach(c => c.classList.remove('active'));
            
            tab.classList.add('active');
            const target = tab.getAttribute('data-tab');
            document.getElementById('betslip-' + target)?.classList.add('active');
            
            if (target === 'elozmeny') {
                loadBettingHistory();
            }
        });
    });

    // ===== UTILITY =====
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== INICIALIZÁLÁS =====
    loadFromStorage();
    renderTicket();
    loadBettingHistory();
    refreshAllOddsButtons();

    console.log('[BETSLIP] Kész!');
});
