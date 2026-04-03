/**
 * BETSLIP.JS - Szelvény/Ticket kezelés
 * Adatbázis: Tickets + TicketSelections táblák
 */

document.addEventListener('DOMContentLoaded', function() {
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));
    console.log('[BETSLIP] Inicializálás...');

    let ticketItems = [];
    let bettingHistory = [];
    let historyCheckTimer = null;
    let isLoggedIn = false;
    let currentUserId = null;
    let userBalance = 0;

    // ===== BEJELENTKEZÉS ELLENŐRZÉSE =====
    function checkLoginStatus() {
        fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                isLoggedIn = data.loggedIn === true;
                currentUserId = data.user?.id || null;
                userBalance = parseFloat(data.user?.balance) || 0;
                console.log('[BETSLIP] Login status:', isLoggedIn, 'User ID:', currentUserId, 'Balance:', userBalance);
                updatePlaceBetButton();
            })
            .catch(e => {
                console.error('[BETSLIP] Login check error:', e);
                isLoggedIn = false;
                userBalance = 0;
                updatePlaceBetButton();
            });
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
        console.log('[BETSLIP] toggleOdds called:', {homeTeam, awayTeam, pick, odds, market, matchId});
        
        var existingIndex = ticketItems.findIndex(function(item) {
            return item.homeTeam === homeTeam && 
                   item.awayTeam === awayTeam && 
                   item.pick === pick && 
                   item.market === market;
        });

        if (existingIndex >= 0) {
            console.log('[BETSLIP] Eltávolítás indexről:', existingIndex);
            ticketItems.splice(existingIndex, 1);
        } else {
            // MÓDOSÍTÁS: Csak akkor blokkoljuk, ha ugyanarról a piacról már van kiválasztás
            // De KÜLÖNBÖZŐ piacokról lehet több fogadás ugyanarról a meccsről
            if (window.BetslipLogic.hasSelectionInMarket(homeTeam, awayTeam, market)) {
                console.log('[BETSLIP] Már van választás ebben a piacban, nem lehet hozzáadni másikat ugyanarról a piacról');
                BmbPopup.warning(t('betslip.marketConflictMsg', 'Már van választásod ebben a piacon! Másik piacról válassz vagy módosítsd a jelenlegi választást.'), t('betslip.marketConflictTitle', 'Piac ütközés'));
                return;
            }

            if (window.BetslipLogic.isCorrectScoreMarket(market) && 
                window.BetslipLogic.isConflictingScore(pick, homeTeam, awayTeam)) {
                console.log('[BETSLIP] Ütköző pontos végeredmény');
                BmbPopup.warning(t('betslip.conflictSelectionMsg', 'Ez a választás ütköző az 1X2 piacon már meglévő választásoddal!'), t('betslip.conflictSelectionTitle', 'Ütköző választás'));
                return;
            }

            console.log('[BETSLIP] Hozzáadás:', {homeTeam, awayTeam, pick, odds, market});
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

        console.log('[BETSLIP] ticketItems után:', ticketItems);
        saveToStorage();
        renderTicket();
        refreshAllOddsButtons();
    };

    // ===== EGYES / KÖTÉS SUB-TAB AUTOMATIKUS VÁLTÁS =====
    function updateTypeTabs() {
        const tabEgyes = document.getElementById('tab-egyes');
        const tabKotes = document.getElementById('tab-kotes');
        if (!tabEgyes || !tabKotes) return;

        if (ticketItems.length >= 2) {
            // 2+ fogadás → Kötés aktív
            tabEgyes.classList.remove('active');
            tabKotes.classList.add('active');
        } else {
            // 0-1 fogadás → Egyes aktív
            tabEgyes.classList.add('active');
            tabKotes.classList.remove('active');
        }
    }

    // ===== TICKET RENDERELÉS =====
    function renderTicket() {
        console.log('[BETSLIP] renderTicket() kezdés, items:', ticketItems.length);
        
        const empty = document.getElementById('betslip-empty');
        const submitBtn = document.getElementById('place-bet-btn');
        const clearBtn = document.getElementById('clear-bets-btn');
        const summary = document.getElementById('betslip-summary');
        const betslipCountEl = document.getElementById('betslip-count');

        if (!empty) {
            console.warn('[BETSLIP] betslip-empty nem található!');
            return;
        }

        if (ticketItems.length === 0) {
            console.log('[BETSLIP] Nincsenek tételek, üres megjelenítés');
            empty.style.display = 'flex';
            const betsContainer = document.getElementById('betslip-bets');
            if (betsContainer) {
                betsContainer.style.display = 'none';
                betsContainer.innerHTML = '';
            }
            if (submitBtn) {
                submitBtn.style.display = 'none';
                submitBtn.setAttribute('disabled', 'disabled');
            }
            if (clearBtn) clearBtn.style.display = 'none';
            if (summary) summary.style.display = 'none';
            if (betslipCountEl) betslipCountEl.textContent = '0';
            updateTypeTabs();
            return;
        }

        // Friss keresés az aktuális DOM-ból
        let betsContainer = document.getElementById('betslip-bets');
        if (!betsContainer) {
            console.warn('[BETSLIP] betslip-bets konténer nem található!');
            return;
        }

        empty.style.display = 'none';
        betsContainer.style.display = 'flex';
        if (submitBtn) {
            submitBtn.style.display = 'block';
            submitBtn.removeAttribute('disabled');
        }
        if (clearBtn) clearBtn.style.display = 'block';
        if (summary) summary.style.display = 'block';

        // MÓDOSÍTÁS: Ne lecseréljük az egész containerét, hanem csak az innerHTML-t frissítjük
        betsContainer.innerHTML = '';
        
        let totalOdds = 1;

        ticketItems.forEach((item, idx) => {
            totalOdds *= item.odds;
            const el = document.createElement('div');
            el.className = 'betslip-item';
            el.innerHTML = `
                <div class="betslip-item-header">
                    <span>${escapeHtml(item.homeTeam)} vs ${escapeHtml(item.awayTeam)}</span>
                    <button class="betslip-remove" data-index="${idx}" title="${t('betslip.remove', 'Eltávolítás')}">×</button>
                </div>
                <div class="betslip-item-market">${escapeHtml(item.market)}</div>
                <div class="betslip-item-pick">${escapeHtml(item.pick)}</div>
                <div class="betslip-item-odds">${item.odds.toFixed(2)}</div>
            `;
            betsContainer.appendChild(el);
        });

        // Frissítjük az összes elemet az oldalon
        if (betslipCountEl) betslipCountEl.textContent = ticketItems.length;
        const totalOddsEl = document.getElementById('total-odds');
        if (totalOddsEl) totalOddsEl.textContent = totalOdds.toFixed(3);
        updatePotentialWin(totalOdds);
        updatePlaceBetButton();
        updateTypeTabs();
        
        console.log('[BETSLIP] renderTicket() vége, totalOdds:', totalOdds);
    }

    // ===== REMOVE BUTTON - delegated event listener =====
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('betslip-remove')) {
            const idx = parseInt(e.target.getAttribute('data-index'));
            console.log('[BETSLIP] Remove gomb kattintva, index:', idx);
            if (!isNaN(idx) && idx >= 0 && idx < ticketItems.length) {
                console.log('[BETSLIP] Removing item at index:', idx);
                ticketItems.splice(idx, 1);
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();
                console.log('[BETSLIP] Item removed, total:', ticketItems.length);
            }
        }
    });

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
    
    function updatePlaceBetButton() {
        if (!submitBtn) return;
        
        const stake = parseFloat(document.getElementById('stake-input')?.value) || 0;
        
        // Letiltás feltételei:
        if (!isLoggedIn || userBalance === 0 || userBalance < stake || ticketItems.length === 0) {
            submitBtn.disabled = true;
            if (!isLoggedIn) {
                submitBtn.title = t('betslip.mustLogin', 'Be kell jelentkezned a fogadáshoz');
            } else if (userBalance === 0) {
                submitBtn.title = t('betslip.noBalance', 'Nincs elegendő egyenleg! Kérjük, töltsd fel az accountot.');
            } else if (userBalance < stake) {
                submitBtn.title = t('betslip.insufficientStakeBalance', 'Nincs elegendő egyenleg az adott téthez!');
            } else if (ticketItems.length === 0) {
                submitBtn.title = t('betslip.minOneBet', 'Legalább egy fogadás szükséges');
            }
        } else {
            submitBtn.disabled = false;
            submitBtn.title = '';
        }
    }
    
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (ticketItems.length === 0) return;
            
            const stake = parseFloat(document.getElementById('stake-input').value) || 0;
            if (stake < 100) {
                BmbPopup.warning(t('betslip.minStakeMsg', 'Minimum tét: 100 Ft'), t('betslip.invalidStakeTitle', 'Érvénytelen tét'));
                return;
            }

            if (!isLoggedIn) {
                BmbPopup.info(t('betslip.loginRequiredMsg', 'A fogadáshoz be kell jelentkezned!'), t('betslip.loginRequiredTitle', 'Bejelentkezés szükséges'));
                return;
            }

            if (userBalance === 0 || userBalance < stake) {
                BmbPopup.warning(t('betslip.noBalance', 'Nincs elegendő egyenleg! Kérjük, töltsd fel az accountot.'), t('betslip.noMoneyTitle', 'Nincs elegendő pénz'));
                return;
            }

            submitTicketToDB(stake);
        });
        
        // Figyelje a stake-input változásait
        const stakeInput = document.getElementById('stake-input');
        if (stakeInput) {
            stakeInput.addEventListener('input', updatePlaceBetButton);
        }
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
                                        <i class="fas fa-check-circle text-success"></i> ${t('betslip.ticketSuccess', 'Szelvény sikeresen leadva!')}
                                    </h5>
                                </div>
                                <div class="modal-body">
                                    <p><strong>${t('betslip.stakeLabel', 'Tét:')}</strong> ${stake.toLocaleString('hu-HU')} Ft</p>
                                    <p><strong>${t('betslip.potentialWinLabel', 'Lehetséges nyeremény:')}</strong> ${Math.round(stake * totalOdds).toLocaleString('hu-HU')} Ft</p>
                                </div>
                                <div class="modal-footer border-top border-secondary">
                                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">${t('betslip.close', 'Bezárás')}</button>
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
                refreshAllOddsButtons();

                // Azonnali lokális egyenlegfrissítés (jobb felső sarok)
                if (typeof data.new_balance === 'number' && !Number.isNaN(data.new_balance)) {
                    userBalance = data.new_balance;
                } else {
                    userBalance = Math.max(0, (parseFloat(userBalance) || 0) - stake);
                }

                const usernameEl = document.getElementById('userMenuUsername');
                if (usernameEl) {
                    const formatted = userBalance.toLocaleString('hu-HU', {
                        style: 'currency',
                        currency: 'HUF'
                    }).replace('Ft', 'FT').trim();
                    usernameEl.textContent = formatted;
                }

                updatePlaceBetButton();
                document.dispatchEvent(new CustomEvent('balance:changed'));

                // Biztos frissítés szerverről cache nélkül, hogy ne maradjon beragadt érték
                fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' })
                    .then(r => r.json())
                    .then(me => {
                        if (me && me.loggedIn && me.user) {
                            const freshBalance = parseFloat(me.user.balance) || 0;
                            userBalance = freshBalance;

                            const liveUsernameEl = document.getElementById('userMenuUsername');
                            if (liveUsernameEl) {
                                const freshFormatted = freshBalance.toLocaleString('hu-HU', {
                                    style: 'currency',
                                    currency: 'HUF'
                                }).replace('Ft', 'FT').trim();
                                liveUsernameEl.textContent = freshFormatted;
                            }
                            updatePlaceBetButton();
                        }
                    })
                    .catch(() => {
                        // Csendes fallback, a lokális frissítés már megtörtént
                    });

                checkLoginStatus();
                loadBettingHistory();
            } else {
                BmbPopup.error((data.message || t('mainMenu.unknown', 'Ismeretlen')), t('betslip.betFailedTitle', 'Sikertelen fogadás'));
            }
        })
        .catch(e => {
            console.error('Hiba:', e);
            BmbPopup.error(t('betslip.serverErrorMsg', 'Szerverhiba! Kérjük próbáld újra később.'), t('live.serverError', 'Szerverhiba'));
        });
    }

    // ===== CLEAR BUTTON =====
    const clearBtn = document.getElementById('clear-bets-btn');
    if (clearBtn) {
        // Tisztítjuk az előző event listener-eket
        const newClearBtn = clearBtn.cloneNode(true);
        clearBtn.parentNode.replaceChild(newClearBtn, clearBtn);
        
        newClearBtn.addEventListener('click', () => {
            BmbPopup.confirm(t('betslip.confirmClearAll', 'Biztosan törlöd az összes fogadást?'), function() {
                ticketItems = [];
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();
                console.log('[BETSLIP] All bets cleared');
            }, null, t('betslip.clearAll', 'Összes törlése'));
        });
    }

    // ===== FOGADÁSI ELŐZMÉNYEK BETÖLTÉSE =====
    function loadBettingHistory() {
        fetch('../../backend/ApiRequest/get_betting_history.php')
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    const oldHistory = bettingHistory;
                    bettingHistory = data.history || [];
                    renderHistory();

                    // Státuszváltozás detektálás → popup értesítés
                    if (oldHistory.length > 0) {
                        detectStatusChanges(oldHistory, bettingHistory);
                    }

                    // Háttér-ellenőrzés indítása/leállítása nyitott szelvények alapján
                    manageBackgroundCheck();
                }
            })
            .catch(e => console.error('Előzmények hiba:', e));
    }

    // ===== STÁTUSZVÁLTOZÁS DETEKTÁLÁS (csak logolás, popup nélkül) =====
    function detectStatusChanges(oldHistory, newHistory) {
        newHistory.forEach(ticket => {
            const oldTicket = oldHistory.find(t => t.id === ticket.id);
            if (!oldTicket) return;

            if (oldTicket.status === 'OPEN' && ticket.status !== 'OPEN') {
                if (ticket.status === 'WON') {
                    const winAmount = parseFloat(ticket.potential_win).toLocaleString('hu-HU');
                    console.log('[BETSLIP] Nyertes szelvény! ID:', ticket.id, 'Nyeremény:', winAmount, 'Ft');
                } else if (ticket.status === 'LOST') {
                    console.log('[BETSLIP] Vesztes szelvény. ID:', ticket.id);
                }
            }
        });
    }

    // ===== HÁTTÉR KIÉRTÉKELÉS KEZELÉSE =====
    function manageBackgroundCheck() {
        const hasOpenTickets = bettingHistory.some(t => t.status === 'OPEN');

        if (hasOpenTickets && !historyCheckTimer) {
            // Van nyitott szelvény → indítsuk a háttér-ellenőrzést (60 mp)
            console.log('[BETSLIP] ⏱ Háttér-ellenőrzés indítása (60s) - vannak nyitott szelvények');
            historyCheckTimer = setInterval(() => {
                console.log('[BETSLIP] 🔄 Háttér kiértékelés futtatása...');
                loadBettingHistory();
            }, 60000);
        } else if (!hasOpenTickets && historyCheckTimer) {
            // Nincs nyitott szelvény → leállítjuk
            console.log('[BETSLIP] ⏹ Háttér-ellenőrzés leállítva - nincs nyitott szelvény');
            clearInterval(historyCheckTimer);
            historyCheckTimer = null;
        }
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
            const statusText = ticket.status === 'OPEN' ? '⏳ ' + t('betslip.pending', 'Függőben') : 
                              ticket.status === 'WON' ? '✅ ' + t('betslip.won', 'Nyertes') : 
                              ticket.status === 'LOST' ? '❌ ' + t('betslip.lost', 'Vesztes') : '❓ ' + t('mainMenu.unknown', 'Ismeretlen');
            
            const statusClass = ticket.status.toLowerCase();
            
            let itemsHtml = '';
            if (ticket.items && ticket.items.length > 0) {
                ticket.items.forEach(item => {
                    const itemStatus = item.status || 'OPEN';
                    const itemIcon = itemStatus === 'WON' ? '✅' : 
                                     itemStatus === 'LOST' ? '❌' : '⏳';
                    const itemStatusClass = itemStatus.toLowerCase();
                    
                    itemsHtml += `
                        <div class="elozmeny-item-entry ${itemStatusClass}">
                            <div class="elozmeny-match">
                                <span class="item-status-icon">${itemIcon}</span>
                                ${escapeHtml(item.homeTeam)} vs ${escapeHtml(item.awayTeam)}
                            </div>
                            <div class="elozmeny-market">${escapeHtml(item.market)}</div>
                            <div class="elozmeny-pick">${t('betslip.tipLabel', 'Tipp:')} <strong>${escapeHtml(item.pick)}</strong> @ ${parseFloat(item.odds).toFixed(2)}</div>
                        </div>
                    `;
                });
            }

            // Nyertes szelvénynél más szín a summary-n
            const wonClass = ticket.status === 'WON' ? ' elozmeny-won' : '';
            const lostClass = ticket.status === 'LOST' ? ' elozmeny-lost' : '';

            const el = document.createElement('div');
            el.className = 'elozmeny-item' + wonClass + lostClass;
            el.innerHTML = `
                <div class="elozmeny-header">
                    <span class="elozmeny-date">${new Date(ticket.created_at).toLocaleString('hu-HU')}</span>
                    <span class="elozmeny-status ${statusClass}">${statusText}</span>
                </div>
                <div class="elozmeny-items-list">${itemsHtml}</div>
                <div class="elozmeny-summary">
                    <span><strong>${t('betslip.stakeLabel', 'Tét:')}</strong> ${parseFloat(ticket.stake).toLocaleString('hu-HU')} Ft</span>
                    <span><strong>${t('betslip.oddsLabel', 'Odds:')}</strong> ${parseFloat(ticket.total_odds).toFixed(3)}</span>
                    <span class="${ticket.status === 'WON' ? 'won-amount' : ''}"><strong>${ticket.status === 'WON' ? t('betslip.winLabel', 'Nyeremény:') : t('betslip.potentialLabel', 'Potenciális:')}</strong> ${parseFloat(ticket.potential_win).toLocaleString('hu-HU')} Ft</span>
                </div>
            `;
            container.appendChild(el);
        });
    }

    // ===== ODDS GOMBOK FRISSÍTÉSE =====
    window.refreshAllOddsButtons = function(delay = 0) {
        const doRefresh = () => {
            console.log('[BETSLIP] refreshAllOddsButtons() - gombok keresése...');
            
            const buttons = document.querySelectorAll('.selection-btn');
            console.log('[BETSLIP] Talált .selection-btn gombok:', buttons.length);
            
            if (buttons.length === 0) {
                console.warn('[BETSLIP] Nincsenek .selection-btn gombok az oldalon!');
                return;
            }
            
            buttons.forEach(btn => {
                const home = btn.getAttribute('data-home');
                const away = btn.getAttribute('data-away');
                const pick = btn.getAttribute('data-pick');
                const market = btn.getAttribute('data-market');

                if (!home || !away || !pick || !market) {
                    console.warn('[BETSLIP] Hiányzó adat egy gombnál:', {home, away, pick, market});
                    return;
                }

                const state = window.BetslipLogic.getButtonState(home, away, pick, market);
                
                btn.classList.remove('active', 'disabled');
                btn.removeAttribute('disabled');

                if (state === 'active') {
                    btn.classList.add('active');
                    console.log('[BETSLIP] Gomb active:', `${home} vs ${away} - ${pick}`);
                } else if (state === 'disabled') {
                    btn.classList.add('disabled');
                    btn.setAttribute('disabled', 'disabled');
                    console.log('[BETSLIP] Gomb disabled:', `${home} vs ${away} - ${pick}`);
                }
            });
            
            console.log('[BETSLIP] refreshAllOddsButtons() - kész!');
        };
        
        if (delay > 0) {
            setTimeout(doRefresh, delay);
        } else {
            doRefresh();
        }
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
    updatePlaceBetButton();

    console.log('[BETSLIP] Kész!');
});
