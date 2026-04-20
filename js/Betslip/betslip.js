/**
 * BETSLIP.JS - Szelvény/Ticket kezelés
 * Adatbázis: Tickets + TicketSelections táblák
 */

document.addEventListener('DOMContentLoaded', function() {
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));
    const td = (text) => (typeof window.i18nDynamic === 'function' ? window.i18nDynamic(text) : text);
    console.log('[BETSLIP] Inicializálás...');

    let ticketItems = [];
    let bettingHistory = [];
    let historyCheckTimer = null;
    const HISTORY_PAGE_SIZE = 2;
    let currentHistoryPage = 1;
    let isLoggedIn = false;
    let currentUserId = null;
    let userBalance = 0;
    let userBonusBalance = 0;
    let availableFreeBetAmount = 0;
    let availableFreeBetId = 0;
    let availableFreeBetMinCombo = 0;
    let availableFreeBetMinOdds = 0;
    let availableFreeBetMinOddsPerEvent = 0;
    let manualStakeBeforeFreeBet = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_bet_amount) || 100;
    let activeBonusList = [];
    let isTicketSubmitting = false;
    let cashoutRefreshTimer = null;
    const pendingCashoutRequests = new Set();
    let isHistoryLoading = false;
    let historyLoadedOnce = false;
    let historyAbortController = null;
    let oddsSyncScheduled = false;
    let remoteAvailabilityValidationInFlight = false;
    let lastRemoteAvailabilityValidationAt = 0;
    const REMOTE_AVAILABILITY_VALIDATION_MS = 20000;

    function formatFt(value) {
        return (parseFloat(value) || 0).toLocaleString('hu-HU', {
            maximumFractionDigits: 0,
            minimumFractionDigits: 0
        }) + ' Ft';
    }

    function formatOddsHu(value) {
        return (parseFloat(value) || 0).toFixed(2).replace('.', ',');
    }

    function getCurrentLang() {
        const stored = String(localStorage.getItem('lang') || '').toLowerCase();
        if (stored === 'en' || stored === 'hu') return stored;
        return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en' : 'hu';
    }

    function localizePickText(text) {
        const src = String(text || '');
        if (getCurrentLang() !== 'en') return src;

        return src
            .replace(/\bfelett\b/gi, 'over')
            .replace(/\balatt\b/gi, 'under');
    }

    function normalizeLiveKeyPart(value) {
        return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    function buildTicketOddsKey(homeTeam, awayTeam, market, pick, matchId) {
        const normalizedMatchId = parseInt(matchId, 10) || 0;
        return [
            String(normalizedMatchId),
            normalizeLiveKeyPart(homeTeam),
            normalizeLiveKeyPart(awayTeam),
            normalizeLiveKeyPart(market),
            normalizeLiveKeyPart(pick)
        ].join('|');
    }

    function buildSelectionLiveKey(selection) {
        const matchId = selection.match_id || selection.matchId || selection.event_id || selection.eventId || '';
        const market = selection.market_name || selection.market || '';
        const pick = selection.pick_label || selection.pick || '';
        return [String(matchId), normalizeLiveKeyPart(market), normalizeLiveKeyPart(pick)].join('|');
    }

    function buildMainMenuMatchUrl(matchId) {
        const pathname = window.location && window.location.pathname ? window.location.pathname : '';
        const frontendIdx = pathname.toLowerCase().indexOf('/frontend/');
        const appPrefix = frontendIdx >= 0 ? pathname.substring(0, frontendIdx) : '';
        return appPrefix + '/frontend/MainMenu/MainMenu.php?eventId=' + encodeURIComponent(String(matchId));
    }

    function openMatchDetailsFromBetslip(matchId) {
        const numericMatchId = parseInt(matchId, 10) || 0;
        if (!(numericMatchId > 0)) return;

        if (typeof window.loadMatchDetails === 'function') {
            try {
                window.loadMatchDetails(numericMatchId);
                return;
            } catch (e) {
                console.warn('[BETSLIP] loadMatchDetails hiba, fallback navigáció:', e);
            }
        }

        window.location.href = buildMainMenuMatchUrl(numericMatchId);
    }

    function isHistoryTabActive() {
        return !!document.getElementById('betslip-elozmeny')?.classList.contains('active');
    }

    function resetSelectionLiveState(ticketId) {
        document.querySelectorAll('.elozmeny-item-entry[data-ticket-id="' + ticketId + '"]').forEach(row => {
            row.classList.remove('live-up', 'live-down', 'live-flat', 'live-won', 'live-lost', 'live-no-data');
            const liveMeta = row.querySelector('.elozmeny-live-meta');
            if (liveMeta) {
                liveMeta.textContent = '';
                liveMeta.style.display = 'none';
                liveMeta.classList.remove('trend-up', 'trend-down', 'trend-flat', 'trend-neutral');
            }
        });
    }

    function applySelectionLiveUpdates(ticketId, selectionUpdates) {
        if (!Array.isArray(selectionUpdates) || selectionUpdates.length === 0) {
            resetSelectionLiveState(ticketId);
            return;
        }

        const updatesByKey = new Map();
        selectionUpdates.forEach(update => updatesByKey.set(buildSelectionLiveKey(update), update));

        document.querySelectorAll('.elozmeny-item-entry[data-ticket-id="' + ticketId + '"]').forEach(row => {
            row.classList.remove('live-up', 'live-down', 'live-flat', 'live-won', 'live-lost', 'live-no-data');
            const liveMeta = row.querySelector('.elozmeny-live-meta');
            const update = updatesByKey.get(row.getAttribute('data-live-key') || '');

            if (!update) {
                if (liveMeta) {
                    liveMeta.textContent = '';
                    liveMeta.style.display = 'none';
                }
                return;
            }

            let liveText = '';
            let liveTextClass = 'trend-neutral';
            const lang = getCurrentLang();
            if (update.status === 'WON') {
                row.classList.add('live-won');
                liveText = (lang === 'en') ? 'Settled: won' : 'Lezárva: nyertes';
                liveTextClass = 'trend-up';
            } else if (update.status === 'LOST') {
                row.classList.add('live-lost');
                liveText = (lang === 'en') ? 'Settled: lost' : 'Lezárva: vesztes';
                liveTextClass = 'trend-down';
            } else if (update.live_odds && Number(update.live_odds) > 0) {
                const liveOdds = parseFloat(update.live_odds).toFixed(2);
                if (update.trend === 'up') {
                    liveText = (lang === 'en' ? 'Live odds improved: ' : 'Live odds javult: ') + liveOdds;
                    liveTextClass = 'trend-up';
                } else if (update.trend === 'down') {
                    liveText = (lang === 'en' ? 'Live odds worsened: ' : 'Live odds romlott: ') + liveOdds;
                    liveTextClass = 'trend-down';
                } else {
                    liveText = (lang === 'en' ? 'Live odds unchanged: ' : 'Live odds változatlan: ') + liveOdds;
                    liveTextClass = 'trend-flat';
                }
            } else {
                liveText = (lang === 'en') ? 'Live odds data unavailable' : 'Live odds adat nem elérhető';
                liveTextClass = 'trend-neutral';
            }

            if (liveMeta) {
                liveMeta.textContent = liveText;
                liveMeta.style.display = liveText ? 'block' : 'none';
                liveMeta.classList.remove('trend-up', 'trend-down', 'trend-flat', 'trend-neutral');
                liveMeta.classList.add(liveTextClass);
            }
        });
    }

    function refreshVisibleCashoutPreviews() {
        if (!isHistoryTabActive()) return;
        const openTicketButtons = document.querySelectorAll('.cashout-btn[data-ticket-id]');
        openTicketButtons.forEach(btn => {
            const ticketId = parseInt(btn.getAttribute('data-ticket-id'), 10);
            if (ticketId > 0) {
                window.BetslipCashout.loadPreview(ticketId);
            }
        });
    }

    function manageCashoutLiveRefresh() {
        const hasOpenTickets = bettingHistory.some(t => t.status === 'OPEN');

        if (hasOpenTickets && !cashoutRefreshTimer) {
            cashoutRefreshTimer = setInterval(refreshVisibleCashoutPreviews, 7000);
        } else if (!hasOpenTickets && cashoutRefreshTimer) {
            clearInterval(cashoutRefreshTimer);
            cashoutRefreshTimer = null;
        }
    }

    function getTicketMetrics() {
        let totalOdds = 1;
        let minOddsPerEvent = null;
        const selectionCount = ticketItems.length;
        const hasDailyTip = ticketItems.some(item => item.isDailyTip);
        const ignoredOddsIndexes = new Set();

        const ignoreLowerOddsFromPair = (idxA, idxB) => {
            const oddsA = parseFloat(ticketItems[idxA] && ticketItems[idxA].odds) || 0;
            const oddsB = parseFloat(ticketItems[idxB] && ticketItems[idxB].odds) || 0;

            if (oddsA >= oddsB) {
                ignoredOddsIndexes.add(idxB);
            } else {
                ignoredOddsIndexes.add(idxA);
            }
        };

        // Speciális szabály: 1X2 döntetlen + összes gól 0,5 alatt ugyanazon meccsen
        // esetén a döntetlen ne szorzódjon bele, csak a 0,5 alatti odds számítson.
        for (let i = 0; i < ticketItems.length; i++) {
            for (let j = i + 1; j < ticketItems.length; j++) {
                if (!isSameSelectionEvent(ticketItems[i], ticketItems[j])) continue;

                const a = analyzeComboSelection(ticketItems[i]);
                const b = analyzeComboSelection(ticketItems[j]);
                const aNoGoalEquivalent = a.isNoGoalEquivalentPick;
                const bNoGoalEquivalent = b.isNoGoalEquivalentPick;

                if ((a.is1X2 && a.isDrawPick && bNoGoalEquivalent) || (b.is1X2 && b.isDrawPick && aNoGoalEquivalent)) {
                    ignoreLowerOddsFromPair(i, j);
                } else if (aNoGoalEquivalent && bNoGoalEquivalent) {
                    ignoreLowerOddsFromPair(i, j);
                } else if ((aNoGoalEquivalent && b.isLastGoalNoGoalPick) || (bNoGoalEquivalent && a.isLastGoalNoGoalPick)) {
                    ignoreLowerOddsFromPair(i, j);
                } else if ((a.isTeamWinPick && b.isOverHalfGoalTotal) || (b.isTeamWinPick && a.isOverHalfGoalTotal)) {
                    ignoreLowerOddsFromPair(i, j);
                }
            }
        }

        ticketItems.forEach((item, idx) => {
            if (ignoredOddsIndexes.has(idx)) return;

            const itemOdds = parseFloat(item.odds) || 0;
            if (itemOdds > 0) {
                totalOdds *= itemOdds;
                if (minOddsPerEvent === null || itemOdds < minOddsPerEvent) {
                    minOddsPerEvent = itemOdds;
                }
            }
        });

        // 1.2x szorzó ha van napi tipp a szelvényen
        if (hasDailyTip) {
            totalOdds *= (window.SITE_SETTINGS && window.SITE_SETTINGS.daily_tip_multiplier) || 1.2;
        }

        // Oddspiramis: 1.3x szorzó ha 6+ fogadás van a szelvényen
        const minPyramidSel = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_pyramid_selections) || 6;
        const hasOddsPyramid = selectionCount >= minPyramidSel;
        if (hasOddsPyramid) {
            totalOdds *= (window.SITE_SETTINGS && window.SITE_SETTINGS.odds_pyramid_multiplier) || 1.3;
        }

        if (minOddsPerEvent === null) {
            minOddsPerEvent = 0;
        }

        return {
            selectionCount,
            totalOdds,
            minOddsPerEvent,
            hasDailyTip,
            hasOddsPyramid
        };
    }

    function isFreeBetTicketEligible() {
        if (ticketItems.length === 0) return false;

        const metrics = getTicketMetrics();
        if (availableFreeBetMinCombo > 0 && metrics.selectionCount < availableFreeBetMinCombo) return false;
        if (availableFreeBetMinOdds > 0 && metrics.totalOdds < availableFreeBetMinOdds) return false;
        if (availableFreeBetMinOddsPerEvent > 0 && metrics.minOddsPerEvent < availableFreeBetMinOddsPerEvent) return false;

        return true;
    }

    function isUsingFreeBet() {
        const toggle = document.getElementById('use-freebet-toggle');
        const selectEl = document.getElementById('balance-type-select');
        return !!(toggle && toggle.checked) || !!(selectEl && selectEl.value === 'freebet');
    }

    function renderFreeBetOption() {
        const row = document.getElementById('freebet-option-row');
        const amountEl = document.getElementById('freebet-amount-display');
        const toggle = document.getElementById('use-freebet-toggle');
        const stakeInput = document.getElementById('stake-input');
        const selectEl = document.getElementById('balance-type-select');
        if (!stakeInput || !selectEl) return;

        const eligibleForCurrentTicket = isFreeBetTicketEligible();
        const freeBetAvailable = isLoggedIn && availableFreeBetAmount > 0 && availableFreeBetId > 0 && eligibleForCurrentTicket;

        if (row) {
            row.style.display = 'none';
        }
        if (amountEl) {
            amountEl.textContent = formatFt(availableFreeBetAmount);
        }

        if (!freeBetAvailable) {
            if (toggle) toggle.checked = false;
            if (selectEl.value === 'freebet') {
                selectEl.value = 'real';
            }
            stakeInput.readOnly = false;
            stakeInput.removeAttribute('aria-disabled');
        } else {
            if (toggle) {
                toggle.checked = (selectEl.value === 'freebet');
            }
        }
    }

    function applyFreeBetSelectionState() {
        const toggle = document.getElementById('use-freebet-toggle');
        const stakeInput = document.getElementById('stake-input');
        const selectEl = document.getElementById('balance-type-select');
        if (!stakeInput) return;

        const freeBetCanBeUsed = availableFreeBetAmount > 0 && availableFreeBetId > 0;
        const toggleChecked = !!(toggle && toggle.checked);
        const selectedFreeBet = !!(selectEl && selectEl.value === 'freebet');
        const shouldUseFreeBet = freeBetCanBeUsed && (toggleChecked || selectedFreeBet);

        if (shouldUseFreeBet) {
            if (toggle) toggle.checked = true;
            if (selectEl) selectEl.value = 'freebet';
            manualStakeBeforeFreeBet = parseFloat(stakeInput.value) || manualStakeBeforeFreeBet || 100;
            stakeInput.value = String(Math.round(availableFreeBetAmount));
            stakeInput.readOnly = true;
            stakeInput.setAttribute('aria-disabled', 'true');
            updateSelectBorderColor();
        } else {
            if (toggle) toggle.checked = false;
            if (selectEl && selectEl.value === 'freebet') {
                selectEl.value = 'real';
            }
            stakeInput.readOnly = false;
            stakeInput.removeAttribute('aria-disabled');
            if ((parseFloat(stakeInput.value) || 0) === Math.round(availableFreeBetAmount) && manualStakeBeforeFreeBet > 0) {
                stakeInput.value = String(Math.round(manualStakeBeforeFreeBet));
            }
        }

        const metrics = getTicketMetrics();
        updatePotentialWin(metrics.totalOdds);
        updatePlaceBetButton();
        updateBetslipBalanceDisplay();
    }

    // ===== BEJELENTKEZÉS ELLENŐRZÉSE =====
    function checkLoginStatus() {
        fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' })
            .then(r => r.json())
            .then(data => {
                isLoggedIn = data.loggedIn === true;
                currentUserId = data.user?.id || null;
                userBalance = parseFloat(data.user?.balance) || 0;
                userBonusBalance = parseFloat(data.user?.bonus_balance) || 0;
                availableFreeBetAmount = parseFloat(data.user?.available_free_bet_amount) || 0;
                availableFreeBetId = parseInt(data.user?.available_free_bet_id, 10) || 0;
                availableFreeBetMinCombo = parseInt(data.user?.available_free_bet_min_combo, 10) || 0;
                availableFreeBetMinOdds = parseFloat(data.user?.available_free_bet_min_odds) || 0;
                availableFreeBetMinOddsPerEvent = parseFloat(data.user?.available_free_bet_min_odds_per_event) || 0;
                activeBonusList = Array.isArray(data.user?.active_bonuses) ? data.user.active_bonuses : [];
                console.log('[BETSLIP] Login status:', isLoggedIn, 'User ID:', currentUserId, 'Balance:', userBalance, 'Bonus:', userBonusBalance, 'Active bonuses:', activeBonusList.length);
                renderFreeBetOption();
                applyFreeBetSelectionState();
                updatePlaceBetButton();
                updateBetslipBalanceDisplay();
                if (isLoggedIn && isHistoryTabActive() && !historyLoadedOnce) {
                    loadBettingHistory();
                }
            })
            .catch(e => {
                console.error('[BETSLIP] Login check error:', e);
                isLoggedIn = false;
                userBalance = 0;
                userBonusBalance = 0;
                availableFreeBetAmount = 0;
                availableFreeBetId = 0;
                availableFreeBetMinCombo = 0;
                availableFreeBetMinOdds = 0;
                availableFreeBetMinOddsPerEvent = 0;
                activeBonusList = [];
                renderFreeBetOption();
                updatePlaceBetButton();
                manageBackgroundCheck();
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

        getButtonState: function(homeTeam, awayTeam, pick, market, matchId) {
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

            var candidateItem = {
                homeTeam: homeTeam,
                awayTeam: awayTeam,
                pick: pick,
                market: market,
                matchId: parseInt(matchId, 10) || 0
            };
            if (findMarketComboConflictWithCandidate(candidateItem)) {
                return 'disabled';
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

    function getUnavailableTicketItemsCount() {
        return ticketItems.filter(item => !!item.marketUnavailable).length;
    }

    function hasBoostedSingleOnlyViolation() {
        if (!Array.isArray(ticketItems) || ticketItems.length <= 1) return false;
        return ticketItems.some(item => !!item.isBoosted);
    }

    function isOddsPlusSingleOnlyMarket(marketName) {
        const normalized = normalizeLiveKeyPart(marketName);
        if (!normalized) return false;
        return normalized.includes('1x2') && (normalized.includes('odds+') || normalized.includes('odds +'));
    }

    function hasAnySingleOnlyComboViolation() {
        return hasBoostedSingleOnlyViolation();
    }

    function normalizeComboText(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function parseGoalsLine(value) {
        const normalized = normalizeComboText(value).replace(',', '.');
        const match = normalized.match(/(\d+(?:\.\d+)?)/);
        return match ? parseFloat(match[1]) : null;
    }

    function analyzeComboSelection(item) {
        const market = normalizeComboText(item && item.market);
        const pick = normalizeComboText(item && item.pick);
        const home = normalizeComboText(item && item.homeTeam);
        const away = normalizeComboText(item && item.awayTeam);

        const is1X2 = window.BetslipLogic.is1X2Market(item && item.market);
        const is1X2GoalsComboMarket = market.includes('1x2 & golok szama') || market.includes('1x2 and goals') || market.includes('1x2 + goals');
        const is1X2BttsComboMarket = market.includes('1x2 - mindket csapat szerez golt') || market.includes('1x2 & mindket csapat szerez golt') || market.includes('1x2 and both teams to score') || market.includes('1x2 & both teams to score');
        const is1X2GoalsBttsComboMarket = (market.includes('1x2 & golok szama') || market.includes('1x2 and goals')) && (market.includes('mindket csapat szerez golt') || market.includes('both teams to score') || market.includes('btts'));
        const isAnyRestricted1X2ComboMarket = is1X2GoalsComboMarket || is1X2BttsComboMarket || is1X2GoalsBttsComboMarket;
        const isPlain1X2Market = is1X2 && !isAnyRestricted1X2ComboMarket;
        const isCorrectScore = window.BetslipLogic.isCorrectScoreMarket(item && item.market);

        const hasGoalWord = market.includes('gol') || market.includes('goal');
        const isBtts = market.includes('mindket csapat szerez golt') || market.includes('both teams to score') || market.includes('btts');
        const isTeamToScore = market.includes('melyik csapat szerez golt') || market.includes('which team scores');
        const isHalfTimeFullTime = market.includes('felido/vegeredmeny') || market.includes('1. felido/vegeredmeny') || market.includes('half time/full time') || market.includes('1st half/full time');
        const isHalfTime = (market.includes('felido') || market.includes('half time') || market.includes('1st half')) && !isHalfTimeFullTime;
        const isHandicap = market.includes('hendikep') || market.includes('handicap');
        const isDoubleChance = market.includes('ketesely') || market.includes('ketsely') || market.includes('double chance');
        const isDrawNoBet = market.includes('draw no bet') ||
            market.includes('dnb') ||
            (market.includes('dontetlennel') &&
                market.includes('tet') &&
                (market.includes('vissza jar') || market.includes('visszajar')));
        const isPlayerToScore = market.includes('jatekos golt szerez') || market.includes('player to score') || market.includes('goalscorer') || market.includes('anytime scorer');
        const isFirstLastGoal = market.includes('elso gol') || market.includes('1. gol') || market.includes('first goal') || market.includes('utolso gol') || market.includes('last goal');
        const isLastGoalMarket = market.includes('utolso gol') || market.includes('last goal');
        const isFirstGoalHowMarket = (market.includes('hogyan szerzik') && (market.includes('elso gol') || market.includes('1. gol'))) || market.includes('how is the first goal scored');
        const isFirstGoalTime10Market = (market.includes('mikor szerzik') && (market.includes('elso gol') || market.includes('1. gol')) && market.includes('10 perces')) || market.includes('when is the first goal scored') && market.includes('10-minute');
        const isFirstGoalTime15Market = (market.includes('mikor szerzik') && (market.includes('elso gol') || market.includes('1. gol')) && market.includes('15 perces')) || market.includes('when is the first goal scored') && market.includes('15-minute');
        const isPenaltyGoalMarket = market.includes('buntetobol golt szerez') || market.includes('penalty goal');
        const isOwnGoalMarket = market.includes('lesz ongol') || market.includes('will there be an own goal');
        const isSubScorerMarket = market.includes('cserejatekos szerez golt') || market.includes('substitute scores');
        const isWinToNil = market.includes('kapott gol nelkuli gyozelem') || market.includes('kapott gol nelkul') || market.includes('win to nil') || market.includes('clean sheet');
        const isGeneralScoringMarket = market.includes('golt szerez') || market.includes('to score') || market.includes('scores');

        const isOverPick = pick.includes('felett') || pick.includes('over') || pick.includes('tobb') || pick.includes('more than');
        const isUnderPick = pick.includes('alatt') || pick.includes('under') || pick.includes('kevesebb') || pick.includes('less than');
        const isYesPick = pick === 'igen' || pick === 'yes';
        const isNoPick = pick === 'nem' || pick === 'no';

        const isHomePick = pick === '1' || pick === 'hazai' || pick === 'home' || (home && pick.includes(home));
        const isAwayPick = pick === '2' || pick === 'vendeg' || pick === 'away' || (away && pick.includes(away));

        const isTeamGoalsMarket = hasGoalWord && (
            market.includes('hazai csapat') ||
            market.includes('vendeg csapat') ||
            market.includes('home team') ||
            market.includes('away team') ||
            market.includes('csapat gol') ||
            market.includes('team goals') ||
            (home && market.includes(home)) ||
            (away && market.includes(away))
        );

        const isTotalGoalsMarket = hasGoalWord && (
            market.includes('golok szama') ||
            market.includes('goals szama') ||
            market.includes('total goals') ||
            market.includes('goals number') ||
            market.includes('meccs tobb') ||
            market.includes('match over') ||
            market.includes('match under') ||
            market.includes('over/under')
        ) && !isTeamGoalsMarket;
        const isGoalsRangeMarket = (market.includes('golok szama') && market.includes('tartomany')) || market.includes('goals range');
        const isOddGoalsMarket = hasGoalWord && (market.includes('paratlan') || market.includes('odd'));

        const isGoalRelated = isTotalGoalsMarket || isTeamGoalsMarket || isBtts || isTeamToScore || isPlayerToScore || isFirstLastGoal || isWinToNil || isGeneralScoringMarket;
        const lineValue = parseGoalsLine((item && item.pick) || (item && item.market) || '');
        const isUnderHalfGoalTotal = isTotalGoalsMarket && isUnderPick && lineValue !== null && lineValue <= 0.5;
        const isUnderOneHalfGoalTotal = isTotalGoalsMarket && isUnderPick && lineValue !== null && lineValue > 0.5 && lineValue <= 1.5;
        const isOverHalfGoalTotal = isTotalGoalsMarket && isOverPick && lineValue !== null && lineValue >= 0.5;
        const isPositiveTotalGoalsLine = isTotalGoalsMarket && lineValue !== null && lineValue > 0 && !isUnderHalfGoalTotal;

        const isTeamToScoreHomePick = isTeamToScore && (isHomePick || pick.includes('hazai') || pick.includes('home'));
        const isTeamWinPick = is1X2 && (isHomePick || isAwayPick);
        const isDrawPick = is1X2 && (pick === 'x' || pick === 'draw' || pick.includes('dontetlen'));
        const isDoubleChanceHomeAwayPick = isDoubleChance && (pick === '12' || pick.includes('hazai vagy vendeg') || pick.includes('home or away'));
        const isFirstGoalMarket = market.includes('elso gol') || market.includes('1. gol') || market.includes('first goal');
        const isFirstGoalNoGoalPick = isFirstGoalMarket && (pick.includes('egyik sem') || pick.includes('none') || pick.includes('nincs gol') || pick.includes('no goal'));
        const isTeamToScoreNoGoalPick = isTeamToScore && (pick.includes('egyik sem') || pick.includes('none') || pick.includes('nincs gol') || pick.includes('no goal'));
        const isGoalsRangeNoGoalPick = isGoalsRangeMarket && (pick.includes('nincs gol') || pick.includes('no goal') || pick.includes('egyik sem') || pick.includes('none'));
        const isTotalGoalsExactZeroPick = isTotalGoalsMarket && /^0(?:[\.,]0+)?$/.test(pick);
        const isNoGoalEquivalentPick = isUnderHalfGoalTotal || isFirstGoalNoGoalPick || isTeamToScoreNoGoalPick || isGoalsRangeNoGoalPick || isTotalGoalsExactZeroPick;
        const isFirstGoalHowOrTimingMarket = isFirstGoalHowMarket || isFirstGoalTime10Market || isFirstGoalTime15Market;
        const isLastGoalHomeAwayPick = isLastGoalMarket && (isHomePick || isAwayPick || pick.includes('hazai') || pick.includes('vendeg') || pick.includes('home') || pick.includes('away'));
        const isLastGoalNoGoalPick = isLastGoalMarket && (pick.includes('egyik sem') || pick.includes('none') || pick.includes('nincs gol') || pick.includes('no goal'));
        const isWillBeGoalMarketSelection =
            (isBtts && isYesPick) ||
            (isTeamToScore && !isNoPick && !isTeamToScoreNoGoalPick) ||
            (isTotalGoalsMarket && isOverPick && lineValue !== null && lineValue >= 0) ||
            (isFirstGoalMarket && !isFirstGoalNoGoalPick) ||
            isLastGoalHomeAwayPick ||
            isPenaltyGoalMarket ||
            (isOwnGoalMarket && isYesPick) ||
            (isSubScorerMarket && isYesPick) ||
            ((market.includes('lesz gol') || market.includes('will there be a goal')) && isYesPick);

        return {
            is1X2,
            isPlain1X2Market,
            isAnyRestricted1X2ComboMarket,
            isCorrectScore,
            isGoalRelated,
            isTotalGoalsMarket,
            isTeamGoalsMarket,
            isBtts,
            isHalfTimeFullTime,
            isHalfTime,
            isHandicap,
            isDoubleChance,
            isDrawNoBet,
            isPlayerToScore,
            isFirstLastGoal,
            isWinToNil,
            isGeneralScoringMarket,
            isOverPick,
            isUnderPick,
            isYesPick,
            isNoPick,
            isUnderHalfGoalTotal,
            isUnderOneHalfGoalTotal,
            isOverHalfGoalTotal,
            isPositiveTotalGoalsLine,
            isOddGoalsMarket,
            isTeamToScoreHomePick,
            isTeamWinPick,
            isDrawPick,
            isDoubleChanceHomeAwayPick,
            isFirstGoalNoGoalPick,
            isTeamToScoreNoGoalPick,
            isGoalsRangeNoGoalPick,
            isTotalGoalsExactZeroPick,
            isNoGoalEquivalentPick,
            isFirstGoalHowMarket,
            isFirstGoalHowOrTimingMarket,
            isLastGoalHomeAwayPick,
            isLastGoalNoGoalPick,
            isWillBeGoalMarketSelection,
            lineValue
        };
    }

    function isSameSelectionEvent(itemA, itemB) {
        const aMatchId = parseInt(itemA && itemA.matchId, 10) || 0;
        const bMatchId = parseInt(itemB && itemB.matchId, 10) || 0;

        if (aMatchId > 0 && bMatchId > 0) {
            return aMatchId === bMatchId;
        }

        if (aMatchId > 0 || bMatchId > 0) {
            return false;
        }

        return normalizeComboText(itemA && itemA.homeTeam) === normalizeComboText(itemB && itemB.homeTeam) &&
               normalizeComboText(itemA && itemA.awayTeam) === normalizeComboText(itemB && itemB.awayTeam);
    }

    function getMarketComboConflictMessage(itemA, itemB) {
        if (!isSameSelectionEvent(itemA, itemB)) return null;

        if (isOddsPlusSingleOnlyMarket(itemA && itemA.market) || isOddsPlusSingleOnlyMarket(itemB && itemB.market)) {
            return 'Kötés tiltás: az 1X2 - Odds+ ugyanazon a meccsen más piaccal nem köthető.';
        }

        const a = analyzeComboSelection(itemA);
        const b = analyzeComboSelection(itemB);
        const aNoGoalEquivalent = a.isNoGoalEquivalentPick;
        const bNoGoalEquivalent = b.isNoGoalEquivalentPick;

        if (a.isCorrectScore || b.isCorrectScore) {
            return 'Kötés tiltás: pontos eredmény piac más piaccal nem köthető ugyanazon a meccsen.';
        }

        if ((a.isPlain1X2Market && b.isAnyRestricted1X2ComboMarket) || (b.isPlain1X2Market && a.isAnyRestricted1X2ComboMarket)) {
            return 'Kötés tiltás: 1X2 piac nem köthető 1X2 kombinált gól/BTTS piaccal ugyanazon a meccsen.';
        }

        if ((a.is1X2 && b.isDoubleChance) || (b.is1X2 && a.isDoubleChance)) {
            return 'Kötés tiltás: 1X2 és Kétesély piac nem köthető ugyanazon a meccsen.';
        }

        if ((a.is1X2 && b.isHandicap) || (b.is1X2 && a.isHandicap)) {
            return 'Kötés tiltás: 1X2 és hendikep piac nem köthető ugyanazon a meccsen.';
        }

        if ((a.is1X2 && b.isDrawNoBet) || (b.is1X2 && a.isDrawNoBet)) {
            return 'Kötés tiltás: 1X2 és Döntetlennél a tét vissza jár piac nem köthető ugyanazon a meccsen.';
        }

        if ((a.isUnderHalfGoalTotal && b.isDoubleChanceHomeAwayPick) || (b.isUnderHalfGoalTotal && a.isDoubleChanceHomeAwayPick)) {
            return 'Kötés tiltás: Gólok száma 0,5 alatt nem köthető Kétesély Hazai vagy Vendég (12) opcióval ugyanazon a meccsen.';
        }

        if ((a.isUnderHalfGoalTotal && b.isDrawNoBet) || (b.isUnderHalfGoalTotal && a.isDrawNoBet)) {
            return 'Kötés tiltás: Gólok száma 0,5 alatt nem köthető a Döntetlennél a tét vissza jár piac opcióival ugyanazon a meccsen.';
        }

        if ((a.isTeamWinPick && bNoGoalEquivalent) || (b.isTeamWinPick && aNoGoalEquivalent)) {
            return 'Kötés tiltás: Hazai/Vendég győzelem nem köthető 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piaccal ugyanazon a meccsen.';
        }

        if ((a.isBtts && a.isYesPick && bNoGoalEquivalent) || (b.isBtts && b.isYesPick && aNoGoalEquivalent)) {
            return 'Kötés tiltás: BTTS Igen nem köthető 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piaccal ugyanazon a meccsen.';
        }

        if ((a.isBtts && a.isYesPick && b.isUnderOneHalfGoalTotal) || (b.isBtts && b.isYesPick && a.isUnderOneHalfGoalTotal)) {
            return 'Kötés tiltás: BTTS Igen nem köthető a gólok száma 1,5 alatt piaccal ugyanazon a meccsen.';
        }

        if ((a.isBtts && a.isNoPick && bNoGoalEquivalent) || (b.isBtts && b.isNoPick && aNoGoalEquivalent)) {
            return 'Kötés tiltás: BTTS Nem nem köthető 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piaccal ugyanazon a meccsen.';
        }

        if (aNoGoalEquivalent && b.isLastGoalHomeAwayPick) {
            return 'Kötés tiltás: 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piac nem köthető Utolsó gól (Hazai/Vendég) piaccal ugyanazon a meccsen.';
        }

        if (bNoGoalEquivalent && a.isLastGoalHomeAwayPick) {
            return 'Kötés tiltás: 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piac nem köthető Utolsó gól (Hazai/Vendég) piaccal ugyanazon a meccsen.';
        }

        if (aNoGoalEquivalent && b.isPositiveTotalGoalsLine) {
            return 'Kötés tiltás: 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piac nem köthető 0-nál nagyobb gólok száma piaccal ugyanazon a meccsen.';
        }

        if (bNoGoalEquivalent && a.isPositiveTotalGoalsLine) {
            return 'Kötés tiltás: 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piac nem köthető 0-nál nagyobb gólok száma piaccal ugyanazon a meccsen.';
        }

        if (aNoGoalEquivalent && b.isOddGoalsMarket) {
            return 'Kötés tiltás: 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piac nem köthető a gólok száma páros/páratlan piaccal ugyanazon a meccsen.';
        }

        if (bNoGoalEquivalent && a.isOddGoalsMarket) {
            return 'Kötés tiltás: 0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem piac nem köthető a gólok száma páros/páratlan piaccal ugyanazon a meccsen.';
        }

        if (a.isFirstGoalHowMarket && !bNoGoalEquivalent) {
            return 'Kötés tiltás: Hogyan szerzik az 1. gólt? piac csak a nincs-gól típusú (0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem) kimenetellel köthető ugyanazon a meccsen.';
        }

        if (b.isFirstGoalHowMarket && !aNoGoalEquivalent) {
            return 'Kötés tiltás: Hogyan szerzik az 1. gólt? piac csak a nincs-gól típusú (0,5 alatt / 1. gól Egyik sem / Melyik csapat szerez gólt: Egyik sem) kimenetellel köthető ugyanazon a meccsen.';
        }

        if (a.isFirstGoalHowOrTimingMarket && b.isWillBeGoalMarketSelection) {
            return 'Kötés tiltás: 1. gól módja/időzítése piac nem köthető olyan piaccal, ami gól bekövetkezését jelenti ugyanazon a meccsen.';
        }

        if (b.isFirstGoalHowOrTimingMarket && a.isWillBeGoalMarketSelection) {
            return 'Kötés tiltás: 1. gól módja/időzítése piac nem köthető olyan piaccal, ami gól bekövetkezését jelenti ugyanazon a meccsen.';
        }

        if (a.isTotalGoalsMarket && b.isTotalGoalsMarket && ((a.isOverPick && b.isUnderPick) || (a.isUnderPick && b.isOverPick))) {
            return 'Kötés tiltás: fölött/alatt piacok ellentétes irányban nem köthetők ugyanazon a meccsen.';
        }

        if ((a.isBtts && b.isTotalGoalsMarket) || (b.isBtts && a.isTotalGoalsMarket)) {
            const bttsSel = a.isBtts ? a : b;
            const goalsSel = a.isTotalGoalsMarket ? a : b;
            if ((bttsSel.isYesPick && goalsSel.isOverPick) || (bttsSel.isNoPick && goalsSel.isUnderPick)) {
                return 'Kötés tiltás: BTTS és gólok száma kombináció (igen+fölött / nem+alatt) nem köthető ugyanazon a meccsen.';
            }
        }

        if (((a.isHalfTimeFullTime || a.isHalfTime) && b.is1X2) || ((b.isHalfTimeFullTime || b.isHalfTime) && a.is1X2)) {
            return 'Kötés tiltás: félidő és végeredmény piacok nem köthetők együtt ugyanazon a meccsen.';
        }

        if ((a.isPlayerToScore && b.isTeamWinPick) || (b.isPlayerToScore && a.isTeamWinPick)) {
            return 'Kötés tiltás: játékos gólt szerez és csapat nyer kombináció nem köthető ugyanazon a meccsen.';
        }

        if ((a.isTotalGoalsMarket && b.isTeamGoalsMarket) || (b.isTotalGoalsMarket && a.isTeamGoalsMarket)) {
            return 'Kötés tiltás: meccs gólok száma és csapat gólpiac nem köthető ugyanazon a meccsen.';
        }

        if ((a.isBtts && a.isYesPick && b.isTeamToScoreHomePick) || (b.isBtts && b.isYesPick && a.isTeamToScoreHomePick)) {
            return 'Kötés tiltás: BTTS igen és hazai csapat szerez gólt kombináció nem köthető ugyanazon a meccsen.';
        }

        return null;
    }

    function findMarketComboConflictWithCandidate(candidateItem) {
        for (let i = 0; i < ticketItems.length; i++) {
            const item = ticketItems[i];
            const sameExact = item.homeTeam === candidateItem.homeTeam &&
                item.awayTeam === candidateItem.awayTeam &&
                item.market === candidateItem.market &&
                item.pick === candidateItem.pick;
            if (sameExact) continue;

            const conflictMessage = getMarketComboConflictMessage(candidateItem, item);
            if (conflictMessage) {
                return { message: conflictMessage, index: i };
            }
        }
        return null;
    }

    function getFirstTicketMarketComboConflict() {
        for (let i = 0; i < ticketItems.length; i++) {
            for (let j = i + 1; j < ticketItems.length; j++) {
                const conflictMessage = getMarketComboConflictMessage(ticketItems[i], ticketItems[j]);
                if (conflictMessage) {
                    return { message: conflictMessage, indexes: [i, j] };
                }
            }
        }
        return null;
    }

    function hasRestrictedMarketComboViolation() {
        return !!getFirstTicketMarketComboConflict();
    }

    function isCurrentEventContextWithoutMarkets(matchId, visibleSelectionButtonCount) {
        if (!(matchId > 0)) return false;
        if (visibleSelectionButtonCount > 0) return false;
        if (!window.location || !window.location.search) return false;

        const eventIdInUrl = parseInt(new URLSearchParams(window.location.search).get('eventId'), 10) || 0;
        return eventIdInUrl === matchId;
    }

    function findSelectionInMatchDetails(details, item) {
        const markets = Array.isArray(details && details.markets) ? details.markets : [];
        if (!item || markets.length === 0) return null;

        const targetMarketId = parseInt(item.marketId, 10) || 0;
        const targetSelectionId = parseInt(item.selectionId, 10) || 0;
        const targetMarket = normalizeLiveKeyPart(item.market);
        const targetPick = normalizeLiveKeyPart(item.pick);

        // Elsődleges egyeztetés stabil API azonosító alapján.
        if (targetMarketId > 0 && targetSelectionId > 0) {
            for (const market of markets) {
                const marketId = parseInt(market && (market.id || market.marketId), 10) || 0;
                if (marketId !== targetMarketId) continue;

                const selections = Array.isArray(market.selections) ? market.selections : [];
                const selection = selections.find(sel => (parseInt(sel && (sel.id || sel.selectionId), 10) || 0) === targetSelectionId);
                if (selection) {
                    return {
                        selection,
                        market
                    };
                }
            }
        }

        for (const market of markets) {
            const specialVal = market && market.specialValue ? ' (' + market.specialValue + ')' : '';
            const marketRawName = (market && market.name) || '';
            const marketTranslatedName = td(marketRawName);
            const normalizedCandidates = new Set([
                normalizeLiveKeyPart(marketRawName + specialVal),
                normalizeLiveKeyPart(marketTranslatedName + specialVal),
                normalizeLiveKeyPart(marketRawName),
                normalizeLiveKeyPart(marketTranslatedName)
            ]);
            const marketMatches = normalizedCandidates.has(targetMarket);
            if (!marketMatches) {
                continue;
            }

            const selections = Array.isArray(market.selections) ? market.selections : [];
            const selection = selections.find(sel => normalizeLiveKeyPart(sel && sel.name) === targetPick);
            if (selection) {
                return {
                    selection,
                    market
                };
            }
        }

        return null;
    }

    function isMatchFinishedFromDetails(details) {
        const match = details && details.match ? details.match : null;
        if (!match) return false;

        const statusId = parseInt(match.statusId, 10) || 0;
        if (statusId === 3) {
            return true;
        }

        const liveStatus = normalizeLiveKeyPart(match.liveStatus || '');
        const finishedKeywords = ['ended', 'finished', 'full time', 'ft', 'final', 'vege', 'vége'];
        if (finishedKeywords.some(keyword => liveStatus.includes(keyword))) {
            return true;
        }

        return false;
    }

    function isMatchStartedFromDetails(details) {
        const match = details && details.match ? details.match : null;
        if (!match) return false;

        if (match.hasStarted === true) {
            return true;
        }

        if (match.isLive === true) {
            return true;
        }

        if (match.startUtc) {
            const startTs = Date.parse(match.startUtc);
            if (!Number.isNaN(startTs) && startTs <= Date.now()) {
                return true;
            }
        }

        return false;
    }

    async function validateTicketAvailabilityFromApi() {
        if (!Array.isArray(ticketItems) || ticketItems.length === 0) return false;

        const uniqueMatchIds = Array.from(new Set(
            ticketItems
                .map(item => parseInt(item.matchId, 10) || 0)
                .filter(id => id > 0)
        ));

        if (uniqueMatchIds.length === 0) return false;

        const detailResults = await Promise.allSettled(
            uniqueMatchIds.map(matchId =>
                fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + matchId, { cache: 'no-store' })
                    .then(r => (r.ok ? r.json() : null))
                    .catch(() => null)
            )
        );

        const detailsByMatchId = new Map();
        uniqueMatchIds.forEach((matchId, idx) => {
            const settled = detailResults[idx];
            if (settled && settled.status === 'fulfilled' && settled.value) {
                detailsByMatchId.set(matchId, settled.value);
            }
        });

        if (detailsByMatchId.size === 0) return false;

        let changedCount = 0;
        let hasNewUnavailable = false;

        ticketItems = ticketItems.map(item => {
            const matchId = parseInt(item.matchId, 10) || 0;
            if (!(matchId > 0)) return item;

            const details = detailsByMatchId.get(matchId);
            if (!details) return item;

            const wasUnavailable = !!item.marketUnavailable;
            const selectionLookup = findSelectionInMatchDetails(details, item);
            const matchFinished = isMatchFinishedFromDetails(details);
            const matchIsLive = !!(details.match && details.match.isLive);
            const oddsRocketStartedLock = !!(details.match && details.match.isBoosted) && matchIsLive && !matchFinished;
            const hasAnyMarkets = Array.isArray(details.markets) && details.markets.length > 0;

            let nextUnavailable = false;
            let nextReason = null;
            let nextOdds = parseFloat(item.odds) || 0;
            let nextBoosted = !!item.isBoosted;
            let nextOriginalOdds = parseFloat(item.originalOdds) || 0;

            if (oddsRocketStartedLock) {
                nextUnavailable = true;
                nextReason = 'oddsrocket_started';
            } else if (selectionLookup && selectionLookup.selection) {
                const selection = selectionLookup.selection;
                const parsedSelectionOdds = parseFloat(selection.odds) || 0;
                if (parsedSelectionOdds > 0) {
                    nextOdds = parsedSelectionOdds;
                }
                nextBoosted = !!selection.boosted;
                nextOriginalOdds = parseFloat(selection.originalOdds) || 0;

                if (matchFinished || parsedSelectionOdds <= 1) {
                    nextUnavailable = true;
                    nextReason = matchFinished ? 'match_finished' : 'market_closed';
                }
            } else if (matchFinished || hasAnyMarkets) {
                // Napi tippeknél név-eltérés miatt ne zárjuk le vakon a piacot,
                // csak ha a meccs ténylegesen befejeződött.
                if (item.isDailyTip && !matchFinished) {
                    nextUnavailable = !!item.marketUnavailable;
                    nextReason = item.unavailableReason || null;
                } else {
                    nextUnavailable = true;
                    nextReason = matchFinished ? 'match_finished' : 'market_closed';
                }
            }

            const currentOdds = parseFloat(item.odds) || 0;
            const currentBoosted = !!item.isBoosted;
            const currentOriginalOdds = parseFloat(item.originalOdds) || 0;
            const currentReason = item.unavailableReason || null;

            const oddsChanged = Math.abs(currentOdds - nextOdds) > 0.0001;
            const boostedChanged = currentBoosted !== nextBoosted;
            const originalChanged = Math.abs(currentOriginalOdds - nextOriginalOdds) > 0.0001;
            const unavailableChanged = wasUnavailable !== nextUnavailable;
            const reasonChanged = currentReason !== nextReason;

            if (!oddsChanged && !boostedChanged && !originalChanged && !unavailableChanged && !reasonChanged) {
                return item;
            }

            if (!wasUnavailable && nextUnavailable) {
                hasNewUnavailable = true;
            }

            changedCount += 1;
            return {
                ...item,
                odds: nextOdds,
                isBoosted: nextBoosted,
                originalOdds: (nextBoosted && nextOriginalOdds > 0) ? nextOriginalOdds : null,
                marketUnavailable: nextUnavailable,
                unavailableReason: nextReason
            };
        });

        if (changedCount > 0) {
            saveToStorage();
            renderTicket();

            if (hasNewUnavailable) {
                BmbPopup.warning(
                    t('betslip.selectionNotBettable', 'Ez a kimenetel már nem fogadható!'),
                    t('betslip.marketClosedTitle', 'Piac lezárva')
                );
            }

            return true;
        }

        return false;
    }

    function scheduleRemoteAvailabilityValidation(force = false) {
        if (remoteAvailabilityValidationInFlight) return;

        const now = Date.now();
        if (!force && (now - lastRemoteAvailabilityValidationAt) < REMOTE_AVAILABILITY_VALIDATION_MS) {
            return;
        }

        remoteAvailabilityValidationInFlight = true;
        lastRemoteAvailabilityValidationAt = now;

        validateTicketAvailabilityFromApi()
            .catch((e) => {
                console.warn('[BETSLIP] Piac elérhetőség ellenőrzés hiba:', e);
            })
            .finally(() => {
                remoteAvailabilityValidationInFlight = false;
            });
    }

    function collectVisibleOddsBySelection() {
        const oddsMap = new Map();
        const visibleMatchIdsWithMarkets = new Set();
        let visibleSelectionButtonCount = 0;

        const upsertOdds = (homeTeam, awayTeam, market, pick, odds, matchId, isBoosted, originalOdds, marketId, selectionId) => {
            const parsedOdds = parseFloat(odds);
            if (!homeTeam || !awayTeam || !market || !pick || Number.isNaN(parsedOdds)) {
                return;
            }

            const normalizedMatchId = parseInt(matchId, 10) || 0;
            const key = buildTicketOddsKey(homeTeam, awayTeam, market, pick, normalizedMatchId);
            oddsMap.set(key, {
                odds: parsedOdds,
                isBoosted: !!isBoosted,
                originalOdds: parseFloat(originalOdds) || 0,
                marketId: parseInt(marketId, 10) || 0,
                selectionId: parseInt(selectionId, 10) || 0
            });

            // Fallback kulcs matchId nélkül, ha a szelvény elemnél nincs matchId eltárolva.
            if (normalizedMatchId > 0) {
                const fallbackKey = buildTicketOddsKey(homeTeam, awayTeam, market, pick, 0);
                if (!oddsMap.has(fallbackKey)) {
                    oddsMap.set(fallbackKey, {
                        odds: parsedOdds,
                        isBoosted: !!isBoosted,
                        originalOdds: parseFloat(originalOdds) || 0,
                        marketId: parseInt(marketId, 10) || 0,
                        selectionId: parseInt(selectionId, 10) || 0
                    });
                }
            }
        };

        document.querySelectorAll('.selection-btn[data-home][data-away][data-market][data-pick][data-odd]').forEach(btn => {
            visibleSelectionButtonCount += 1;
            const matchId = parseInt(btn.getAttribute('data-match-id'), 10) || 0;
            if (matchId > 0) {
                visibleMatchIdsWithMarkets.add(matchId);
            }

            upsertOdds(
                btn.getAttribute('data-home'),
                btn.getAttribute('data-away'),
                btn.getAttribute('data-market'),
                btn.getAttribute('data-pick'),
                btn.getAttribute('data-odd'),
                matchId,
                btn.hasAttribute('data-boosted'),
                btn.getAttribute('data-original-odd'),
                btn.getAttribute('data-market-id'),
                btn.getAttribute('data-selection-id')
            );
        });

        document.querySelectorAll('.tip-combo-pick[data-home][data-away][data-market][data-pick][data-odd]').forEach(el => {
            upsertOdds(
                el.getAttribute('data-home'),
                el.getAttribute('data-away'),
                el.getAttribute('data-market'),
                el.getAttribute('data-pick'),
                el.getAttribute('data-odd'),
                el.getAttribute('data-event-id'),
                false,
                0,
                el.getAttribute('data-market-id'),
                el.getAttribute('data-selection-id')
            );
        });

        return {
            oddsMap,
            visibleMatchIdsWithMarkets,
            visibleSelectionButtonCount
        };
    }

    function syncTicketOddsWithVisibleSelections() {
        if (!Array.isArray(ticketItems) || ticketItems.length === 0) {
            return false;
        }

        const {
            oddsMap,
            visibleMatchIdsWithMarkets,
            visibleSelectionButtonCount
        } = collectVisibleOddsBySelection();
        if (oddsMap.size === 0) {
            // Akkor is frissítünk, ha részletek oldalon nincs már fogadható piac az adott meccshez.
            let unavailableChanged = false;
            ticketItems = ticketItems.map(item => {
                const matchId = parseInt(item.matchId, 10) || 0;
                if (!isCurrentEventContextWithoutMarkets(matchId, visibleSelectionButtonCount)) {
                    return item;
                }
                if (item.marketUnavailable) {
                    return item;
                }
                unavailableChanged = true;
                return {
                    ...item,
                    marketUnavailable: true,
                    unavailableReason: 'market_closed'
                };
            });

            if (unavailableChanged) {
                saveToStorage();
                renderTicket();
                return true;
            }

            return false;
        }

        let changedCount = 0;

        ticketItems = ticketItems.map(item => {
            const matchId = parseInt(item.matchId, 10) || 0;
            const key = buildTicketOddsKey(item.homeTeam, item.awayTeam, item.market, item.pick, matchId);
            const fallbackKey = matchId > 0
                ? buildTicketOddsKey(item.homeTeam, item.awayTeam, item.market, item.pick, 0)
                : key;

            const visibleOdds = oddsMap.get(key) || oddsMap.get(fallbackKey);
            if (!visibleOdds) {
                const unavailableByMarket = matchId > 0 && visibleMatchIdsWithMarkets.has(matchId);
                const unavailableByCurrentEventState = isCurrentEventContextWithoutMarkets(matchId, visibleSelectionButtonCount);
                if (!unavailableByMarket && !unavailableByCurrentEventState) {
                    return item;
                }

                changedCount += item.marketUnavailable ? 0 : 1;
                return {
                    ...item,
                    marketUnavailable: true,
                    unavailableReason: 'market_closed'
                };
            }

            const currentUnavailable = !!item.marketUnavailable;
            const nextUnavailable = (parseFloat(visibleOdds.odds) || 0) <= 1;
            const currentReason = item.unavailableReason || null;
            const nextReason = nextUnavailable ? 'market_closed' : null;

            if (nextUnavailable && !currentUnavailable) {
                changedCount += 1;
            }
            if (!nextUnavailable && currentUnavailable) {
                changedCount += 1;
            }
            const reasonChanged = currentReason !== nextReason;
            if (reasonChanged) {
                changedCount += 1;
            }

            const currentOdds = parseFloat(item.odds) || 0;
            const nextOdds = parseFloat(visibleOdds.odds) || 0;
            const currentBoosted = !!item.isBoosted;
            const nextBoosted = !!visibleOdds.isBoosted;
            const currentOriginal = parseFloat(item.originalOdds) || 0;
            const nextOriginal = parseFloat(visibleOdds.originalOdds) || 0;

            const oddsChanged = Math.abs(currentOdds - nextOdds) > 0.0001;
            const boostChanged = currentBoosted !== nextBoosted;
            const originalChanged = Math.abs(currentOriginal - nextOriginal) > 0.0001;

            if (!oddsChanged && !boostChanged && !originalChanged && !reasonChanged && (currentUnavailable === nextUnavailable)) {
                return item;
            }

            changedCount += 1;
            return {
                ...item,
                odds: nextOdds,
                isBoosted: nextBoosted,
                originalOdds: (nextBoosted && nextOriginal > 0) ? nextOriginal : null,
                marketId: (parseInt(item.marketId, 10) || 0) > 0 ? item.marketId : (parseInt(visibleOdds.marketId, 10) || 0),
                selectionId: (parseInt(item.selectionId, 10) || 0) > 0 ? item.selectionId : (parseInt(visibleOdds.selectionId, 10) || 0),
                marketUnavailable: nextUnavailable,
                unavailableReason: nextReason
            };
        });

        if (changedCount > 0) {
            console.log('[BETSLIP] Odds szinkron kész, frissült elemek:', changedCount);
            saveToStorage();
            renderTicket();
            return true;
        }

        return false;
    }

    function scheduleVisibleOddsSync() {
        if (oddsSyncScheduled) {
            return;
        }
        oddsSyncScheduled = true;

        window.requestAnimationFrame(() => {
            oddsSyncScheduled = false;
            syncTicketOddsWithVisibleSelections();
        });
    }

    function shouldScheduleOddsSyncFromMutations(mutationsList) {
        for (const mutation of mutationsList) {
            if (mutation.type === 'attributes') {
                const target = mutation.target;
                if (target && target.matches && (target.matches('.selection-btn') || target.matches('.tip-combo-pick'))) {
                    return true;
                }
            }

            if (mutation.type === 'childList') {
                for (const node of mutation.addedNodes) {
                    if (!node || node.nodeType !== 1) continue;
                    if (node.matches && (node.matches('.selection-btn') || node.matches('.tip-combo-pick'))) {
                        return true;
                    }
                    if (node.querySelector && node.querySelector('.selection-btn, .tip-combo-pick')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    // ===== TOGGLE: HOZZÁADÁS / ELTÁVOLÍTÁS =====
    window.toggleOdds = function(homeTeam, awayTeam, pick, odds, market, matchId, isDailyTip, isBoosted, originalOdds, marketId, selectionId) {
        console.log('[BETSLIP] toggleOdds called:', {homeTeam, awayTeam, pick, odds, market, matchId, isDailyTip});
        const normalizedMarketId = parseInt(marketId, 10) || 0;
        const normalizedSelectionId = parseInt(selectionId, 10) || 0;
        
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
            const normalizedOdds = parseFloat(odds) || 0;
            if (normalizedOdds <= 1) {
                BmbPopup.warning('Erre a piacra jelenleg nem lehet fogadni (1.00 odds).', 'Piac lezárva');
                return;
            }

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

            const candidateComboItem = {
                homeTeam: homeTeam,
                awayTeam: awayTeam,
                pick: pick,
                market: market,
                matchId: matchId || 0
            };
            const marketComboConflict = findMarketComboConflictWithCandidate(candidateComboItem);
            if (marketComboConflict) {
                BmbPopup.warning(marketComboConflict.message, 'Kötés tiltás');
                return;
            }

            console.log('[BETSLIP] Hozzáadás:', {homeTeam, awayTeam, pick, odds, market});
            ticketItems.push({
                homeTeam: homeTeam,
                awayTeam: awayTeam,
                pick: pick,
                odds: odds,
                market: market,
                marketId: normalizedMarketId,
                selectionId: normalizedSelectionId,
                matchId: matchId || 0,
                isDailyTip: !!isDailyTip,
                isBoosted: !!isBoosted,
                originalOdds: (isBoosted && parseFloat(originalOdds) > 0) ? parseFloat(originalOdds) : null,
                marketUnavailable: (parseFloat(odds) || 0) <= 1,
                unavailableReason: ((parseFloat(odds) || 0) <= 1) ? 'market_closed' : null,
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
            // Boost indicator eltávolítása
            const boostIndicator = document.getElementById('daily-tip-boost-indicator');
            if (boostIndicator) boostIndicator.style.display = 'none';
            const pyramidIndicator = document.getElementById('odds-pyramid-boost-indicator');
            if (pyramidIndicator) pyramidIndicator.style.display = 'none';
            renderFreeBetOption();
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
        
        const metrics = getTicketMetrics();

        ticketItems.forEach((item, idx) => {
            const currentOdds = parseFloat(item.odds) || 0;
            const oldOdds = parseFloat(item.originalOdds) || 0;
            const isUnavailable = !!item.marketUnavailable;
            const hasBoostedOddsDisplay = !!item.isBoosted && oldOdds > 0;
            const matchId = parseInt(item.matchId, 10) || 0;
            const matchLabel = escapeHtml(td(item.homeTeam)) + ' vs ' + escapeHtml(td(item.awayTeam));
            const oddsHtml = hasBoostedOddsDisplay
                ? `<span class="betslip-boosted-odd-display">
                        <span class="betslip-original-odd-crossed">${formatOddsHu(oldOdds)}</span>
                        <i class="fas fa-rocket betslip-boosted-icon-small" aria-hidden="true"></i>
                        <span class="betslip-boosted-new-odd">${formatOddsHu(currentOdds)}</span>
                   </span>`
                : `<span>${formatOddsHu(currentOdds)}</span>`;

            const el = document.createElement('div');
            el.className = 'betslip-item' + (isUnavailable ? ' betslip-item-unavailable' : '');
            el.innerHTML = `
                <div class="betslip-item-header">
                    ${matchId > 0
                        ? `<button type="button" class="betslip-match-link" data-match-id="${matchId}" title="Meccs megnyitása">${matchLabel}</button>`
                        : `<span>${matchLabel}</span>`}
                    <button class="betslip-remove" data-index="${idx}" title="${t('betslip.remove', 'Eltávolítás')}">×</button>
                </div>
                <div class="betslip-item-market">${escapeHtml(td(item.market))}</div>
                <div class="betslip-item-pick">${escapeHtml(td(item.pick))}</div>
                <div class="betslip-item-odds">${oddsHtml}${item.isDailyTip ? ' <span class="daily-tip-badge">Napi tipp</span>' : ''}</div>
                ${isUnavailable ? `<div class="betslip-item-warning"><i class="fas fa-lock"></i> ${t('betslip.selectionNotBettable', 'Ez a kimenetel már nem fogadható!')}</div>` : ''}
            `;
            betsContainer.appendChild(el);
        });

        if (hasBoostedSingleOnlyViolation()) {
            const warningEl = document.createElement('div');
            warningEl.className = 'betslip-item-warning betslip-combo-lock-warning';
            warningEl.innerHTML = '<i class="fas fa-ban"></i> Kötés tiltás miatt nem lehet fogadni! Az Oddsűrhajó csak single tétben fogadható.';
            betsContainer.appendChild(warningEl);
        }

        // Frissítjük az összes elemet az oldalon
        if (betslipCountEl) betslipCountEl.textContent = ticketItems.length;
        const totalOddsEl = document.getElementById('total-odds');
        if (totalOddsEl) totalOddsEl.textContent = metrics.totalOdds.toFixed(3);

        // 1.2x napi tipp boost kijelző
        const summaryEl = document.getElementById('betslip-summary');
        const boostIndicator = document.getElementById('daily-tip-boost-indicator');
        if (boostIndicator) {
            boostIndicator.style.display = metrics.hasDailyTip ? 'block' : 'none';
        } else if (metrics.hasDailyTip && summaryEl) {
            const indicator = document.createElement('div');
            indicator.id = 'daily-tip-boost-indicator';
            indicator.className = 'daily-tip-boost-indicator';
            indicator.innerHTML = '<i class="fas fa-bolt"></i> Napi tipp bónusz: 1.2x szorzó aktív!';
            summaryEl.parentNode.insertBefore(indicator, summaryEl);
        }

        // Oddspiramis kijelző — mindig látszik ha van tétel, mutatja a haladást
        renderOddsPyramidIndicator(metrics, summaryEl);

        updatePotentialWin(metrics.totalOdds);
        renderFreeBetOption();
        updatePlaceBetButton();
        updateTypeTabs();
        updateBetslipBalanceDisplay();
        
        console.log('[BETSLIP] renderTicket() vége, totalOdds:', metrics.totalOdds, 'hasDailyTip:', metrics.hasDailyTip);
    }

    // ===== REMOVE BUTTON - delegated event listener =====
    document.addEventListener('click', (e) => {
        const matchLink = e.target.closest('.betslip-match-link[data-match-id]');
        if (matchLink) {
            e.preventDefault();
            e.stopPropagation();
            openMatchDetailsFromBetslip(matchLink.getAttribute('data-match-id'));
            return;
        }

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

    window.addEventListener('languageChanged', function() {
        renderTicket();
    });

    // ===== ODDSPIRAMIS INDIKÁTOR =====
    function renderOddsPyramidIndicator(metrics, summaryEl) {
        let indicator = document.getElementById('odds-pyramid-boost-indicator');
        const parentEl = summaryEl ? summaryEl.parentNode : null;
        if (!parentEl) return;

        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'odds-pyramid-boost-indicator';
            parentEl.insertBefore(indicator, summaryEl);
        }

        const count = metrics.selectionCount;
        const needed = 6;
        const remaining = Math.max(0, needed - count);
        const progress = Math.min(count / needed, 1);

        if (count > 0 && count < needed) {
            // Haladás — még nem 6
            indicator.className = 'odds-pyramid-indicator pyramid-progress';
            indicator.innerHTML = 
                '<div class="pyramid-info-row">' +
                    '<i class="fas fa-layer-group"></i> ' +
                    '<span>' + t('betslip.oddsPyramidProgress', 'Oddspiramis: még <strong>{n}</strong> fogadás kell az 1.3x bónuszhoz!').replace('{n}', remaining) + '</span>' +
                '</div>' +
                '<div class="pyramid-progress-bar-wrap">' +
                    '<div class="pyramid-progress-bar" style="width:' + Math.round(progress * 100) + '%"></div>' +
                '</div>' +
                '<div class="pyramid-count">' + count + ' / ' + needed + '</div>';
            indicator.style.display = 'block';
        } else if (metrics.hasOddsPyramid) {
            // Aktív — 6+ fogadás
            indicator.className = 'odds-pyramid-indicator pyramid-active';
            indicator.innerHTML = 
                '<div class="pyramid-info-row">' +
                    '<i class="fas fa-layer-group"></i> ' +
                    '<span>' + t('betslip.oddsPyramidBoost', 'Oddspiramis bónusz: 1.3x szorzó aktív! (6+ fogadás)') + '</span>' +
                '</div>' +
                '<div class="pyramid-progress-bar-wrap">' +
                    '<div class="pyramid-progress-bar" style="width:100%"></div>' +
                '</div>' +
                '<div class="pyramid-count">' + count + ' / ' + needed + ' ✓</div>';
            indicator.style.display = 'block';
        } else {
            indicator.style.display = 'none';
        }
    }

    function updateBetslipBalanceDisplay() {
        const row = document.getElementById('betslip-balance-row');
        const display = document.getElementById('betslip-balance-display');
        const typeRow = document.getElementById('balance-type-row');
        const selectEl = document.getElementById('balance-type-select');

        if (!row || !display) return;
        if (!isLoggedIn) {
            row.style.display = 'none';
            if (typeRow) typeRow.style.display = 'none';
            return;
        }

        const total = userBalance + userBonusBalance;
        row.style.display = 'flex';

        if (userBonusBalance > 0) {
            display.innerHTML = formatFt(userBalance) + ' <span style="color:#7c3aed;"> + ' + formatFt(userBonusBalance) + ' 🎁</span>';
        } else {
            display.textContent = formatFt(total);
        }

        // Bónusz selector dropdown frissítése
        if (typeRow && selectEl) {
            const hasBonusOptions = activeBonusList.length > 0;
            const hasFreeBetOption = isLoggedIn && availableFreeBetAmount > 0 && availableFreeBetId > 0 && isFreeBetTicketEligible();
            const hasAnySourceOption = hasBonusOptions || hasFreeBetOption;

            if (hasAnySourceOption) {
                typeRow.style.display = 'flex';

                // Jelenlegi kiválasztás megjegyzése
                const currentValue = selectEl.value;

                // Dropdown opciók újragenerálása
                selectEl.innerHTML = '';

                // Rendes egyenleg opció
                const realOpt = document.createElement('option');
                realOpt.value = 'real';
                realOpt.textContent = '💰 ' + window.i18n('betslip.realBalance', 'Rendes egyenleg') + ' — ' + formatFt(userBalance);
                realOpt.style.color = '#4caf50';
                selectEl.appendChild(realOpt);

                // Bónusz opciók (minden aktív bónuszhoz külön)
                activeBonusList.forEach(function(bonus) {
                    const opt = document.createElement('option');
                    opt.value = 'bonus_' + bonus.id;
                    opt.textContent = '🎁 ' + bonus.name + ' — ' + formatFt(bonus.balance);
                    opt.style.color = '#7c3aed';
                    selectEl.appendChild(opt);
                });

                // Ingyenes fogadás opció
                if (hasFreeBetOption) {
                    const freeBetOpt = document.createElement('option');
                    freeBetOpt.value = 'freebet';
                    freeBetOpt.textContent = '🎟 Ingyenes fogadás — ' + formatFt(availableFreeBetAmount);
                    freeBetOpt.style.color = '#4fc3f7';
                    selectEl.appendChild(freeBetOpt);
                }

                // Visszaállítás a korábbi kiválasztásra, ha még létezik
                if (currentValue && selectEl.querySelector('option[value="' + currentValue + '"]')) {
                    selectEl.value = currentValue;
                } else {
                    selectEl.value = 'real';
                }

                // Border szín frissítése a kiválasztás alapján
                updateSelectBorderColor();
            } else {
                typeRow.style.display = 'none';
                if (selectEl) selectEl.value = 'real';
            }
        }
    }

    function updateSelectBorderColor() {
        const selectEl = document.getElementById('balance-type-select');
        if (!selectEl) return;
        const val = selectEl.value;
        if (val === 'real') {
            selectEl.style.borderColor = '#4caf50';
        } else if (val === 'freebet') {
            selectEl.style.borderColor = '#4fc3f7';
        } else {
            selectEl.style.borderColor = '#7c3aed';
        }
    }

    function getSelectedBalanceType() {
        const selectEl = document.getElementById('balance-type-select');
        if (!selectEl) return 'real';
        if (selectEl.value === 'freebet') return 'freebet';
        return selectEl.value.startsWith('bonus_') ? 'bonus' : 'real';
    }

    function getSelectedUserBonusId() {
        const selectEl = document.getElementById('balance-type-select');
        if (!selectEl) return 0;
        const val = selectEl.value;
        if (val.startsWith('bonus_')) {
            return parseInt(val.replace('bonus_', ''), 10) || 0;
        }
        return 0;
    }

    function getSelectedBonusBalance() {
        const bonusId = getSelectedUserBonusId();
        if (bonusId <= 0) return 0;
        const bonus = activeBonusList.find(b => b.id === bonusId);
        return bonus ? bonus.balance : 0;
    }

    function updatePotentialWin(totalOdds) {
        const stakeInput = document.getElementById('stake-input');
        const stake = parseFloat(stakeInput.value) || 0;
        const useFreeBet = isUsingFreeBet();
        const grossWin = stake * totalOdds;
        const netFreeBetWin = stake * Math.max(0, totalOdds - 1);
        const win = Math.round(useFreeBet ? netFreeBetWin : grossWin);
        document.getElementById('potential-payout').textContent = 
            win.toLocaleString('hu-HU') + ' Ft';
    }

    const stakeInput = document.getElementById('stake-input');
    if (stakeInput) {
        stakeInput.addEventListener('input', () => {
            const metrics = getTicketMetrics();
            updatePotentialWin(metrics.totalOdds);
        });
    }

    // ===== TICKET ELKÜLDÉSE =====
    const submitBtn = document.getElementById('place-bet-btn');
    
    function updatePlaceBetButton() {
        if (!submitBtn) return;

        if (isTicketSubmitting) {
            submitBtn.disabled = true;
            submitBtn.title = 'Szelvény leadása folyamatban...';
            return;
        }
        
        const stake = parseFloat(document.getElementById('stake-input')?.value) || 0;
        const useFreeBet = isUsingFreeBet();
        const freeBetEligible = isFreeBetTicketEligible();
        const freeBetCoversStake = useFreeBet && freeBetEligible && availableFreeBetAmount >= stake && availableFreeBetId > 0;
        const balanceType = getSelectedBalanceType();
        const activeBalance = balanceType === 'bonus' ? getSelectedBonusBalance() : userBalance;
        const unavailableItems = getUnavailableTicketItemsCount();
        const comboLockViolation = hasAnySingleOnlyComboViolation();
        const restrictedMarketComboViolation = hasRestrictedMarketComboViolation();
        const marketComboConflict = getFirstTicketMarketComboConflict();
        
        // Letiltás feltételei:
        if (!isLoggedIn || ticketItems.length === 0 || unavailableItems > 0 || comboLockViolation || restrictedMarketComboViolation || (!freeBetCoversStake && (activeBalance === 0 || activeBalance < stake))) {
            submitBtn.disabled = true;
            if (!isLoggedIn) {
                submitBtn.title = t('betslip.mustLogin', 'Be kell jelentkezned a fogadáshoz');
            } else if (ticketItems.length === 0) {
                submitBtn.title = t('betslip.minOneBet', 'Legalább egy fogadás szükséges');
            } else if (unavailableItems > 0) {
                submitBtn.title = t('betslip.selectionNotBettableHint', 'Ez a kimenetel már nem fogadható! Távolítsd el vagy módosítsd a választást.');
            } else if (comboLockViolation) {
                submitBtn.title = 'Kötés tiltás miatt nem lehet fogadni! Az Oddsűrhajó csak single tétben fogadható.';
            } else if (restrictedMarketComboViolation) {
                submitBtn.title = marketComboConflict ? marketComboConflict.message : 'Kötés tiltás miatt ez a szelvény nem adható fel.';
            } else if (useFreeBet && !freeBetCoversStake) {
                submitBtn.title = 'Az ingyenes fogadás feltételei vagy összege nem megfelelő ehhez a szelvényhez.';
            } else if (activeBalance === 0) {
                submitBtn.title = balanceType === 'bonus'
                    ? 'Nincs elegendő bónusz egyenleg!'
                    : t('betslip.noBalance', 'Nincs elegendő egyenleg! Kérjük, töltsd fel az accountot.');
            } else if (activeBalance < stake) {
                submitBtn.title = balanceType === 'bonus'
                    ? 'A bónusz egyenleg nem elég ehhez a téthez!'
                    : t('betslip.insufficientStakeBalance', 'Nincs elegendő egyenleg az adott téthez!');
            }
        } else {
            submitBtn.disabled = false;
            submitBtn.title = '';
        }
    }
    
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            if (isTicketSubmitting) return;
            if (ticketItems.length === 0) return;
            
            const stake = parseFloat(document.getElementById('stake-input').value) || 0;
            const useFreeBet = isUsingFreeBet();
            const minBet = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_bet_amount) || 100;
            if (stake < minBet) {
                BmbPopup.warning(t('betslip.minStakeMsg', 'Minimum tét: ' + minBet + ' Ft'), t('betslip.invalidStakeTitle', 'Érvénytelen tét'));
                return;
            }

            if (!isLoggedIn) {
                BmbPopup.info(t('betslip.loginRequiredMsg', 'A fogadáshoz be kell jelentkezned!'), t('betslip.loginRequiredTitle', 'Bejelentkezés szükséges'));
                return;
            }

            if (getUnavailableTicketItemsCount() > 0) {
                BmbPopup.warning(
                    t('betslip.selectionNotBettable', 'Ez a kimenetel már nem fogadható!'),
                    t('betslip.marketUnavailableTitle', 'Nem fogadható piac')
                );
                return;
            }

            if (hasAnySingleOnlyComboViolation()) {
                BmbPopup.warning(
                    'Kötés tiltás miatt nem lehet fogadni! Az Oddsűrhajó csak single tétben fogadható.',
                    'Kötés tiltás'
                );
                return;
            }

            const submitConflict = getFirstTicketMarketComboConflict();
            if (submitConflict) {
                BmbPopup.warning(submitConflict.message, 'Kötés tiltás');
                return;
            }

            if (useFreeBet) {
                if (!(availableFreeBetId > 0 && isFreeBetTicketEligible() && availableFreeBetAmount >= stake)) {
                    BmbPopup.warning('Az ingyenes fogadás feltételei vagy összege nem megfelelő ehhez a szelvényhez.', 'Ingyenes fogadás hiba');
                    return;
                }
            } else {
                const balanceType = getSelectedBalanceType();
                const activeBalance = balanceType === 'bonus' ? getSelectedBonusBalance() : userBalance;
                if (activeBalance === 0 || activeBalance < stake) {
                    const msg = balanceType === 'bonus'
                        ? 'Nincs elegendő bónusz egyenleg!'
                        : t('betslip.noBalance', 'Nincs elegendő egyenleg! Kérjük, töltsd fel az accountot.');
                    BmbPopup.warning(msg, t('betslip.noMoneyTitle', 'Nincs elegendő pénz'));
                    return;
                }
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
        if (isTicketSubmitting) return;
        isTicketSubmitting = true;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.title = 'Szelvény leadása folyamatban...';
        }

        const metrics = getTicketMetrics();
        const totalOdds = metrics.totalOdds;
        const useFreeBet = isUsingFreeBet();
        const balanceType = getSelectedBalanceType();
        const useBonus = (!useFreeBet && balanceType === 'bonus');
        const userBonusId = useBonus ? getSelectedUserBonusId() : 0;

        const payload = {
            stake: stake,
            totalOdds: totalOdds,
            potentialWin: Math.round(useFreeBet ? (stake * Math.max(0, totalOdds - 1)) : (stake * totalOdds)),
            items: ticketItems,
            useFreeBet: useFreeBet,
            useBonus: useBonus,
            userBonusId: userBonusId,
            freeBetUserBonusId: parseInt(useFreeBet ? availableFreeBetId : 0, 10) || 0,
            hasDailyTipBoost: metrics.hasDailyTip,
            hasOddsPyramidBoost: metrics.hasOddsPyramid
        };

        fetch('../../backend/ApiRequest/submit_ticket.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                const usedFreeBet = !!data.free_bet_used;
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
                                    ${usedFreeBet ? `<p><strong>Ingyenes fogadás:</strong> ${stake.toLocaleString('hu-HU')} Ft</p>` : ''}
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
                const possibleWin = Math.round(usedFreeBet ? (stake * Math.max(0, totalOdds - 1)) : (stake * totalOdds));
                const usedBonusBet = !!payload.useBonus;
                successModal.querySelector('.modal-body').innerHTML = `
                    <p><strong>${t('betslip.stakeLabel', 'Tét:')}</strong> ${stake.toLocaleString('hu-HU')} Ft</p>
                    ${usedFreeBet ? `<p><strong>Ingyenes fogadás:</strong> ${stake.toLocaleString('hu-HU')} Ft</p>` : ''}
                    ${usedBonusBet ? `<p><strong style="color:#7c3aed;">🎁 Bónusz egyenlegből</strong></p>` : ''}
                    <p><strong>${t('betslip.potentialWinLabel', 'Lehetséges nyeremény:')}</strong> ${possibleWin.toLocaleString('hu-HU')} Ft</p>
                `;
                bsSuccessModal.show();

                ticketItems = [];
                saveToStorage();
                renderTicket();
                refreshAllOddsButtons();

                if (usedFreeBet) {
                    availableFreeBetAmount = Math.max(0, availableFreeBetAmount - stake);
                    if (availableFreeBetAmount <= 0) {
                        availableFreeBetId = 0;
                    }
                }

                // Reset balance type to real after submission
                const selectEl = document.getElementById('balance-type-select');
                if (selectEl) selectEl.value = 'real';

                // Azonnali lokális egyenlegfrissítés (jobb felső sarok)
                if (typeof data.new_balance === 'number' && !Number.isNaN(data.new_balance)) {
                    userBalance = data.new_balance;
                } else {
                    userBalance = Math.max(0, (parseFloat(userBalance) || 0) - stake);
                }
                if (typeof data.new_bonus_balance === 'number' && !Number.isNaN(data.new_bonus_balance)) {
                    userBonusBalance = data.new_bonus_balance;
                }

                const walletEl = document.getElementById('sessionBetDisplay');
                if (walletEl) {
                    walletEl.textContent = userBalance.toLocaleString('hu-HU', {
                        maximumFractionDigits: 0,
                        minimumFractionDigits: 0
                    }) + ' FT';
                }

                // Bónusz egyenleg frissítése a fejlécben
                const bonusBadge = document.getElementById('bonusBalanceBadge');
                const bonusDisplay = document.getElementById('bonusBalanceDisplay');
                if (bonusBadge && bonusDisplay) {
                    if (userBonusBalance > 0) {
                        bonusBadge.style.display = '';
                        bonusDisplay.textContent = userBonusBalance.toLocaleString('hu-HU', { maximumFractionDigits: 0, minimumFractionDigits: 0 }) + ' FT';
                    } else {
                        bonusBadge.style.display = 'none';
                    }
                }

                updatePlaceBetButton();
                updateBetslipBalanceDisplay();
                document.dispatchEvent(new CustomEvent('balance:changed'));

                // Biztos frissítés szerverről cache nélkül, hogy ne maradjon beragadt érték
                fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' })
                    .then(r => r.json())
                    .then(me => {
                        if (me && me.loggedIn && me.user) {
                            const freshBalance = parseFloat(me.user.balance) || 0;
                            userBalance = freshBalance;
                            userBonusBalance = parseFloat(me.user.bonus_balance) || 0;

                            const liveWalletEl = document.getElementById('sessionBetDisplay');
                            if (liveWalletEl) {
                                liveWalletEl.textContent = freshBalance.toLocaleString('hu-HU', {
                                    maximumFractionDigits: 0,
                                    minimumFractionDigits: 0
                                }) + ' FT';
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
        })
        .finally(() => {
            isTicketSubmitting = false;
            updatePlaceBetButton();
        });
    }

    document.addEventListener('change', (e) => {
        if (e.target && e.target.id === 'use-freebet-toggle') {
            applyFreeBetSelectionState();
        }
        if (e.target && e.target.id === 'balance-type-select') {
            const toggle = document.getElementById('use-freebet-toggle');
            if (toggle) {
                toggle.checked = e.target.value === 'freebet';
            }
            applyFreeBetSelectionState();
            updateSelectBorderColor();
            updatePlaceBetButton();
        }
    });

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
        if (!isLoggedIn) {
            bettingHistory = [];
            renderHistory();
            manageBackgroundCheck();
            return;
        }

        if (isHistoryLoading) {
            return;
        }

        if (historyAbortController) {
            historyAbortController.abort();
        }
        historyAbortController = new AbortController();
        isHistoryLoading = true;

        fetch('../../backend/ApiRequest/get_betting_history.php', {
            signal: historyAbortController.signal,
            cache: 'no-store'
        })
            .then(r => {
                if (r.status === 401) {
                    throw new Error('UNAUTHORIZED');
                }
                return r.json();
            })
            .then(data => {
                if (data.status === 'ok') {
                    const oldHistory = bettingHistory;
                    bettingHistory = data.history || [];
                    historyLoadedOnce = true;
                    renderHistory();

                    // Státuszváltozás detektálás → popup értesítés
                    if (oldHistory.length > 0) {
                        detectStatusChanges(oldHistory, bettingHistory);
                    }

                    // Háttér-ellenőrzés indítása/leállítása nyitott szelvények alapján
                    manageBackgroundCheck();
                }
            })
            .catch(e => {
                if (e && e.name === 'AbortError') return;

                if (String(e && e.message) === 'UNAUTHORIZED') {
                    isLoggedIn = false;
                    bettingHistory = [];
                    renderHistory();
                    manageBackgroundCheck();
                    return;
                }

                console.error('Előzmények hiba:', e);
            })
            .finally(() => {
                isHistoryLoading = false;
            });
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
        if (!isLoggedIn) {
            if (historyCheckTimer) {
                clearInterval(historyCheckTimer);
                historyCheckTimer = null;
            }
            manageCashoutLiveRefresh();
            return;
        }

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

        manageCashoutLiveRefresh();
    }

    // ===== FOGADÁSI ELŐZMÉNYEK RENDERELÉS =====
    function renderHistory() {
        const container = document.getElementById('elozmeny-items');
        const empty = document.getElementById('elozmeny-empty');
        const pagination = document.getElementById('elozmeny-pagination');
        const prevBtn = document.getElementById('elozmeny-prev-btn');
        const nextBtn = document.getElementById('elozmeny-next-btn');
        const pageInfo = document.getElementById('elozmeny-page-info');

        if (!container || !empty) return;

        if (bettingHistory.length === 0) {
            empty.style.display = 'flex';
            container.style.display = 'none';
            if (pagination) pagination.style.display = 'none';
            return;
        }

        const totalPages = Math.max(1, Math.ceil(bettingHistory.length / HISTORY_PAGE_SIZE));
        currentHistoryPage = Math.min(Math.max(currentHistoryPage, 1), totalPages);
        const startIndex = (currentHistoryPage - 1) * HISTORY_PAGE_SIZE;
        const visibleHistory = bettingHistory.slice(startIndex, startIndex + HISTORY_PAGE_SIZE);

        empty.style.display = 'none';
        container.style.display = 'flex';
        container.innerHTML = '';
        container.scrollTop = 0;

        visibleHistory.forEach(ticket => {
            const isCashout = ticket.status === 'CASHOUT';
            const statusText = isCashout ? '💰 Cash Out' :
                              ticket.status === 'OPEN' ? '⏳ ' + t('betslip.pending', 'Függőben') : 
                              ticket.status === 'WON' ? '✅ ' + t('betslip.won', 'Nyertes') : 
                              ticket.status === 'LOST' ? '❌ ' + t('betslip.lost', 'Vesztes') : 
                              ticket.status === 'VOID' ? '↩️ ' + t('betslip.void', 'Visszatérítve') : '❓ ' + t('mainMenu.unknown', 'Ismeretlen');
            
            const statusClass = ticket.status.toLowerCase();
            
            let itemsHtml = '';
            if (ticket.items && ticket.items.length > 0) {
                ticket.items.forEach(item => {
                    const itemStatus = item.status || 'OPEN';
                    const itemIcon = itemStatus === 'WON' ? '✅' : 
                                     itemStatus === 'LOST' ? '❌' : 
                                     itemStatus === 'CASHOUT' ? '💰' :
                                     itemStatus === 'VOID' ? '↩️' : '⏳';
                    const itemStatusClass = itemStatus.toLowerCase();
                    const liveKey = buildSelectionLiveKey(item);
                    
                    const navMatchId = item.event_id || item.match_id || null;
                    const clickable = navMatchId ? 'elozmeny-match-clickable' : '';
                    const dataAttr = navMatchId ? `data-match-id="${navMatchId}"` : '';
                    itemsHtml += `
                        <div class="elozmeny-item-entry ${itemStatusClass}" data-ticket-id="${ticket.id}" data-live-key="${escapeHtml(liveKey)}">
                            <div class="elozmeny-match ${clickable}" ${dataAttr}>
                                <span class="item-status-icon">${itemIcon}</span>
                                ${escapeHtml(item.homeTeam)} vs ${escapeHtml(item.awayTeam)}
                                ${navMatchId ? '<i class="fas fa-external-link-alt elozmeny-match-link-icon"></i>' : ''}
                            </div>
                            <div class="elozmeny-market">${escapeHtml(item.market)}</div>
                            <div class="elozmeny-pick">${t('betslip.tipLabel', 'Tipp:')} <strong>${escapeHtml(localizePickText(item.pick))}</strong> @ ${parseFloat(item.odds).toFixed(2)}</div>
                            <div class="elozmeny-live-meta" style="display:none"></div>
                        </div>
                    `;
                });
            }

            // Cashout gomb OPEN szelvényekhez
            let cashoutHtml = '';
            if (ticket.status === 'OPEN') {
                cashoutHtml = `
                    <div class="cashout-section" id="cashout-section-${ticket.id}">
                        <button class="cashout-btn" data-ticket-id="${ticket.id}" onclick="window.BetslipCashout.loadPreview(${ticket.id})">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span class="cashout-btn-text">Cash Out</span>
                            <span class="cashout-btn-amount" id="cashout-amount-${ticket.id}"></span>
                        </button>
                    </div>
                `;
            }

            // Cashout badge a már cash out-olt szelvényeknél
            let cashoutBadgeHtml = '';
            if (isCashout && ticket.cashout_amount !== null) {
                cashoutBadgeHtml = `
                    <div class="cashout-badge">
                        <i class="fas fa-check-circle"></i>
                        Cash out: <strong>${parseFloat(ticket.cashout_amount).toLocaleString('hu-HU')} Ft</strong>
                    </div>
                `;
            }

            // Nyertes/vesztes/cashout szín
            const wonClass = ticket.status === 'WON' ? ' elozmeny-won' : '';
            const lostClass = ticket.status === 'LOST' ? ' elozmeny-lost' : '';
            const cashoutClass = isCashout ? ' elozmeny-cashout' : '';
            const voidClass = ticket.status === 'VOID' ? ' elozmeny-void' : '';

            const el = document.createElement('div');
            el.className = 'elozmeny-item' + wonClass + lostClass + cashoutClass + voidClass;
            const bonusBadge = ticket.bonus_bet ? '<span class="elozmeny-bonus-badge">🎁 Bónusz</span>' : '';
            const stakeLabel = ticket.free_bet_used
                ? 'Tét, ingyenes fogadás:'
                : t('betslip.stakeLabel', 'Tét:');
            el.innerHTML = `
                <div class="elozmeny-header">
                    <span class="elozmeny-date">${new Date(ticket.created_at).toLocaleString('hu-HU')}${bonusBadge}</span>
                    <span class="elozmeny-status ${statusClass}">${statusText}</span>
                </div>
                <div class="elozmeny-items-list">${itemsHtml}</div>
                ${cashoutBadgeHtml}
                ${cashoutHtml}
                <div class="elozmeny-summary">
                    <span><strong>${stakeLabel}</strong> ${parseFloat(ticket.stake).toLocaleString('hu-HU')} Ft</span>
                    <span><strong>${t('betslip.oddsLabel', 'Odds:')}</strong> ${parseFloat(ticket.total_odds).toFixed(3)}</span>
                    <span class="${ticket.status === 'WON' ? 'won-amount' : ''}"><strong>${ticket.status === 'WON' ? t('betslip.winLabel', 'Nyeremény:') : t('betslip.potentialLabel', 'Potenciális:')}</strong> ${parseFloat(ticket.potential_win).toLocaleString('hu-HU')} Ft</span>
                </div>
            `;
            container.appendChild(el);

            // Automatikus cashout preview betöltése nyitott szelvényekhez
            if (ticket.status === 'OPEN') {
                window.BetslipCashout.loadPreview(ticket.id);
            }
        });

        if (pagination && prevBtn && nextBtn && pageInfo) {
            pagination.style.display = 'flex';
            pageInfo.textContent = currentHistoryPage + ' / ' + totalPages;
            prevBtn.disabled = currentHistoryPage === 1;
            nextBtn.disabled = currentHistoryPage === totalPages;
        }
    }

    // ===== ELŐZMÉNY MECCS KATTINTÁS =====
    document.addEventListener('click', function(e) {
        const matchEl = e.target.closest('.elozmeny-match-clickable');
        if (!matchEl) return;
        const eventId = parseInt(matchEl.getAttribute('data-match-id'), 10);
        if (!eventId) return;

        // Főoldalra navigálunk, ahol a loadMatchDetails fallback-kel kezeli a lejátszott meccseket is
        const mainPath = '../../frontend/MainMenu/MainMenu.php?eventId=' + eventId;
        if (window.location.pathname.includes('/MainMenu/MainMenu.php')) {
            // Már a főoldalon vagyunk
            if (typeof window.loadMatchDetails === 'function') {
                window.loadMatchDetails(eventId);
            } else {
                window.location.href = mainPath;
            }
        } else {
            window.location.href = mainPath;
        }
    });

    // ===== CASHOUT LOGIKA =====
    window.BetslipCashout = {
        // Cashout érték előnézet betöltése
        loadPreview: function(ticketId) {
            const amountEl = document.getElementById('cashout-amount-' + ticketId);
            const sectionEl = document.getElementById('cashout-section-' + ticketId);
            if (!amountEl || !sectionEl) return;

            if (pendingCashoutRequests.has(ticketId)) return;
            pendingCashoutRequests.add(ticketId);

            fetch('../../backend/ApiRequest/cashout_ticket.php?ticket_id=' + ticketId)
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ok' && data.available && data.cashout_amount > 0) {
                        const nextAmount = parseFloat(data.cashout_amount) || 0;
                        const prevAmount = parseFloat(amountEl.getAttribute('data-last-cashout') || '0') || 0;

                        amountEl.textContent = nextAmount.toLocaleString('hu-HU') + ' Ft';
                        amountEl.setAttribute('data-last-cashout', String(nextAmount));

                        amountEl.classList.remove('cashout-amount-up', 'cashout-amount-down');
                        if (prevAmount > 0 && nextAmount !== prevAmount) {
                            amountEl.classList.add(nextAmount > prevAmount ? 'cashout-amount-up' : 'cashout-amount-down');
                        }

                        sectionEl.style.display = 'block';
                        sectionEl.querySelector('.cashout-btn').onclick = function() {
                            window.BetslipCashout.confirm(ticketId, data.cashout_amount);
                        };

                        applySelectionLiveUpdates(ticketId, data.selection_updates || []);
                    } else {
                        // Cashout nem elérhető → elrejtjük
                        sectionEl.style.display = 'none';
                        resetSelectionLiveState(ticketId);
                    }
                })
                .catch(() => {
                    sectionEl.style.display = 'none';
                    resetSelectionLiveState(ticketId);
                })
                .finally(() => {
                    pendingCashoutRequests.delete(ticketId);
                });
        },

        // Cashout megerősítés
        confirm: function(ticketId, amount) {
            const msg = 'Biztosan ki szeretnéd venni ' + parseFloat(amount).toLocaleString('hu-HU') + ' Ft-ot?\n\nEz lezárja a szelvényt és az összeg jóváírásra kerül az egyenlegeden.';
            BmbPopup.confirm(msg, function() {
                window.BetslipCashout.execute(ticketId);
            }, null, 'Cash Out');
        },

        // Cashout végrehajtás
        execute: function(ticketId) {
            const btn = document.querySelector('.cashout-btn[data-ticket-id="' + ticketId + '"]');
            if (btn) {
                btn.disabled = true;
                btn.querySelector('.cashout-btn-text').textContent = 'Feldolgozás...';
            }

            fetch('../../backend/ApiRequest/cashout_ticket.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticketId: ticketId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'ok') {
                    BmbPopup.success(
                        'Sikeres Cash Out! ' + parseFloat(data.cashout_amount).toLocaleString('hu-HU') + ' Ft jóváírva.',
                        'Cash Out'
                    );

                    // Egyenleg azonnali frissítése
                    if (typeof data.new_balance === 'number' && !Number.isNaN(data.new_balance)) {
                        userBalance = data.new_balance;
                        const walletEl = document.getElementById('sessionBetDisplay');
                        if (walletEl) {
                            walletEl.textContent = userBalance.toLocaleString('hu-HU', {
                                maximumFractionDigits: 0,
                                minimumFractionDigits: 0
                            }) + ' FT';
                        }
                    }
                    document.dispatchEvent(new CustomEvent('balance:changed'));

                    loadBettingHistory();
                    checkLoginStatus();
                } else {
                    BmbPopup.error(data.message || 'Cash out sikertelen', 'Hiba');
                    if (btn) {
                        btn.disabled = false;
                        btn.querySelector('.cashout-btn-text').textContent = 'Cash Out';
                    }
                }
            })
            .catch(() => {
                BmbPopup.error('Szerverhiba! Próbáld újra később.', 'Hiba');
                if (btn) {
                    btn.disabled = false;
                    btn.querySelector('.cashout-btn-text').textContent = 'Cash Out';
                }
            });
        }
    };

    // ===== ODDS GOMBOK FRISSÍTÉSE =====
    window.refreshAllOddsButtons = function(delay = 0) {
        const doRefresh = () => {
            console.log('[BETSLIP] refreshAllOddsButtons() - gombok keresése...');
            
            const buttons = document.querySelectorAll('.selection-btn');
            console.log('[BETSLIP] Talált .selection-btn gombok:', buttons.length);
            
            buttons.forEach(btn => {
                const home = btn.getAttribute('data-home');
                const away = btn.getAttribute('data-away');
                const pick = btn.getAttribute('data-pick');
                const market = btn.getAttribute('data-market');
                const odds = parseFloat(btn.getAttribute('data-odd')) || 0;

                if (!home || !away || !pick || !market) {
                    console.warn('[BETSLIP] Hiányzó adat egy gombnál:', {home, away, pick, market});
                    return;
                }

                const matchId = parseInt(btn.getAttribute('data-match-id'), 10) || 0;
                const state = window.BetslipLogic.getButtonState(home, away, pick, market, matchId);
                const isLockedByOdds = odds <= 1;
                
                btn.classList.remove('active', 'disabled', 'market-locked');
                btn.removeAttribute('disabled');

                if (isLockedByOdds) {
                    btn.classList.add('disabled', 'market-locked');
                    btn.setAttribute('disabled', 'disabled');
                    return;
                }

                if (state === 'active') {
                    btn.classList.add('active');
                    console.log('[BETSLIP] Gomb active:', `${home} vs ${away} - ${pick}`);
                } else if (state === 'disabled') {
                    btn.classList.add('disabled');
                    btn.setAttribute('disabled', 'disabled');
                    console.log('[BETSLIP] Gomb disabled:', `${home} vs ${away} - ${pick}`);
                }
            });

            syncTicketOddsWithVisibleSelections();
            scheduleRemoteAvailabilityValidation();
            
            // Tipp gombok szinkronizálása is
            if (typeof window.syncTipButtons === 'function') {
                window.syncTipButtons();
            }

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
                setTimeout(refreshVisibleCashoutPreviews, 200);
            }
        });
    });

    const historyPrevBtn = document.getElementById('elozmeny-prev-btn');
    const historyNextBtn = document.getElementById('elozmeny-next-btn');

    if (historyPrevBtn) {
        historyPrevBtn.addEventListener('click', () => {
            if (currentHistoryPage > 1) {
                currentHistoryPage -= 1;
                renderHistory();
            }
        });
    }

    if (historyNextBtn) {
        historyNextBtn.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(bettingHistory.length / HISTORY_PAGE_SIZE));
            if (currentHistoryPage < totalPages) {
                currentHistoryPage += 1;
                renderHistory();
            }
        });
    }

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
    // Előzményeket csak kérésre (tab nyitáskor) és bejelentkezve töltünk,
    // ezzel elkerüljük a reload utáni párhuzamos, fagyást okozó hívásokat.
    if (isHistoryTabActive()) {
        loadBettingHistory();
    }
    refreshAllOddsButtons();
    scheduleRemoteAvailabilityValidation(true);
    updatePlaceBetButton();

    setInterval(() => {
        scheduleRemoteAvailabilityValidation();
    }, REMOTE_AVAILABILITY_VALIDATION_MS);

    if (window.MutationObserver) {
        const oddsDomObserver = new MutationObserver((mutationsList) => {
            if (shouldScheduleOddsSyncFromMutations(mutationsList)) {
                scheduleVisibleOddsSync();
            }
        });

        oddsDomObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-odd', 'data-original-odd', 'data-market', 'data-pick', 'data-home', 'data-away']
        });
    }

    console.log('[BETSLIP] Kész!');
});
