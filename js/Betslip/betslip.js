document.addEventListener('DOMContentLoaded', function() {
    console.log('[TICKET INIT] Starting...');

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
        ticketItems = JSON.parse(localStorage.getItem('ticket') || '[]');
        bettingHistory = JSON.parse(localStorage.getItem('bettingHistory') || '[]');
        console.log('[TICKET] Loaded:', ticketItems.length, 'items,', bettingHistory.length, 'history');
    }

    function saveToStorage() {
        localStorage.setItem('ticket', JSON.stringify(ticketItems));
        localStorage.setItem('bettingHistory', JSON.stringify(bettingHistory));
    }

    // ===== TOGGLE: HOZZÁADÁS / ELTÁVOLÍTÁS =====
    window.toggleOdds = function(homeTeam, awayTeam, pick, odds, market, matchId) {
        console.log('[TICKET] toggleOdds:', {homeTeam, awayTeam, pick, odds, market});

        var existingIndex = ticketItems.findIndex(function(item) {
            return item.homeTeam === homeTeam && 
                   item.awayTeam === awayTeam && 
                   item.pick === pick && 
                   item.market === market;
        });

        if (existingIndex >= 0) {
            ticketItems.splice(existingIndex, 1);
            console.log('[TICKET] Removed');
        } else {
            if (window.BetslipLogic.hasSelectionInMarket(homeTeam, awayTeam, market)) {
                console.log('[TICKET] Market already has selection');
                return;
            }

            if (window.BetslipLogic.isCorrectScoreMarket(market) && 
                window.BetslipLogic.isConflictingScore(pick, homeTeam, awayTeam)) {
                console.log('[TICKET] Conflicting score');
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
            console.log('[TICKET] Added');
        }

        saveToStorage();
        renderTicket();
        refreshAllOddsButtons();
    };

    // ===== TICKET RENDERELÉS =====
    function renderTicket() {
        const container = document.getElementById('ticket-items');
        const empty = document.querySelector('.ticket-empty');
        const footer = document.getElementById('ticket-footer');

        if (!container || !empty || !footer) {
            console.warn('[TICKET] HTML elemek hiányoznak');
            return;
        }

        if (ticketItems.length === 0) {
            empty.style.display = 'flex';
            container.style.display = 'none';
            footer.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        container.style.display = 'flex';
        footer.style.display = 'block';

        container.innerHTML = '';
        let totalOdds = 1;

        ticketItems.forEach((item, idx) => {
            totalOdds *= item.odds;
            const el = document.createElement('div');
            el.classList.add('ticket-item');
            el.innerHTML = `
                <div class="ticket-item-header">
                    <span>${item.homeTeam} vs ${item.awayTeam}</span>
                    <button class="ticket-remove" data-index="${idx}">×</button>
                </div>
                <div class="ticket-item-market">${item.market}</div>
                <div class="ticket-item-pick">${item.pick}</div>
                <div class="ticket-item-odds">${item.odds.toFixed(2)}</div>
            `;
            container.appendChild(el);
        });

        document.querySelectorAll('.ticket-remove').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-index'));
                ticketItems.splice(idx, 1);
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();
            });
        });

        document.getElementById('ticket-count').textContent = ticketItems.length;
        document.getElementById('ticket-total-odds').textContent = totalOdds.toFixed(2);
        updatePotentialWin(totalOdds);
    }

    function updatePotentialWin(totalOdds) {
        const stakeInput = document.getElementById('stake-input');
        const stake = parseFloat(stakeInput.value) || 0;
        const win = Math.round(stake * totalOdds);
        document.getElementById('ticket-potential-win').textContent = 
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
    const submitBtn = document.getElementById('ticket-submit');
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

            const ticket = {
                id: Date.now(),
                createdAt: new Date().toLocaleString('hu-HU'),
                items: ticketItems.map(item => ({...item})),
                stake: stake,
                totalOdds: totalOdds,
                potentialWin: Math.round(stake * totalOdds),
                status: 'pending'
            };

            bettingHistory.unshift(ticket);
            saveToStorage();

            // Jóváhagyás modal megjelenítése
            showConfirmModal(ticket);

            ticketItems = [];
            renderTicket();
            renderHistory();
            refreshAllOddsButtons();
        });
    }

    // ===== CONFIRM MODAL =====
    function showConfirmModal(ticket) {
        const overlay = document.getElementById('ticket-confirm-overlay');
        const itemsDiv = document.getElementById('ticket-confirm-items');
        const confirmStake = document.getElementById('ticket-confirm-stake');
        const confirmOdds = document.getElementById('ticket-confirm-odds');
        const confirmWin = document.getElementById('ticket-confirm-win');
        const closeBtn = document.getElementById('ticket-confirm-close');
        const okBtn = document.getElementById('ticket-confirm-ok');

        if (!overlay) return;

        itemsDiv.innerHTML = '';
        ticket.items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'ticket-confirm-item';
            div.innerHTML = `
                <div class="ticket-confirm-item-match">${item.homeTeam} vs ${item.awayTeam}</div>
                <div class="ticket-confirm-item-pick">${item.pick} @ ${item.odds.toFixed(2)}</div>
            `;
            itemsDiv.appendChild(div);
        });

        confirmStake.textContent = ticket.stake.toLocaleString('hu-HU') + ' Ft';
        confirmOdds.textContent = ticket.totalOdds.toFixed(2);
        confirmWin.textContent = ticket.potentialWin.toLocaleString('hu-HU') + ' Ft';

        overlay.classList.add('active');

        closeBtn.onclick = () => overlay.classList.remove('active');
        okBtn.onclick = () => overlay.classList.remove('active');
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
            const statusText = ticket.status === 'pending' ? '⏳ Függőben' : 
                              ticket.status === 'won' ? '✅ Nyertes' : 
                              '❌ Vesztes';
            
            const statusClass = ticket.status;
            
            let itemsHtml = '';
            if (ticket.items && ticket.items.length > 0) {
                ticket.items.forEach(item => {
                    itemsHtml += `
                        <div class="elozmeny-item-entry">
                            <div class="elozmeny-match">${item.homeTeam} vs ${item.awayTeam}</div>
                            <div class="elozmeny-market">${item.market}</div>
                            <div class="elozmeny-pick">${item.pick} @ ${item.odds.toFixed(2)}</div>
                        </div>
                    `;
                });
            }

            const el = document.createElement('div');
            el.classList.add('elozmeny-item');
            el.innerHTML = `
                <div class="elozmeny-header">
                    <span class="elozmeny-date">${ticket.createdAt}</span>
                    <span class="elozmeny-status ${statusClass}">${statusText}</span>
                </div>
                <div class="elozmeny-items-list">${itemsHtml}</div>
                <div class="elozmeny-summary">
                    <span><strong>Tét:</strong> ${ticket.stake.toLocaleString('hu-HU')} Ft</span>
                    <span><strong>Odds:</strong> ${ticket.totalOdds.toFixed(2)}</span>
                    <span><strong>Lehetséges:</strong> ${ticket.potentialWin.toLocaleString('hu-HU')} Ft</span>
                </div>
            `;
            container.appendChild(el);
        });
    }

    // ===== TAB VÁLTÁS =====
    const tabs = document.querySelectorAll('.ticket-tab');
    const contents = document.querySelectorAll('.ticket-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.remove('active'));
            tab.classList.add('active');
            const target = tab.getAttribute('data-tab');
            document.getElementById('ticket-' + target)?.classList.add('active');
        });
    });

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

    // ===== FOGADÁSI ELŐZMÉNYEK AUTOMATIKUS FRISSÍTÉSE (30 perc) =====
    function checkAndUpdateHistory() {
        console.log('[TICKET] Checking history status...');
        
        let changed = false;

        bettingHistory.forEach(ticket => {
            if (ticket.status !== 'pending') return;

            let allFinished = true;
            let anyLost = false;

            ticket.items.forEach(item => {
                if (!item.matchId) return;

                fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + item.matchId)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.match) return;

                        const score = data.match.score;
                        const isLive = data.match.isLive;
                        const parts = score.split(' - ');
                        const home = parseInt(parts[0]);
                        const away = parseInt(parts[1]);

                        if (isLive || isNaN(home) || isNaN(away)) {
                            allFinished = false;
                            return;
                        }

                        const won = checkPickWon(item.pick, item.market, home, away);
                        if (!won) anyLost = true;
                    })
                    .catch(e => {
                        console.error('[TICKET] API error:', e);
                        allFinished = false;
                    });
            });

            if (anyLost) {
                ticket.status = 'lost';
                changed = true;
            } else if (allFinished) {
                ticket.status = 'won';
                changed = true;
            }
        });

        if (changed) {
            saveToStorage();
            renderHistory();
        }
    }

    function checkPickWon(pick, market, homeGoals, awayGoals) {
        const pickLower = pick.toLowerCase();
        const marketLower = market.toLowerCase();

        if (marketLower.includes('1x2') || marketLower.includes('winner')) {
            if (pickLower === '1' || pickLower === 'home') return homeGoals > awayGoals;
            if (pickLower === '2' || pickLower === 'away') return awayGoals > homeGoals;
            if (pickLower === 'x' || pickLower === 'draw') return homeGoals === awayGoals;
        }

        if (marketLower.includes('over') || marketLower.includes('under')) {
            const total = homeGoals + awayGoals;
            const match = marketLower.match(/\((\d+\.?\d*)\)/);
            const line = match ? parseFloat(match[1]) : 0;
            if (line > 0) {
                if (pickLower === 'over') return total > line;
                if (pickLower === 'under') return total < line;
            }
        }

        if (marketLower.includes('correct score') || marketLower.includes('pontos végeredmény')) {
            return pick === homeGoals + ':' + awayGoals;
        }

        return false;
    }

    function startHistoryCheck() {
        checkAndUpdateHistory();
        historyCheckTimer = setInterval(checkAndUpdateHistory, 30 * 60 * 1000);
    }

    // ===== INICIALIZÁLÁS =====
    loadFromStorage();
    renderTicket();
    renderHistory();
    refreshAllOddsButtons();
    startHistoryCheck();

    console.log('[TICKET INIT] Complete!');
});

/**
 * BETSLIP.JS
 * Szelvény kezelés, AJAX API hívások, automatikus frissítés
 */

// ===== GLOBÁLIS STÁTUSZ =====
let ticketData = {
    items: [],
    stake: 0
};

let elozmenyek = [];
let autoRefreshInterval = null;
const AUTO_REFRESH_INTERVAL = 10 * 60 * 1000; // 10 perc milliszekundumban

// ===== INICIALIZÁCIÓ =====
document.addEventListener('DOMContentLoaded', function() {
    loadElozmenyFromStorage();
    startAutoRefresh();
});

// ===== AUTO REFRESH - 10 PERCENKÉNT ELLENŐRZI =====
/**
 * Elindítja az automatikus frissítést
 * 10 percenként API-ból kéri le az aktuális szelvényadatokat
 */
function startAutoRefresh() {
    // Azonnal ellenőrizze az oldal betöltésekor
    checkAndUpdateBets();
    
    // Majd 10 percenként
    autoRefreshInterval = setInterval(() => {
        checkAndUpdateBets();
    }, AUTO_REFRESH_INTERVAL);
    
    console.log('✅ Auto-refresh elindítva (10 perc)');
}

/**
 * API hívás a szelvénytételek státuszának ellenőrzéséhez
 * Csak a "PENDING" (nyitott) szelvényeket ellenőrzi
 */
function checkAndUpdateBets() {
    // Csak azokat az előzményeket küldünk el, amelyek még "pending" státuszban vannak
    const pendingBets = elozmenyek.filter(bet => bet.status === 'pending');
    
    if (pendingBets.length === 0) {
        console.log('ℹ️ Nincs ellenőrizendő szelvény (mind már lezárult)');
        return;
    }
    
    // API payload összeállítása
    const payload = pendingBets.map(bet => ({
        id: bet.id,
        status: bet.status,
        items: bet.items.map(item => ({
            id: item.id,
            matchId: item.matchId,
            pick: item.pick,
            market: item.market,
            status: item.status
        }))
    }));
    
    // AJAX POST kérés az API-hoz
    fetch('/BetMatchBonus/backend/ApiRequest/check_bets.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok' && data.bets) {
            // Frissítjük az előzményeket az API válasza alapján
            updateBetsFromAPI(data.bets);
            renderElozmeny();
            console.log('✅ Szelvények frissítve (API)');
        }
    })
    .catch(error => {
        console.error('❌ API hiba:', error);
    });
}

/**
 * Frissíti az előzményadatokat az API válasza alapján
 */
function updateBetsFromAPI(apiBets) {
    apiBets.forEach(apiBet => {
        const localBet = elozmenyek.find(b => b.id === apiBet.id);
        
        if (localBet) {
            // Frissítjük a szelvény státuszát
            localBet.status = apiBet.status;
            
            // Frissítjük az egyes tételek státuszát
            apiBet.items.forEach(apiItem => {
                const localItem = localBet.items.find(i => i.id === apiItem.id);
                if (localItem) {
                    localItem.status = apiItem.status;
                    if (apiItem.finalScore) {
                        localItem.finalScore = apiItem.finalScore;
                    }
                }
            });
        }
    });
    
    saveElozmenytToStorage();
}

// ===== SELECTION KATTINTÁS KEZELÉS =====
document.addEventListener('click', function(e) {
    if (e.target.closest('.selection-btn')) {
        const btn = e.target.closest('.selection-btn');
        const matchId = parseInt(btn.dataset.matchId);
        const oddValue = parseFloat(btn.dataset.odd);
        const label = btn.dataset.label;
        const market = btn.dataset.market;
        const matchName = btn.dataset.matchName;
        
        addToTicket(matchId, oddValue, label, market, matchName);
    }
});

/**
 * Szelvénytételhez adás
 */
function addToTicket(matchId, odd, pick, market, matchName) {
    const existingItem = ticketData.items.find(item => item.matchId === matchId);
    
    if (existingItem) {
        removeFromTicket(matchId);
        return;
    }
    
    const item = {
        id: Date.now(),
        matchId: matchId,
        odd: odd,
        pick: pick,
        market: market,
        matchName: matchName
    };
    
    ticketData.items.push(item);
    renderTicket();
    updateSelectionStates();
}

/**
 * Szelvénytételből eltávolítás
 */
function removeFromTicket(matchId) {
    ticketData.items = ticketData.items.filter(item => item.matchId !== matchId);
    renderTicket();
    updateSelectionStates();
}

/**
 * Selection gombok állapotának frissítése (selected/disabled)
 */
function updateSelectionStates() {
    document.querySelectorAll('.selection-btn').forEach(btn => {
        const matchId = parseInt(btn.dataset.matchId);
        const isSelected = ticketData.items.some(item => item.matchId === matchId);
        
        btn.classList.toggle('selected', isSelected);
    });
}

/**
 * SZELVÉNY RENDER - Számol és megjelenít
 */
function renderTicket() {
    const ticketItems = document.getElementById('ticket-items');
    
    if (!ticketItems) return;
    
    if (ticketData.items.length === 0) {
        ticketItems.innerHTML = `
            <div class="ticket-empty">
                <div class="ticket-empty-icon">🎫</div>
                <p>Üres szelvény</p>
                <span>Válassz meccseket az oldal szelvényrészéből</span>
            </div>
        `;
        renderTicketFooter();
        return;
    }
    
    let html = '<div class="ticket-items">';
    let totalOdds = 1;
    
    ticketData.items.forEach(item => {
        totalOdds *= item.odd;
        html += `
            <div class="ticket-item">
                <div class="ticket-item-header">
                    <span>${escapeHtml(item.matchName)}</span>
                    <button class="ticket-remove" onclick="removeFromTicket(${item.matchId})" title="Eltávolítás">×</button>
                </div>
                <div class="ticket-item-market">${escapeHtml(item.market)}</div>
                <div class="ticket-item-pick">${escapeHtml(item.pick)}</div>
                <div class="ticket-item-odds">Odds: <strong>${item.odd.toFixed(2)}</strong></div>
            </div>
        `;
    });
    
    html += '</div>';
    ticketItems.innerHTML = html;
    
    // Tároljuk a teljes odds-ot
    ticketData.totalOdds = parseFloat(totalOdds.toFixed(3));
    
    renderTicketFooter();
}

/**
 * SZELVÉNY FOOTER - Tét, nyeremény, küldés gomb
 */
function renderTicketFooter() {
    const footer = document.getElementById('ticket-footer');
    if (!footer) return;
    
    const stake = parseFloat(document.getElementById('stake-input')?.value) || 0;
    const potentialWin = (stake * ticketData.totalOdds).toFixed(2);
    
    let html = `
        <div class="ticket-stake">
            <label>Téted (HUF)</label>
            <input 
                type="number" 
                id="stake-input" 
                class="stake-input" 
                placeholder="0.00" 
                min="0" 
                step="100" 
                value="${stake}"
            >
        </div>
        
        <div class="ticket-summary">
            <div class="ticket-row">
                <span>Teljes odds:</span>
                <span>${ticketData.totalOdds.toFixed(3)}</span>
            </div>
            <div class="ticket-row">
                <span>Lehetséges nyeremény:</span>
                <span>${potentialWin} HUF</span>
            </div>
            <div class="ticket-row ticket-row-highlight">
                <span>Téted:</span>
                <span>${stake.toFixed(2)} HUF</span>
            </div>
        </div>
    `;
    
    if (ticketData.items.length > 0) {
        html += `
            <button class="ticket-submit-btn" onclick="showConfirmModal()">
                <i class="fas fa-check"></i> Szelvény leadása
            </button>
        `;
    }
    
    footer.innerHTML = html;
}

/**
 * CONFIRM MODAL - Végső megerősítés
 */
function showConfirmModal() {
    const stake = parseFloat(document.getElementById('stake-input')?.value) || 0;
    
    if (stake <= 0) {
        alert('Kérlek adj meg egy tétösszeg!');
        return;
    }
    
    const modal = document.getElementById('ticket-confirm-modal-container');
    if (!modal) return;
    
    const potentialWin = (stake * ticketData.totalOdds).toFixed(2);
    
    let itemsHtml = '';
    ticketData.items.forEach(item => {
        itemsHtml += `
            <div class="ticket-confirm-item">
                <div class="ticket-confirm-item-match">${escapeHtml(item.matchName)}</div>
                <div class="ticket-confirm-item-pick">${escapeHtml(item.pick)} @ ${item.odd.toFixed(2)}</div>
            </div>
        `;
    });
    
    modal.innerHTML = `
        <div class="ticket-confirm-overlay active">
            <div class="ticket-confirm-modal">
                <div class="ticket-confirm-header">
                    <span class="ticket-confirm-close" onclick="closeConfirmModal()">×</span>
                    <span class="ticket-confirm-icon">✓</span>
                    <h3>Szelvény megerősítése</h3>
                </div>
                
                <div class="ticket-confirm-body">
                    <div class="ticket-confirm-items">
                        ${itemsHtml}
                    </div>
                    
                    <div class="ticket-confirm-summary">
                        <div class="ticket-confirm-row">
                            <span>Teljes odds:</span>
                            <span>${ticketData.totalOdds.toFixed(3)}</span>
                        </div>
                        <div class="ticket-confirm-row">
                            <span>Téted:</span>
                            <span>${stake.toFixed(2)} HUF</span>
                        </div>
                        <div class="ticket-confirm-row highlight">
                            <span>Lehetséges nyeremény:</span>
                            <span>${potentialWin} HUF</span>
                        </div>
                    </div>
                </div>
                
                <div class="ticket-confirm-footer">
                    <button class="ticket-confirm-ok-btn" onclick="submitTicket()">
                        Szelvény leadása
                    </button>
                </div>
            </div>
        </div>
    `;
}

function closeConfirmModal() {
    const modal = document.getElementById('ticket-confirm-modal-container');
    if (modal) modal.innerHTML = '';
}

/**
 * SZELVÉNY LEADÁS - API-hoz küldés
 */
function submitTicket() {
    const stake = parseFloat(document.getElementById('stake-input')?.value) || 0;
    
    if (!ticketData.items.length || stake <= 0) {
        alert('Érvénytelen szelvény!');
        return;
    }
    
    const payload = {
        stake: stake,
        items: ticketData.items.map(item => ({
            matchId: item.matchId,
            pick: item.pick,
            market: item.market
        }))
    };
    
    fetch('/BetMatchBonus/backend/ApiRequest/submit_ticket.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            // Szelvény sikeresen leadva
            const newBet = {
                id: data.ticket_id || Date.now(),
                stake: stake,
                totalOdds: ticketData.totalOdds,
                potentialWin: stake * ticketData.totalOdds,
                status: 'pending',
                items: ticketData.items.map(item => ({
                    id: Date.now() + Math.random(),
                    matchId: item.matchId,
                    pick: item.pick,
                    market: item.market,
                    status: 'pending'
                })),
                createdAt: new Date().toLocaleString('hu-HU')
            };
            
            elozmenyek.unshift(newBet);
            saveElozmenytToStorage();
            
            ticketData.items = [];
            ticketData.totalOdds = 1;
            ticketData.stake = 0;
            
            closeConfirmModal();
            renderTicket();
            renderElozmeny();
            
            alert('✅ Szelvény sikeresen leadva!');
        } else {
            alert('❌ Hiba: ' + (data.message || 'Ismeretlen hiba'));
        }
    })
    .catch(error => {
        console.error('Hiba:', error);
        alert('❌ Szerverhiba!');
    });
}

// ===== ELŐZMÉNYEK RENDER =====
/**
 * Fogadási előzmények megjelenítése
 * Színes státusz badgek: PENDING (sárga), WON (zöld), LOST (piros)
 */
function renderElozmeny() {
    const container = document.getElementById('elozmeny-items');
    if (!container) return;
    
    if (elozmenyek.length === 0) {
        container.innerHTML = `
            <div class="elozmeny-empty">
                <div class="elozmeny-empty-icon">📊</div>
                <p>Nincs előzmény</p>
                <span>Az első szelvényed itt jelenik meg</span>
            </div>
        `;
        return;
    }
    
    let html = '<div class="elozmeny-items">';
    
    elozmenyek.forEach(bet => {
        const statusClass = bet.status || 'pending';
        const statusText = {
            'pending': '⏳ Folyamatban',
            'won': '✅ Nyert',
            'lost': '❌ Veszített'
        }[statusClass] || 'Ismeretlen';
        
        let itemsHtml = '<div class="elozmeny-items-list">';
        
        bet.items.forEach(item => {
            const itemStatus = item.status || 'pending';
            const itemStatusIcon = {
                'pending': '⏳',
                'won': '✅',
                'lost': '❌'
            }[itemStatus] || '?';
            
            itemsHtml += `
                <div class="elozmeny-item-entry">
                    <div class="elozmeny-match">${itemStatusIcon} ${escapeHtml(item.matchName || 'Ismeretlen meccs')}</div>
                    <div class="elozmeny-market">${escapeHtml(item.market || '-')}</div>
                    <div class="elozmeny-pick">${escapeHtml(item.pick || '-')}</div>
                    ${item.finalScore ? `<div style="color: #999; font-size: 11px; margin-top: 4px;">Végeredmény: ${item.finalScore}</div>` : ''}
                </div>
            `;
        });
        
        itemsHtml += '</div>';
        
        html += `
            <div class="elozmeny-item">
                <div class="elozmeny-header">
                    <span class="elozmeny-date">${bet.createdAt || 'N/A'}</span>
                    <span class="elozmeny-status ${statusClass}">${statusText}</span>
                </div>
                ${itemsHtml}
                <div class="elozmeny-summary">
                    <span>
                        <strong>Téted</strong>
                        ${(bet.stake || 0).toFixed(2)} HUF
                    </span>
                    <span>
                        <strong>Odds</strong>
                        ${(bet.totalOdds || 0).toFixed(3)}
                    </span>
                    <span>
                        <strong>Nyeremény</strong>
                        ${(bet.potentialWin || 0).toFixed(2)} HUF
                    </span>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// ===== LOCAL STORAGE KEZELÉS =====
function saveElozmenytToStorage() {
    localStorage.setItem('betHistory', JSON.stringify(elozmenyek));
}

function loadElozmenyFromStorage() {
    const stored = localStorage.getItem('betHistory');
    if (stored) {
        try {
            elozmenyek = JSON.parse(stored);
        } catch (e) {
            console.error('Storage parse hiba:', e);
            elozmenyek = [];
        }
    }
    renderElozmeny();
}

// ===== TAB SWITCHING =====
document.addEventListener('click', function(e) {
    if (e.target.closest('.ticket-tab')) {
        const btn = e.target.closest('.ticket-tab');
        const tabName = btn.dataset.tab;
        
        document.querySelectorAll('.ticket-tab').forEach(t => {
            t.classList.remove('active');
        });
        document.querySelectorAll('.ticket-content').forEach(c => {
            c.classList.remove('active');
        });
        
        btn.classList.add('active');
        const content = document.getElementById(`${tabName}-content`);
        if (content) content.classList.add('active');
        
        // Újra rendeezzük ha szükséges
        if (tabName === 'elozmeny') {
            renderElozmeny();
        }
    }
});

// ===== UTILITY FUNKCIÓK =====
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
