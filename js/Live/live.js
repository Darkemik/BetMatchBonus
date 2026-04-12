document.addEventListener('DOMContentLoaded', function() {
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));
    const td = (text) => (typeof window.i18nDynamic === 'function' ? window.i18nDynamic(text) : text);
    const matchesContainer = document.getElementById('matches-container');
    const sportsNav = document.getElementById('liveSportsNav');
    let currentSportId = null; // Dynamically set from first live sport
    let userSelectedSportId = null; // Set when user explicitly clicks a sport
    let autoRefreshInterval = null;
    let viewingMatchDetails = false;
    let refreshRequestId = 0;
    let currentDetailEventId = null;

    function applyDynamicTranslations(root) {
        if (!root) return;
        root.querySelectorAll('.country-name, .league-name, .market-name, .sport-name').forEach(el => {
            el.textContent = td(el.textContent);
        });
    }

    // ===== BAJNOKSÁG ÁLLAPOTOK (sportváltás között megmarad) =====
    const visibleLeagueCountPerSport = {}; // { sportId: number } — hány bajnokság látható
    const expandedLeaguesPerSport = {}; // { sportId: Set of leagueId }

    function applyLeagueStates() {
        if (!currentSportId) return;
        const container = document.getElementById('matches-container');
        if (!container) return;

        const hiddenGroups = container.querySelectorAll('.league-hidden-extra');
        const totalHidden = hiddenGroups.length;
        const visibleCount = visibleLeagueCountPerSport[currentSportId] || 5;

        // Mutassuk a látható limitig, a többit rejtsük
        const allGroups = container.querySelectorAll('.league-group');
        let shown = 0;
        allGroups.forEach((group, idx) => {
            if (idx < visibleCount) {
                if (group.classList.contains('league-hidden-extra')) {
                    group.style.display = '';
                }
                shown++;
            }
        });

        // Gomb szöveg frissítése vagy elrejtése
        const loadBtn = container.querySelector('.load-more-leagues-btn');
        if (loadBtn) {
            const stillHidden = allGroups.length - visibleCount;
            if (stillHidden <= 0) {
                loadBtn.style.display = 'none';
            } else {
                loadBtn.style.display = '';
                loadBtn.querySelector('.load-more-count').textContent = stillHidden;
            }

            if (!loadBtn._liveHandlerAttached) {
                loadBtn._liveHandlerAttached = true;
                loadBtn.addEventListener('click', function() {
                    const current = visibleLeagueCountPerSport[currentSportId] || 5;
                    visibleLeagueCountPerSport[currentSportId] = current + 5;
                    applyLeagueStates();
                });
            }
        }

        // Expanded állapotok visszaállítása
        const expandedSet = expandedLeaguesPerSport[currentSportId];
        if (expandedSet && expandedSet.size > 0) {
            allGroups.forEach(group => {
                const lid = group.getAttribute('data-league-id');
                if (expandedSet.has(lid)) {
                    group.classList.add('expanded');
                }
            });
        }

        // League header kattintás figyelése (expanded állapot mentés)
        container.querySelectorAll('.league-header').forEach(header => {
            if (header._liveExpandHandlerAttached) return;
            header._liveExpandHandlerAttached = true;
            header.addEventListener('click', function() {
                const group = this.parentElement;
                const lid = group.getAttribute('data-league-id');
                if (!expandedLeaguesPerSport[currentSportId]) {
                    expandedLeaguesPerSport[currentSportId] = new Set();
                }
                // toggle után a classList már frissült az onclick-ben
                setTimeout(() => {
                    if (group.classList.contains('expanded')) {
                        expandedLeaguesPerSport[currentSportId].add(lid);
                    } else {
                        expandedLeaguesPerSport[currentSportId].delete(lid);
                    }
                }, 0);
            });
        });
    }

    // ===== KERESŐ LOGIKA =====
    let liveSearchTerm = '';

    function initSearchHandlers() {
        const searchInput = document.getElementById('liveSearchInput');
        const searchClear = document.getElementById('liveSearchClear');
        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            liveSearchTerm = this.value.trim().toLowerCase();
            if (searchClear) searchClear.style.display = liveSearchTerm ? 'flex' : 'none';
            applySearchFilter();
        });

        if (searchClear) {
            searchClear.addEventListener('click', function() {
                searchInput.value = '';
                liveSearchTerm = '';
                this.style.display = 'none';
                applySearchFilter();
            });
        }
    }

    function applySearchFilter() {
        const container = document.getElementById('matches-container');
        if (!container) return;

        const leagueGroups = container.querySelectorAll('.league-group');
        if (leagueGroups.length === 0) return;

        const allLoaded = (visibleLeagueCountPerSport[currentSportId] || 5) >= container.querySelectorAll('.league-group').length;

        leagueGroups.forEach(group => {
            // Ha a csoport rejtett és még nem töltöttük be az összest, ne piszkáljuk
            if (!allLoaded && group.classList.contains('league-hidden-extra')) return;

            const leagueTitle = (group.querySelector('.league-title')?.textContent || '').toLowerCase();
            const leagueCountry = (group.querySelector('.league-country')?.textContent || '').toLowerCase();

            // Ha a bajnokság/ország neve tartalmazza a keresőszöveget, az egész csoportot mutatjuk
            const leagueMatch = liveSearchTerm === '' ||
                leagueTitle.includes(liveSearchTerm) ||
                leagueCountry.includes(liveSearchTerm);

            const rows = group.querySelectorAll('.match-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const teams = (row.querySelector('.match-cell')?.textContent || '').toLowerCase();
                const rowMatch = leagueMatch || teams.includes(liveSearchTerm);
                row.style.display = rowMatch ? '' : 'none';
                if (rowMatch) visibleCount++;
            });

            group.style.display = visibleCount > 0 ? '' : 'none';
        });
    }

    // Keresők inicializálása
    initSearchHandlers();

    // Fallback sport config - used if backend doesn't provide details
    const SPORT_CONFIG_FALLBACK = {
        66:  { name: 'Labdarúgás',  icon: 'fa-futbol' },
        67:  { name: 'Kosárlabda',  icon: 'fa-basketball-ball' },
        78:  { name: 'Darts',       icon: 'fa-bullseye' },
        83:  { name: 'Vízilabda',   icon: 'fa-swimmer' },
        73:  { name: 'Kézilabda',   icon: 'fa-hand-rock' },
        70:  { name: 'Jégkorong',   icon: 'fa-hockey-puck' },
        77:  { name: 'Pingpong',    icon: 'fa-table-tennis' },
        145: { name: 'eSport',      icon: 'fa-gamepad' }
    };

    // Dynamic sport details - populated from backend
    let sportDetails = {};

    // Preferred display order (known sports first, then the rest)
    const SPORT_ORDER_PRIORITY = [66, 67, 70, 73, 78, 83, 77];

    // eSport sport IDs — these are shown on the eSport page, not here
    const ESPORT_SPORT_IDS = [145, 146, 147, 148];

    // Sportok amikre ténylegesen lehet fogadni (API ad odds-ot)
    const BETTABLE_SPORT_IDS = [66, 67, 68, 69, 70, 73, 76, 77, 78, 80, 83, 88, 101, 102, 106, 151];

    // Főmenüben elérhető sportok listája — az élő oldalon csak ezeket mutatjuk
    let mainMenuSportIds = new Set();

    console.log('[LIVE.JS] Inicializálás...');

    // ===== GLOBÁLIS EVENT DELEGATION az odds gombokhoz =====
    document.addEventListener('click', function(e) {
        const selectionBtn = e.target.closest('.selection-btn');
        if (!selectionBtn) return;

        if (selectionBtn.classList.contains('disabled') || selectionBtn.classList.contains('market-locked')) {
            console.log('[LIVE.JS] Disabled/locked selection-btn, ignored');
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const homeTeam = selectionBtn.getAttribute('data-home');
        const awayTeam = selectionBtn.getAttribute('data-away');
        const pick = selectionBtn.getAttribute('data-pick');
        const odds = parseFloat(selectionBtn.getAttribute('data-odd'));
        const market = selectionBtn.getAttribute('data-market');
        const matchId = parseInt(selectionBtn.getAttribute('data-match-id')) || 0;

        console.log('[LIVE.JS] selection-btn delegation kattintás:', {
            homeTeam, awayTeam, pick, odds, market, matchId
        });

        if (!homeTeam || !awayTeam || !pick || !market) {
            console.error('[LIVE.JS] Hiányzó adatok a selection-btn-ben');
            return;
        }

        if (typeof window.toggleOdds === 'function') {
            window.toggleOdds(homeTeam, awayTeam, pick, odds, market, matchId);
            
            setTimeout(() => {
                if (typeof window.refreshAllOddsButtons === 'function') {
                    window.refreshAllOddsButtons();
                    console.log('[LIVE.JS] Odds gombok azonnal frissítve (delegation után)');
                }
            }, 0);
        } else {
            console.error('[LIVE.JS] toggleOdds függvény nem érhető el');
        }
    });

    // ===== GLOBÁLIS EVENT DELEGATION a btn-add-bet gombokhoz =====
    document.addEventListener('click', function(e) {
        const addBetBtn = e.target.closest('.btn-add-bet');
        if (!addBetBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const matchId = parseInt(addBetBtn.getAttribute('data-match-id'));
        console.log('[LIVE.JS] btn-add-bet kattintás (delegation), matchId:', matchId);

        if (isNaN(matchId)) {
            console.error('[LIVE.JS] Érvénytelen matchId a btn-add-bet-ben');
            return;
        }

        fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + matchId)
            .then(response => response.json())
            .then(data => {
                console.log('[LIVE.JS] Match details kapott');
                if (data.error) {
                    BmbPopup.error(data.error, 'Hiba');
                } else {
                    openMatchModal(data);
                }
            })
            .catch(error => {
                console.error('[LIVE.JS] Hiba a meccs adatok lekérésekor:', error);
                    BmbPopup.error(t('live.errorFetchingMatch', 'Hiba a meccs adatok lekérésekor'), t('live.serverError', 'Szerverhiba'));
            });
    });

    // ===== SPORT NAV DINAMIKUS FELÉPÍTÉSE =====
    function buildSportsNav(liveSports) {
        // liveSports = { sportId: count, ... } — only sports with count > 0
        sportsNav.innerHTML = '';

        // Get all sport IDs that have live matches, EXCLUDING esport sports + only bettable main menu sports
        const liveSportIds = Object.keys(liveSports)
            .map(id => parseInt(id))
            .filter(id => liveSports[id] > 0
                && !ESPORT_SPORT_IDS.includes(id)
                && BETTABLE_SPORT_IDS.includes(id)
                && (mainMenuSportIds.size === 0 || mainMenuSportIds.has(id))
            );

        if (liveSportIds.length === 0) {
            sportsNav.innerHTML = '<div class="sports-nav-empty"><i class="fas fa-info-circle"></i> ' + t('live.noLiveAnySport', 'Jelenleg nincs élő meccs egyetlen sportágban sem.') + '</div>';
            currentSportId = null;
            return;
        }

        // Sort: priority sports first (in their defined order), then the rest alphabetically
        const orderedSports = [];
        
        // First: add priority sports that have live matches
        SPORT_ORDER_PRIORITY.forEach(id => {
            if (liveSportIds.includes(id)) {
                orderedSports.push(id);
            }
        });

        // Then: add remaining sports (not in priority list) sorted by name
        const remainingSports = liveSportIds
            .filter(id => !SPORT_ORDER_PRIORITY.includes(id))
            .sort((a, b) => {
                const nameA = getSportName(a).toLowerCase();
                const nameB = getSportName(b).toLowerCase();
                return nameA.localeCompare(nameB, 'hu');
            });
        
        orderedSports.push(...remainingSports);

        // If user explicitly selected a sport, keep it even if temporarily 0 matches
        if (userSelectedSportId && liveSportIds.includes(userSelectedSportId)) {
            currentSportId = userSelectedSportId;
        } else if (!currentSportId || !liveSportIds.includes(currentSportId)) {
            currentSportId = orderedSports[0];
            userSelectedSportId = null;
        }

        orderedSports.forEach(sportId => {
            const name = getSportName(sportId);
            const icon = getSportIcon(sportId);
            const count = liveSports[sportId];
            const isActive = sportId === currentSportId;

            const link = document.createElement('a');
            link.href = '#';
            link.className = 'sport-item' + (isActive ? ' active' : '');
            link.setAttribute('data-sport-id', sportId);
            link.innerHTML = `
                <div class="sport-icon"><i class="fas ${icon}"></i></div>
                <span class="sport-name">${escapeHtml(td(name))}</span>
                <span class="sport-count has-live">${count}</span>
            `;

            link.addEventListener('click', function(e) {
                e.preventDefault();
                sportsNav.querySelectorAll('.sport-item').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                currentSportId = sportId;
                userSelectedSportId = sportId;
                viewingMatchDetails = false;
                // Sportváltás: keresés reset
                liveSearchTerm = '';
                const si = document.getElementById('liveSearchInput');
                const sc = document.getElementById('liveSearchClear');
                if (si) si.value = '';
                if (sc) sc.style.display = 'none';
                // Sportváltás: bajnokság állapot NEM törlődik (megmarad ha visszaváltunk)
                // Sportváltás: DOM ürítése + dismissed reset
                dismissedIds.clear();
                const feedContainer = document.getElementById('goal-toast-container');
                if (feedContainer) feedContainer.innerHTML = '';
                // NEM renderelünk cache-ből — várunk a friss szerver adatra (ne villogjon)
                // Loading state amíg az új sport töltődik
                matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Betöltés...</div>';
                refreshMatches();
                loadTickerAndUpcoming();
            });

            sportsNav.appendChild(link);
        });
    }

    // Helper: get sport name from dynamic details or fallback
    function getSportName(sportId) {
        if (sportDetails[sportId] && sportDetails[sportId].name) {
            return td(sportDetails[sportId].name);
        }
        if (SPORT_CONFIG_FALLBACK[sportId]) {
            return td(SPORT_CONFIG_FALLBACK[sportId].name);
        }
        return 'Sport #' + sportId;
    }

    // Helper: get sport icon from dynamic details or fallback
    function getSportIcon(sportId) {
        if (sportDetails[sportId] && sportDetails[sportId].icon) {
            return sportDetails[sportId].icon;
        }
        if (SPORT_CONFIG_FALLBACK[sportId]) {
            return SPORT_CONFIG_FALLBACK[sportId].icon;
        }
        return 'fa-trophy';
    }

    // ===== LIVE SPORT COUNTS LEKÉRÉSE =====
    function fetchLiveSportCounts() {
        return fetch('../../backend/ApiRequest/get_sidebar_sports.php?mode=live')
            .then(response => response.json())
            .then(data => {
                // data = [{sport_api_id, sport_name, icon, match_count, countries: [...]}, ...]
                const liveSports = {};

                // Save sport details from the unified endpoint
                data.forEach(sport => {
                    const id = sport.sport_api_id;
                    sportDetails[id] = {
                        name: sport.sport_name,
                        icon: sport.icon
                    };
                    if (sport.match_count > 0) {
                        liveSports[id] = sport.match_count;
                    }
                });

                return liveSports;
            })
            .catch(error => {
                console.error('[LIVE.JS] Hiba a live sport counts lekérésekor:', error);
                return {};
            });
    }

    // ===== UPDATE SPORT NAV (frissítés) =====
    function updateSportsNav() {
        fetchLiveSportCounts().then(liveSports => {
            buildSportsNav(liveSports);
        });
    }

    // Meccsek frissítése AJAX-szal
    function refreshMatches() {
        if (viewingMatchDetails) {
            console.log('[LIVE.JS] refreshMatches() kihagyva - meccs részletek nézet aktív');
            return;
        }

        if (!currentSportId) {
            console.log('[LIVE.JS] refreshMatches() kihagyva - nincs kiválasztott sport');
            return;
        }

        const myRequestId = ++refreshRequestId;

        console.log('[LIVE.JS] Meccsek frissítése, sport ID:', currentSportId, 'requestId:', myRequestId);
        
        const url = '../../backend/ApiRequest/live_table.php?sport_id=' + currentSportId;
        
        fetch(url, { method: 'GET' })
        .then(response => response.text())
        .then(html => {
            if (viewingMatchDetails || myRequestId !== refreshRequestId) {
                console.log('[LIVE.JS] refreshMatches response eldobva');
                return;
            }

            console.log('[LIVE.JS] HTML kapott, hossz:', html.length);
            matchesContainer.innerHTML = html;
            applyDynamicTranslations(matchesContainer);
            
            attachMatchClickHandlers();
            applyLeagueStates();
            applySearchFilter();
            
            if (typeof window.refreshAllOddsButtons === 'function') {
                window.refreshAllOddsButtons(50);
            }
        })
        .catch(error => console.error('[LIVE.JS] Hiba a meccsek frissítésekor:', error));
    }

    // Meccs sor kattintás kezelése
    function attachMatchClickHandlers() {
        const matchRows = document.querySelectorAll('.match-row.clickable');
        console.log('[LIVE.JS] attachMatchClickHandlers - talált match-row:', matchRows.length);
        
        matchRows.forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.btn-add-bet') || e.target.closest('.selection-btn')) {
                    console.log('[LIVE.JS] Add-bet vagy selection-btn gombra kattintás');
                    return;
                }
                
                const matchId = parseInt(this.getAttribute('data-match-id'));
                console.log('[LIVE.JS] Meccs soron kattintás, matchId:', matchId);
                loadMatchDetails(matchId);
            });
        });
    }

    // Meccs modal megnyitása
    function openMatchModal(matchData) {
        console.log('[LIVE.JS] openMatchModal - kapott adat:', matchData);

        if (!matchData || matchData.error) {
            console.error('[LIVE.JS] Hiba a match data-ban:', matchData);
            BmbPopup.error((matchData ? matchData.error : 'Ismeretlen hiba'), 'Hiba');
            return;
        }

        const match = matchData.match;
        if (!match) {
            console.error('[LIVE.JS] Nincs match objektum a válaszban');
            BmbPopup.error('Nincsenek meccs adatok', 'Hiba');
            return;
        }

        const markets = matchData.markets || [];

        console.log('[LIVE.JS] Modal adatok - match:', match.name, 'markets:', markets.length);

        let modalHTML = `
            <div class="modal fade" id="matchModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content bg-dark text-light">
                        <div class="modal-header border-bottom border-secondary">
                            <div>
                                <h5 class="modal-title">${escapeHtml(match.name || t('live.matchDefaultName', 'Meccs'))}</h5>
                                <small class="text-muted">${escapeHtml(td((match.country || '') + ' - ' + (match.championship || '')))}</small>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="match-info mb-4">
                                <div class="text-center">
                                    <div class="fs-5 mb-2">${escapeHtml((match.homeTeam || '') + ' ')} <strong>${escapeHtml(match.score || '0 - 0')}</strong> ${escapeHtml((match.awayTeam || ''))}</div>
                                    ${match.isLive ? `<div class="live-indicator"><span class="badge bg-danger">${t('live.liveBadge', 'ÉLŐBEN')}</span> ${escapeHtml(match.liveTime || '')}</div>` : '<div class="text-muted small">' + t('live.notLive', 'Nem élő') + '</div>'}
                                </div>
                            </div>
        `;

        if (markets.length > 0) {
            modalHTML += '<div class="markets">';
            
            markets.forEach(market => {
                const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                const marketFullName = td(market.name || '') + specialVal;
                modalHTML += `<div class="market-section mb-3">
                    <h6 class="text-secondary">${escapeHtml(marketFullName)}</h6>
                    <div class="selections">`;
                
                if (market.selections && Array.isArray(market.selections)) {
                    market.selections.forEach(selection => {
                        const oddsValue = parseFloat(selection.odds) || 0;
                        const state = window.BetslipLogic ? window.BetslipLogic.getButtonState(match.homeTeam, match.awayTeam, selection.name, marketFullName) : null;
                        const stateClass = state ? ' ' + state : '';
                        const isDisabled = state === 'disabled' ? ' disabled' : '';
                        
                        modalHTML += `
                            <button class="selection-btn${stateClass}"${isDisabled} data-match-id="${match.id}" data-home="${escapeHtml(match.homeTeam || '')}" data-away="${escapeHtml(match.awayTeam || '')}" data-pick="${escapeHtml(selection.name)}" data-market="${escapeHtml(marketFullName)}" data-odd="${oddsValue}">
                                <div class="selection-name">${escapeHtml(selection.name)}</div>
                                <div class="selection-odds">${oddsValue.toFixed(2)}</div>
                            </button>`;
                    });
                }
                
                modalHTML += '</div></div>';
            });
            
            modalHTML += '</div>';
        } else {
            modalHTML += '<div class="alert alert-info">' + t('live.noMarkets', 'Nincsenek elérhető piacok ehhez a mérkőzéshez.') + '</div>';
        }

        modalHTML += `
                        </div>
                    </div>
                </div>
            </div>
        `;

        let existingModal = document.getElementById('matchModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        const modal = new bootstrap.Modal(document.getElementById('matchModal'));
        modal.show();

        if (typeof window.refreshAllOddsButtons === 'function') {
            window.refreshAllOddsButtons(50);
        }
    }

    // Meccs részletek megjelenítése
    function loadMatchDetails(eventId) {
        console.log('[LIVE.JS] loadMatchDetails, eventId:', eventId);
        viewingMatchDetails = true;
        currentDetailEventId = eventId;
        refreshRequestId++;
        
        const container = matchesContainer;
        container.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> ' + t('live.loadingMatchDetails', 'Meccs adatok betöltése...') + '</div>';
        
        fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + eventId)
            .then(response => {
                console.log('[LIVE.JS] Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('[LIVE.JS] Raw response:', text);
                const data = JSON.parse(text);
                console.log('[LIVE.JS] Match details JSON kapott:', data);
                renderMatchDetails(data);
            })
            .catch(error => console.error('[LIVE.JS] Hiba a meccs adatok lekérésekor:', error));
    }

    // Meccs részletek frissítése
    function refreshMatchDetails() {
        if (!viewingMatchDetails || !currentDetailEventId) return;
        
        console.log('[LIVE.JS] refreshMatchDetails, eventId:', currentDetailEventId);
        
        fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + currentDetailEventId)
            .then(response => response.json())
            .then(data => {
                if (!viewingMatchDetails || !currentDetailEventId) return;
                
                if (data && !data.error && data.match) {
                    const scoreBig = matchesContainer.querySelector('.score-big');
                    if (scoreBig) scoreBig.textContent = data.match.score || '0 - 0';
                    
                    const liveTimeBig = matchesContainer.querySelector('.live-time-big');
                    if (liveTimeBig) liveTimeBig.textContent = data.match.liveTime || '-';
                    
                    const markets = data.markets || [];
                    markets.forEach(market => {
                        const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                        const marketFullName = td(market.name || '') + specialVal;
                        
                        if (market.selections && Array.isArray(market.selections)) {
                            market.selections.forEach(selection => {
                                const newOdds = parseFloat(selection.odds) || 0;
                                const btns = matchesContainer.querySelectorAll(`.selection-btn[data-market="${CSS.escape(marketFullName)}"][data-pick="${CSS.escape(selection.name)}"]`);
                                btns.forEach(btn => {
                                    const oddsEl = btn.querySelector('.selection-odds');
                                    if (oddsEl) {
                                        const oldOdds = parseFloat(btn.getAttribute('data-odd')) || 0;
                                        if (oldOdds !== newOdds) {
                                            const arrowClass = newOdds > oldOdds ? 'odds-arrow-up' : 'odds-arrow-down';
                                            const arrowIcon = newOdds > oldOdds ? '▲' : '▼';
                                            
                                            const oldArrow = btn.querySelector('.odds-arrow');
                                            if (oldArrow) oldArrow.remove();
                                            
                                            const arrowSpan = document.createElement('span');
                                            arrowSpan.className = 'odds-arrow ' + arrowClass;
                                            arrowSpan.textContent = arrowIcon;
                                            oddsEl.appendChild(arrowSpan);
                                            
                                            oddsEl.firstChild.textContent = newOdds.toFixed(2);
                                            btn.setAttribute('data-odd', newOdds);
                                            
                                            btn.classList.add('odds-changed');
                                            setTimeout(() => {
                                                btn.classList.remove('odds-changed');
                                                const arrow = btn.querySelector('.odds-arrow');
                                                if (arrow) arrow.remove();
                                            }, 3000);
                                        }
                                    }
                                });
                            });
                        }
                    });
                    
                    console.log('[LIVE.JS] Meccs részletek frissítve (állás, perc, oddsok)');
                }
            })
            .catch(error => console.error('[LIVE.JS] Hiba a meccs részletek frissítésekor:', error));
    }

    // Meccs részletek renderelése
    function renderMatchDetails(matchData) {
        console.log('[LIVE.JS] renderMatchDetails - kapott adat:', matchData);

        if (!matchData || matchData.error) {
            console.error('[LIVE.JS] Hiba a match data-ban:', matchData);
            matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('live.errorPrefix', 'Hiba:') + ' ' + (matchData ? matchData.error : t('mainMenu.unknown', 'Ismeretlen')) + '</div>';
            return;
        }

        const match = matchData.match;
        if (!match) {
            console.error('[LIVE.JS] Nincs match objektum a válaszban');
            matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('live.noMatchData', 'Nincsenek meccs adatok') + '</div>';
            return;
        }

        const markets = matchData.markets || [];
        console.log('[LIVE.JS] Render adatok - match:', match.name, 'markets:', markets.length);

        let html = `
            <button class="back-btn" id="back-to-matches">
                <i class="fas fa-arrow-left"></i> ${t('live.backToLive', 'Vissza az élő meccsekhez')}
            </button>

            <div class="match-header-card">
                <div class="match-meta">
                    <span class="meta-item"><i class="fas fa-globe-europe"></i> ${escapeHtml(td(match.country || t('mainMenu.unknown', 'Ismeretlen')))}</span>
                    <span class="meta-item"><i class="fas fa-trophy"></i> ${escapeHtml(td(match.championship || t('mainMenu.unknown', 'Ismeretlen')))}</span>
                    <span class="meta-item"><i class="fas fa-clock"></i> ${match.startUtc ? escapeHtml(new Date(match.startUtc).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' })) : '-'}</span>
                </div>
                <div class="match-scoreboard">
                    <div class="team-side home-side">
                        <span class="team-name-big">${escapeHtml(match.homeTeam || '')}</span>
                    </div>
                    <div class="score-center">
                        <div class="score-big">${escapeHtml(match.score || '0 - 0')}</div>
                        ${match.isLive ? `<div class="live-badge"><span class="live-dot-big"></span><span class="live-time-big">${escapeHtml(match.liveTime || '-')}</span></div>` : '<div class="not-started-badge"><i class="fas fa-clock"></i> ' + t('live.notLive', 'Nem élő') + '</div>'}
                    </div>
                    <div class="team-side away-side">
                        <span class="team-name-big">${escapeHtml(match.awayTeam || '')}</span>
                    </div>
                </div>
            </div>

            <h3 class="markets-title"><i class="fas fa-chart-bar"></i> ${t('mainMenu.bettingMarkets', 'Fogadási piacok')}</h3>
        `;

        if (markets.length > 0) {
            html += '<div class="markets-container">';
            
            markets.forEach(market => {
                const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                const marketFullName = td(market.name || '') + specialVal;
                html += `<div class="market-card">
                    <div class="market-header"><span class="market-name">${escapeHtml(marketFullName)}</span></div>
                    <div class="market-selections">`;
                
                if (market.selections && Array.isArray(market.selections)) {
                    market.selections.forEach(selection => {
                        const oddsValue = parseFloat(selection.odds) || 0;
                        const state = window.BetslipLogic ? window.BetslipLogic.getButtonState(match.homeTeam, match.awayTeam, selection.name, marketFullName) : null;
                        const stateClass = state ? ' ' + state : '';
                        const isDisabled = state === 'disabled' ? ' disabled' : '';
                        
                        html += `
                            <button class="selection-btn${stateClass}"${isDisabled} data-match-id="${match.id}" data-home="${escapeHtml(match.homeTeam || '')}" data-away="${escapeHtml(match.awayTeam || '')}" data-pick="${escapeHtml(selection.name)}" data-market="${escapeHtml(marketFullName)}" data-odd="${oddsValue}">
                                <div class="selection-name">${escapeHtml(selection.name)}</div>
                                <div class="selection-odds">${oddsValue.toFixed(2)}</div>
                            </button>`;
                    });
                }
                
                html += '</div></div>';
            });
            
            html += '</div>';
        } else {
            html += '<div class="alert alert-info">' + t('live.noMarkets', 'Nincsenek elérhető piacok ehhez a mérkőzéshez.') + '</div>';
        }

        matchesContainer.innerHTML = html;
        if (typeof window.applyI18n === 'function') window.applyI18n(matchesContainer);

        if (typeof window.refreshAllOddsButtons === 'function') {
            window.refreshAllOddsButtons(50);
        }

        document.getElementById('back-to-matches').addEventListener('click', function() {
            console.log('[LIVE.JS] Vissza a meccsekhez');
            viewingMatchDetails = false;
            currentDetailEventId = null;
            refreshMatches();
        });
    }

    // HTML escape függvény
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== INICIALIZÁLÁS =====
    console.log('[LIVE.JS] Az oldal inicializálása...');

    // Háttér szinkron (API → DB)
    function syncFromApi() {
        fetch('../../backend/refresh_all.php', { method: 'GET' })
            .catch(err => console.warn('[SYNC] Hálózati hiba:', err));
    }
    syncFromApi();
    setInterval(syncFromApi, 60000);

    // First: fetch main menu sports list, then live sport counts
    fetch('../../backend/ApiRequest/get_sidebar_sports.php')
        .then(r => r.json())
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(s => {
                    if (s.sport_api_id && !ESPORT_SPORT_IDS.includes(s.sport_api_id)) {
                        mainMenuSportIds.add(s.sport_api_id);
                    }
                });
            }
            console.log('[LIVE.JS] Főmenü sportok:', [...mainMenuSportIds]);
        })
        .catch(err => console.warn('[LIVE.JS] Főmenü sportok hiba:', err))
        .then(() => fetchLiveSportCounts())
        .then(liveSports => {
            buildSportsNav(liveSports);
            if (currentSportId) {
                refreshMatches();
            } else {
                matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>' + t('live.noLiveAnySport', 'Jelenleg nincs élő meccs egyetlen sportágban sem.') + '</div>';
            }
            // Feed betöltése MIUTÁN a currentSportId be van állítva
            // Nem renderelünk cache-ből — várunk a friss szerver adatra
            loadTickerAndUpcoming();
        });
    
    // ===== EREDMÉNY FEED + KÖZELGŐ MECCSEK =====
    let serverTimeOffset = 0;
    const dismissedIds = new Set();
    let feedHovered = false; // Feed frissítés szüneteltetése hover alatt
    // Sportonként külön kezdőidő — mikor lett először kiválasztva az adott sport
    const sportFeedStartTimes = new Map();

    // Hover figyelés: ne frissüljön a feed amíg a user fölötte van
    const feedPanel = document.getElementById('score-feed-panel');
    if (feedPanel) {
        feedPanel.addEventListener('mouseenter', () => { feedHovered = true; });
        feedPanel.addEventListener('mouseleave', () => { feedHovered = false; });
    }

    // ── localStorage persistence ──
    const FEED_STORAGE_KEY = 'bmb_feed_cache';

    function saveFeedToStorage() {
        try {
            const data = {
                startTimes: Object.fromEntries(sportFeedStartTimes),
                dismissed: [...dismissedIds],
                items: feedItemsCache,
                savedAt: Math.floor(Date.now() / 1000)
            };
            localStorage.setItem(FEED_STORAGE_KEY, JSON.stringify(data));
        } catch (e) { /* quota exceeded — silently ignore */ }
    }

    function loadFeedFromStorage() {
        try {
            const raw = localStorage.getItem(FEED_STORAGE_KEY);
            if (!raw) return;
            const data = JSON.parse(raw);
            // Max 10 perc régi adat — utána eldobjuk
            const age = Math.floor(Date.now() / 1000) - (data.savedAt || 0);
            if (age > 600) {
                localStorage.removeItem(FEED_STORAGE_KEY);
                return;
            }
            if (data.startTimes) {
                for (const [k, v] of Object.entries(data.startTimes)) {
                    sportFeedStartTimes.set(Number(k), v);
                }
            }
            if (Array.isArray(data.dismissed)) {
                data.dismissed.forEach(id => dismissedIds.add(id));
            }
            if (data.items) {
                Object.assign(feedItemsCache, data.items);
            }
        } catch (e) { /* corrupted data — ignore */ }
    }

    // Sportonként tárolt feed elemek (backend → renderScoreFeed is menti ide)
    const feedItemsCache = {}; // { sportId: [item, item, ...] }

    // Induláskor betöltjük a localStorage-ból
    loadFeedFromStorage();

    function getFeedStartTime(sportId) {
        if (!sportFeedStartTimes.has(sportId)) {
            // Első kiválasztáskor: utolsó 5 perc változásait mutatjuk
            // Mindig az aktuális serverTimeOffset-et használjuk (ne legyen rossz ha 0 volt induláskor)
            sportFeedStartTimes.set(sportId, Math.floor(Date.now() / 1000) + serverTimeOffset - 300);
        }
        return sportFeedStartTimes.get(sportId);
    }

    // serverTimeOffset frissítése simítással (ne ugrálja az eltelt idő számlálót)
    let offsetSynced = false;
    function updateServerTimeOffset(serverTime) {
        const newOffset = serverTime - Math.floor(Date.now() / 1000);
        if (!offsetSynced) {
            // Első szinkron: elfogadjuk az értéket
            serverTimeOffset = newOffset;
            offsetSynced = true;
        } else if (Math.abs(newOffset - serverTimeOffset) > 5) {
            // Csak nagy eltérés (>5s) esetén frissítünk (pl. szerver óra változás)
            serverTimeOffset = newOffset;
        }
        // Kis eltérést (hálózati jitter) figyelmen kívül hagyjuk
    }

    function loadTickerAndUpcoming() {
        // FONTOS: ne hívjuk amíg a currentSportId nincs beállítva
        if (!currentSportId) return;
        fetch('../../backend/ApiRequest/get_live_ticker.php?sport_id=' + currentSportId)
            .then(r => r.json())
            .then(data => {
                if (data.serverTime) {
                    updateServerTimeOffset(data.serverTime);
                }
                const tickerItems = data.ticker || [];
                // Mentés sportonkénti cache-be
                feedItemsCache[currentSportId] = tickerItems;
                saveFeedToStorage();
                renderScoreFeed(tickerItems);
                renderUpcoming(data.upcoming || []);
            })
            .catch(err => console.error('[LIVE.JS] Ticker hiba:', err));
    }

    function renderScoreFeed(items) {
        const container = document.getElementById('goal-toast-container');
        if (!container) return;
        // Ha a user a feed felett van egérrel, ne rendereljünk (ne tűnjön el elem kattintás közben)
        if (feedHovered) return;

        // Szűrés: csak az adott sport kiválasztása UTÁNI + nem elrejtett elemek
        const startTime = getFeedStartTime(currentSportId);
        const visible = items.filter(item => {
            if (!item.id) return false;
            if (dismissedIds.has(item.id)) return false;
            if (item.ts && item.ts < startTime) return false;
            return true;
        });

        // Üres állapot
        if (visible.length === 0) {
            if (!container.querySelector('.score-feed-empty')) {
                container.innerHTML = '<div class="score-feed-empty"><i class="fas fa-futbol"></i>Még nincs eredmény változás</div>';
            }
            return;
        }

        // Van adat → üres placeholder törlése
        const emptyEl = container.querySelector('.score-feed-empty');
        if (emptyEl) emptyEl.remove();

        // Térkép: backend ID → adat
        const dataMap = new Map();
        visible.forEach(item => dataMap.set(item.id, item));

        // 1) Lejárt elemek eltüntetése fade-out animációval (ne tűnjenek el hirtelen)
        container.querySelectorAll('.goal-toast[data-id]').forEach(el => {
            if (!dataMap.has(el.getAttribute('data-id'))) {
                if (el.classList.contains('goal-toast-removing')) return; // már törlés alatt
                el.classList.add('goal-toast-removing');
                el.style.animation = 'goalItemFadeOut 0.4s ease forwards';
                setTimeout(() => {
                    el.remove();
                    if (!container.querySelector('.goal-toast')) {
                        container.innerHTML = '<div class="score-feed-empty"><i class="fas fa-futbol"></i>Még nincs eredmény változás</div>';
                    }
                }, 400);
            }
        });

        // 2) Létező elemek frissítése (eredmény, élő idő, eltelt idő, gólcsapat változhat)
        const existingIds = new Set();
        container.querySelectorAll('.goal-toast[data-id]').forEach(el => {
            const id = el.getAttribute('data-id');
            existingIds.add(id);
            const item = dataMap.get(id);
            if (!item) return;
            // Frissíthető mezők (nem kell DOM-ot újraépíteni)
            const scoreEl = el.querySelector('.goal-toast-score');
            if (scoreEl && scoreEl.textContent !== item.score) scoreEl.textContent = item.score;
            const timeEl = el.querySelector('.goal-toast-time');
            if (timeEl && timeEl.textContent !== (item.liveTime || '')) timeEl.textContent = item.liveTime || '';
            // Eltelt idő timestamp frissítése (új gól → ts változik)
            const elapsedEl = el.querySelector('.goal-toast-elapsed');
            if (elapsedEl && item.ts) {
                const oldTs = elapsedEl.getAttribute('data-ts');
                if (oldTs !== String(item.ts)) {
                    elapsedEl.setAttribute('data-ts', item.ts);
                }
            }
            // Gólcsapat frissítése (ha más csapat szerzett újabb gólt)
            const titleEl = el.querySelector('.goal-toast-title strong');
            if (titleEl && item.goalTeam && titleEl.textContent !== item.goalTeam) {
                titleEl.textContent = item.goalTeam;
            }
        });

        // 3) Új elemek hozzáadása (amiket a DOM még nem tartalmaz)
        visible.forEach(item => {
            if (existingIds.has(item.id)) return;

            const teams = (item.name || '').split(/ vs\. | - /);
            const home = (teams[0] || '').trim();
            const away = (teams[1] || '').trim();

            const el = document.createElement('div');
            el.className = 'goal-toast goal-toast-new goal-toast-clickable';
            el.setAttribute('data-id', item.id);
            el.setAttribute('data-match-id', item.matchId || 0);

            // Sport-specifikus ikon és szöveg
            const icon = item.sportIcon ? `<i class="fas ${escapeHtml(item.sportIcon)}"></i>` : '⚽';
            const label = currentSportId === 66 ? 'GÓL!'
                        : currentSportId === 67 ? 'KOSÁR!'
                        : currentSportId === 70 ? 'GÓL!'
                        : currentSportId === 73 ? 'GÓL!'
                        : currentSportId === 78 ? 'PONT!'
                        : currentSportId === 77 ? 'PONT!'
                        : 'PONT!';

            el.innerHTML = `
                <span class="goal-toast-icon">${icon}</span>
                <div class="goal-toast-body">
                    <div class="goal-toast-title">${label} <strong>${escapeHtml(item.goalTeam || '')}</strong></div>
                    <div class="goal-toast-match">${escapeHtml(home)} <span class="goal-toast-score">${escapeHtml(item.score)}</span> ${escapeHtml(away)} <span class="goal-toast-time">${escapeHtml(item.liveTime || '')}</span></div>
                </div>
                <span class="goal-toast-elapsed" data-ts="${item.ts || 0}"></span>
                <button class="goal-toast-close">&times;</button>
            `;

            el.querySelector('.goal-toast-close').addEventListener('click', (e) => {
                e.stopPropagation();
                dismissedIds.add(item.id);
                el.remove();
                if (!container.querySelector('.goal-toast')) {
                    container.innerHTML = '<div class="score-feed-empty"><i class="fas fa-futbol"></i>Még nincs eredmény változás</div>';
                }
            });

            // Kattintás a feed elemre → meccs részletek
            el.addEventListener('click', function() {
                const matchId = parseInt(this.getAttribute('data-match-id'));
                if (matchId) loadMatchDetails(matchId);
            });

            container.prepend(el);
            setTimeout(() => el.classList.remove('goal-toast-new'), 500);
        });

        // Max 10 elem megjelenítése
        const all = container.querySelectorAll('.goal-toast');
        for (let i = 10; i < all.length; i++) all[i].remove();
    }

    function renderUpcoming(items) {
        const list = document.getElementById('upcoming-list');
        const section = document.getElementById('upcoming-section');
        if (!list || !section) return;

        // Always show section (flex), never hide it
        section.style.display = '';

        if (items.length === 0) {
            list.innerHTML = '<div class="upcoming-empty"><i class="fas fa-clock" style="font-size:18px;opacity:0.4;display:block;margin-bottom:6px;"></i><span>Nincs közelgő meccs</span></div>';
            return;
        }

        let html = '';
        items.forEach(item => {
            const teams = (item.name || '').split(/ vs\. | - /);
            const display = teams.length >= 2
                ? escapeHtml(teams[0].trim()) + ' vs ' + escapeHtml(teams[1].trim())
                : escapeHtml(item.name);
            html += `<div class="upcoming-item" data-match-id="${item.apiId}">
                <div class="upcoming-sport-icon"><i class="fas ${escapeHtml(item.sportIcon || 'fa-trophy')}"></i></div>
                <div class="upcoming-info">
                    <div class="upcoming-teams">${display}</div>
                    <div class="upcoming-league">${escapeHtml(item.league || '')}</div>
                </div>
                <div class="upcoming-time">${escapeHtml(item.startTime)}</div>
            </div>`;
        });
        list.innerHTML = html;

        // Kattintás → meccs részletek
        list.querySelectorAll('.upcoming-item').forEach(item => {
            item.addEventListener('click', function() {
                const matchId = parseInt(this.getAttribute('data-match-id'));
                if (matchId) loadMatchDetails(matchId);
            });
        });
    }

    // ===== ELTELT IDŐ SZÁMLÁLÓ =====
    function updateElapsedTimers() {
        const elems = document.querySelectorAll('.goal-toast-elapsed[data-ts]');
        const nowSec = Math.floor(Date.now() / 1000) + serverTimeOffset;
        elems.forEach(el => {
            const ts = parseInt(el.getAttribute('data-ts'));
            if (!ts) return;
            const diff = nowSec - ts;
            if (diff < 0) {
                el.textContent = 'Most';
            } else if (diff < 60) {
                el.textContent = diff + ' mp';
            } else {
                const min = Math.floor(diff / 60);
                el.textContent = min + ' perce';
            }
        });
    }
    setInterval(updateElapsedTimers, 1000);

    // Auto-frissítés 5 másodpercenként (feed + meccsek + sportok)
    autoRefreshInterval = setInterval(() => {
        if (viewingMatchDetails) {
            refreshMatchDetails();
            // Feed frissítés meccs részletek módban is (ne fagyjon be)
            loadTickerAndUpcoming();
        } else {
            fetchLiveSportCounts().then(liveSports => {
                buildSportsNav(liveSports);
                if (currentSportId) {
                    refreshMatches();
                } else {
                    matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>' + t('live.noLiveAnySport', 'Jelenleg nincs élő meccs egyetlen sportágban sem.') + '</div>';
                }
            });
            loadTickerAndUpcoming();
        }
    }, 5000);

    window.addEventListener('languageChanged', function() {
        if (viewingMatchDetails && currentDetailEventId) {
            refreshMatchDetails();
            return;
        }
        updateSportsNav();
        refreshMatches();
    });
    
    console.log('[LIVE.JS] Inicializálás kész!');
});
