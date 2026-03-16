document.addEventListener('DOMContentLoaded', function() {
    console.log('[BETSLIP INIT] Starting betslip initialization...');

    // ===== GLOBÁLIS ADATOK =====
    let betslipItems = [];
    let betHistory = [];
    let checkBetsTimer = null;

    // ===== ADATOK BETÖLTÉSE LOCALSTORAGE-BŐL =====
    function loadFromStorage() {
        betslipItems = JSON.parse(localStorage.getItem('betslip') || '[]');
        betHistory = JSON.parse(localStorage.getItem('betHistory') || '[]');
        
        if (!localStorage.getItem('betHistoryCleared_v2')) {
            localStorage.removeItem('betHistory');
            localStorage.setItem('betHistoryCleared_v2', '1');
            betHistory = [];
        }
        
        console.log('[BETSLIP] Loaded from storage:', betslipItems.length, 'items,', betHistory.length, 'bets');
    }

    // ===== ADATOK MENTÉSE LOCALSTORAGE-BE =====
    function saveBetslip() {
        localStorage.setItem('betslip', JSON.stringify(betslipItems));
        console.log('[BETSLIP] Saved:', betslipItems.length, 'items');
    }

    function saveBetHistory() {
        localStorage.setItem('betHistory', JSON.stringify(betHistory));
        console.log('[BETSLIP] Saved history:', betHistory.length, 'bets');
    }

    // ===== TAB VÁLTÁS =====
    const tabs = document.querySelectorAll('.betslip-tab');
    const contents = document.querySelectorAll('.betslip-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.getAttribute('data-tab');
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            const contentEl = document.getElementById('betslip-' + target);
            if (contentEl) contentEl.classList.add('active');
        });
    });

    // ===== BETSLIP RENDERELÉS =====
    function renderBetslip() {
        const itemsContainer = document.getElementById('betslip-items');
        const emptyState = document.querySelector('.betslip-empty');
        const footer = document.getElementById('betslip-footer');

        if (!itemsContainer || !emptyState || !footer) {
            console.warn('[BETSLIP] HTML elemek hiányoznak!');
            return;
        }

        if (betslipItems.length === 0) {
            emptyState.style.display = 'flex';
            itemsContainer.style.display = 'none';
            footer.style.display = 'none';
            return;
        }

        emptyState.style.display = 'none';
        itemsContainer.style.display = 'flex';
        footer.style.display = 'block';

        itemsContainer.innerHTML = '';
        let totalOdds = 1;

        betslipItems.forEach((item, index) => {
            totalOdds *= item.odds;
            const el = document.createElement('div');
            el.classList.add('betslip-item');
            el.innerHTML = `
                <div class="betslip-item-header">
                    <span class="betslip-item-match">${item.homeTeam} vs ${item.awayTeam}</span>
                    <button class="betslip-item-remove" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="betslip-item-market">${item.market || ''}</div>
                <div class="betslip-item-pick">${item.pick}</div>
                <div class="betslip-item-odds">${item.odds.toFixed(2)}</div>
            `;
            itemsContainer.appendChild(el);
        });

        document.querySelectorAll('.betslip-item-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-index'));
                const removed = betslipItems[idx];
                betslipItems.splice(idx, 1);
                saveBetslip();
                renderBetslip();
                refreshAllOddsButtons();
            });
        });

        document.getElementById('betslip-count').textContent = betslipItems.length;
        document.getElementById('betslip-total-odds').textContent = totalOdds.toFixed(2);
        updatePotentialWin(totalOdds);
    }

    function updatePotentialWin(totalOdds) {
        const stakeInput = document.getElementById('stake-input');
        const stake = parseFloat(stakeInput.value) || 0;
        const potentialWin = stake * totalOdds;
        document.getElementById('betslip-potential-win').textContent =
            Math.round(potentialWin).toLocaleString('hu-HU') + ' Ft';
    }

    const stakeInput = document.getElementById('stake-input');
    if (stakeInput) {
        stakeInput.addEventListener('input', () => {
            let totalOdds = 1;
            betslipItems.forEach(item => totalOdds *= item.odds);
            updatePotentialWin(totalOdds);
        });
    }

    const submitBtn = document.getElementById('betslip-submit');
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (betslipItems.length === 0) return;
            const stake = parseFloat(stakeInput.value) || 0;
            if (stake < 100) {
                alert('A minimum tét 100 Ft!');
                return;
            }
            let totalOdds = 1;
            betslipItems.forEach(item => totalOdds *= item.odds);

            var betItems = betslipItems.map(function(item) {
                return {
                    homeTeam: item.homeTeam,
                    awayTeam: item.awayTeam,
                    pick: item.pick,
                    odds: item.odds,
                    market: item.market || '',
                    matchId: item.matchId || 0,
                    status: 'pending'
                };
            });

            const bet = {
                id: Date.now(),
                date: new Date().toLocaleString('hu-HU'),
                items: betItems,
                stake: stake,
                totalOdds: totalOdds,
                potentialWin: Math.round(stake * totalOdds),
                status: 'pending'
            };

            betHistory.unshift(bet);
            saveBetHistory();
            betslipItems = [];
            saveBetslip();
            renderBetslip();
            renderNaplo();
            refreshAllOddsButtons();

            alert('Fogadás sikeresen leadva!');
        });
    }

    // ===== NAPLÓ RENDERELÉS =====
    function renderNaplo() {
        const naploItems = document.getElementById('naplo-items');
        const naploEmpty = document.getElementById('naplo-empty');
        if (!naploItems || !naploEmpty) return;

        if (betHistory.length === 0) {
            naploEmpty.style.display = 'flex';
            naploItems.style.display = 'none';
            return;
        }
        naploEmpty.style.display = 'none';
        naploItems.style.display = 'flex';
        naploItems.innerHTML = '';
        betHistory.forEach(function(bet) {
            var statusClass = bet.status;
            var statusText = 'Függőben';
            var statusIcon = '⏳';
            if (bet.status === 'won') {
                statusText = 'Nyertes';
                statusIcon = '✅';
            } else if (bet.status === 'lost') {
                statusText = 'Vesztes';
                statusIcon = '❌';
            }

            var itemsHtml = '';
            if (bet.items && bet.items.length > 0) {
                bet.items.forEach(function(item) {
                    var itemStatusClass = item.status || 'pending';
                    var itemStatusIcon = '⏳';
                    if (item.status === 'won') itemStatusIcon = '✅';
                    else if (item.status === 'lost') itemStatusIcon = '❌';

                    itemsHtml += '<div class="naplo-bet-item ' + itemStatusClass + '">' +
                        '<span class="naplo-bet-icon">' + itemStatusIcon + '</span>' +
                        '<div class="naplo-bet-details">' +
                            '<span class="naplo-bet-match">' + item.homeTeam + ' vs ' + item.awayTeam + '</span>' +
                            '<span class="naplo-bet-pick">' + (item.market || '') + ' → <strong>' + item.pick + '</strong> @ ' + item.odds.toFixed(2) + '</span>' +
                        '</div>' +
                    '</div>';
                });
            }

            var el = document.createElement('div');
            el.classList.add('naplo-item', statusClass);
            el.innerHTML =
                '<div class="naplo-item-header">' +
                    '<span class="naplo-item-date">' + bet.date + '</span>' +
                    '<span class="naplo-item-status ' + statusClass + '">' + statusIcon + ' ' + statusText + '</span>' +
                '</div>' +
                '<div class="naplo-bet-items">' + itemsHtml + '</div>' +
                '<div class="naplo-item-details">' +
                    '<span>Tét: ' + bet.stake.toLocaleString('hu-HU') + ' Ft</span>' +
                    '<span>Odds: ' + bet.totalOdds.toFixed(2) + '</span>' +
                    '<span>Lehetséges: ' + bet.potentialWin.toLocaleString('hu-HU') + ' Ft</span>' +
                '</div>';
            naploItems.appendChild(el);
        });
    }

    // ===== GLOBÁLIS FÜGGVÉNYEK =====
    window.addToBetslip = function(homeTeam, awayTeam, pick, odds, market, matchId) {
        console.log('[BETSLIP] addToBetslip:', {homeTeam, awayTeam, pick, odds, market});

        // Duplikátum ellenőrzés
        var exists = betslipItems.some(function(i) {
            return i.homeTeam === homeTeam && i.awayTeam === awayTeam && 
                   i.pick === pick && i.market === market;
        });
        if (exists) {
            console.log('[BETSLIP] Already in slip');
            return;
        }

        // 1X2 piacon: nincs piaca zárolás, csak halványuláson alapuló
        // Más piacokon: piaca zárolás van
        var is1X2Market = is1X2OrMatchWinner(market);
        
        if (!is1X2Market) {
            var hasOtherInMarket = betslipItems.some(function(item) {
                return item.homeTeam === homeTeam && 
                       item.awayTeam === awayTeam && 
                       item.market === market;
            });

            if (hasOtherInMarket) {
                console.log('[BETSLIP] Market already locked (not 1X2)');
                return;
            }
        }

        // Hozzáadás
        var newItem = {
            homeTeam: homeTeam,
            awayTeam: awayTeam,
            pick: pick,
            odds: odds,
            market: market || '',
            matchId: matchId || 0
        };

        betslipItems.push(newItem);
        saveBetslip();
        renderBetslip();
        refreshAllOddsButtons();
        
        console.log('[BETSLIP] Added! Now:', betslipItems.length);
    };

    window.removeFromBetslip = function(homeTeam, awayTeam, pick, market) {
        console.log('[BETSLIP] removeFromBetslip:', {homeTeam, awayTeam, pick, market});

        betslipItems = betslipItems.filter(function(item) {
            return !(item.homeTeam === homeTeam && item.awayTeam === awayTeam && 
                     item.pick === pick && item.market === market);
        });
        
        saveBetslip();
        renderBetslip();
        refreshAllOddsButtons();

        console.log('[BETSLIP] Removed! Now:', betslipItems.length);
    };

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

    window.refreshAllOddsButtons = function() {
        console.log('[BETSLIP] refreshAllOddsButtons');
        
        var betslip = JSON.parse(localStorage.getItem('betslip') || '[]');
        
        document.querySelectorAll('.selection-btn').forEach(function(btn) {
            var home = btn.getAttribute('data-home');
            var away = btn.getAttribute('data-away');
            var pick = btn.getAttribute('data-pick');
            var market = btn.getAttribute('data-market');

            if (!home || !away || !pick || !market) return;

            var inSlip = betslip.some(function(item) {
                return item.homeTeam === home && item.awayTeam === away && item.pick === pick && item.market === market;
            });

            var hasOtherInMarket = betslip.some(function(item) {
                return item.homeTeam === home && item.awayTeam === away && item.market === market && item.pick !== pick;
            });

            btn.classList.remove('active', 'market-locked', 'hidden-other');

            if (inSlip) {
                btn.classList.add('active');
            } else if (hasOtherInMarket) {
                if (is1X2OrMatchWinner(market)) {
                    btn.classList.add('hidden-other');
                } else {
                    btn.classList.add('market-locked');
                }
            }
        });
    };

    // ===== BETSLIP ELLENŐRZÉS (BACKEND) =====
    function checkBetResults() {
        var pendingBets = betHistory.filter(function(b) { return b.status === 'pending'; });
        if (pendingBets.length === 0) return;

        var checkableBets = pendingBets.filter(function(b) {
            return b.items && b.items.some(function(item) {
                return item.matchId && item.matchId > 0;
            });
        });
        if (checkableBets.length === 0) return;

        fetch('../../backend/ApiRequest/check_bets.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(checkableBets)
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.status !== 'ok' || !data.bets) return;

            var changed = false;
            data.bets.forEach(function(updatedBet) {
                for (var i = 0; i < betHistory.length; i++) {
                    if (betHistory[i].id === updatedBet.id) {
                        if (betHistory[i].status !== updatedBet.status ||
                            JSON.stringify(betHistory[i].items) !== JSON.stringify(updatedBet.items)) {
                            betHistory[i].status = updatedBet.status;
                            betHistory[i].items = updatedBet.items;
                            changed = true;
                        }
                        break;
                    }
                }
            });

            if (changed) {
                saveBetHistory();
                renderNaplo();
            }
        })
        .catch(function(err) {
            console.error('Check bets error:', err);
        });
    }

    function startBetCheck() {
        checkBetResults();
        checkBetsTimer = setInterval(checkBetResults, 30000);
    }

    // ===== INICIALIZÁLÁS =====
    loadFromStorage();
    renderBetslip();
    renderNaplo();
    refreshAllOddsButtons();
    startBetCheck();

    console.log('[BETSLIP INIT] Complete!');
});
