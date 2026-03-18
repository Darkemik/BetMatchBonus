/**
 * BETSLIP.JS - Szelvény/Ticket kezelés
 * Adatbázis: Tickets + TicketSelections táblák
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[BETSLIP] Inicializálás...');

    let ticketItems = [];
    let bettingHistory = [];
    let historyCheckTimer = null;
    let isLoggedIn = false;
    let currentUserId = null;

    // ===== BEJELENTKEZÉS ELLENŐRZÉSE =====
    function checkLoginStatus() {
        fetch('../../backend/Auth/me.php')
            .then(r => r.json())
            .then(data => {
                isLoggedIn = data.loggedIn === true;
                currentUserId = data.user?.id || null;
                console.log('[BETSLIP] Login status:', isLoggedIn, 'User ID:', currentUserId);
            })
            .catch(e => {
                console.error('[BETSLIP] Login check error:', e);
                isLoggedIn = false;
            });
    }

    // ===== POP-UP MODAL - TÉT LEADÁSA =====
    function showPlaceBetModal(totalOdds) {
        let modal = document.getElementById('betslip-place-bet-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'betslip-place-bet-modal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content bg-dark text-light" style="border: 1px solid rgba(255,255,255,0.1);">
                        <div class="modal-header border-bottom border-secondary">
                            <h5 class="modal-title">
                                <i class="fas fa-ticket-alt"></i> Szelvény leadása
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="modal-stake-input" class="form-label">Tét összege (Ft):</label>
                                <input type="number" id="modal-stake-input" class="form-control bg-secondary text-light border-secondary" 
                                       placeholder="Min. 100 Ft" min="100" value="100">
                                <small class="text-muted">Minimális tét: 100 Ft</small>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <div class="card bg-secondary" style="border: none;">
                                        <div class="card-body p-2">
                                            <div class="text-muted small">Szorzó</div>
                                            <div class="fs-5 fw-bold" id="modal-total-odds">-</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="card bg-secondary" style="border: none;">
                                        <div class="card-body p-2">
                                            <div class="text-muted small">Lehetséges nyeremény</div>
                                            <div class="fs-5 fw-bold" id="modal-potential-win">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="modal-items-list" class="small text-muted" style="max-height: 200px; overflow-y: auto;">
                                <!-- items list -->
                            </div>
                        </div>
                        <div class="modal-footer border-top border-secondary">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Mégse</button>
                            <button type="button" class="btn btn-success" id="modal-confirm-bet-btn">
                                <i class="fas fa-check"></i> Szelvény leadása
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            document.getElementById('modal-confirm-bet-btn').addEventListener('click', function() {
                const modalStake = parseFloat(document.getElementById('modal-stake-input').value) || 0;
                if (modalStake < 100) {
                    alert('Minimum tét: 100 Ft');
                    return;
                }
                submitTicketToDB(modalStake);
            });
            
            const modalStakeInput = document.getElementById('modal-stake-input');
            if (modalStakeInput) {
                modalStakeInput.addEventListener('input', () => {
                    updateModalDisplay(totalOdds);
                });
            }
        }
        
        updateModalDisplay(totalOdds);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    function updateModalDisplay(totalOdds) {
        const modalStake = parseFloat(document.getElementById('modal-stake-input').value) || 0;
        const potentialWin = Math.round(modalStake * totalOdds);
        
        document.getElementById('modal-total-odds').textContent = totalOdds.toFixed(3);
        document.getElementById('modal-potential-win').textContent = potentialWin.toLocaleString('hu-HU') + ' Ft';
        
        let itemsHtml = '<div class="border-top pt-2">';
        ticketItems.forEach((item, idx) => {
            itemsHtml += `
                <div class="py-2 border-bottom">
                    <div class="fw-bold">${escapeHtml(item.homeTeam)} vs ${escapeHtml(item.awayTeam)}</div>
                    <div class="small">${escapeHtml(item.market)}</div>
                    <div class="small text-success">${escapeHtml(item.pick)} @ ${item.odds.toFixed(2)}</div>
                </div>
            `;
        });
        itemsHtml += '</div>';
        document.getElementById('modal-items-list').innerHTML = itemsHtml;
    }

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
        console.log('[BETSLIP] Ticket items:', ticketItems.length, '- Active buttons refreshed');
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

        // EVENT DELEGATION - nem direkt az X gombokra
        betsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('betslip-remove')) {
                const idx = parseInt(e.target.getAttribute('data-index'));
                if (!isNaN(idx) && idx >= 0 && idx < ticketItems.length) {
                    ticketItems.splice(idx, 1);
                    saveToStorage();
                    renderTicket();
                    refreshAllOddsButtons();
                    console.log('[BETSLIP] Item removed, total:', ticketItems.length);
                }
            }
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
            
            // Közvetlenül nyissuk meg a fogadás modalt
            let totalOdds = 1;
            ticketItems.forEach(item => totalOdds *= item.odds);
            showPlaceBetModal(totalOdds);
        });
    }

    // ===== SUBMIT TICKET TO DB =====
    function submitTicketToDB(stake) {
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
            const modal = document.getElementById('betslip-place-bet-modal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }

            if (data.status === 'ok') {
                // Success modal
                let successModal = document.getElementById('betslip-success-modal');
                if (!successModal) {
                    successModal = document.createElement('div');
                    successModal.id = 'betslip-success-modal';
                    successModal.className = 'modal fade';
                    successModal.tabIndex = -1;
                    successModal.innerHTML = `
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content bg-dark text-light" style="border: 1px solid rgba(76, 175, 80, 0.3);">
                                <div class="modal-header border-bottom border-success">
                                    <h5 class="modal-title">
                                        <i class="fas fa-check-circle text-success"></i> Szelvény sikeresen leadva!
                                    </h5>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Szelvény ID:</strong> ${data.ticket_id || '-'}</p>
                                    <p><strong>Tét:</strong> ${stake.toLocaleString('hu-HU')} Ft</p>
                                    <p><strong>Lehetséges nyeremény:</strong> ${Math.round(stake * totalOdds).toLocaleString('hu-HU')} Ft</p>
                                </div>
                                <div class="modal-footer border-top border-secondary">
                                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Bezárás</button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(successModal);
                }
                
                const bsSuccessModal = new bootstrap.Modal(successModal);
                bsSuccessModal.show();

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
    }

    // ===== CLEAR BUTTON =====
    const clearBtn = document.getElementById('clear-bets-btn');
    if (clearBtn) {
        // Tisztítjuk az előző event listener-eket
        const newClearBtn = clearBtn.cloneNode(true);
        clearBtn.parentNode.replaceChild(newClearBtn, clearBtn);
        
        newClearBtn.addEventListener('click', () => {
            if (confirm('Biztosan törlöd az összes fogadást?')) {
                ticketItems = [];
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();
                console.log('[BETSLIP] All bets cleared');
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
    checkLoginStatus();
    loadFromStorage();
    renderTicket();
    loadBettingHistory();
    refreshAllOddsButtons();

    console.log('[BETSLIP] Kész!');
});
