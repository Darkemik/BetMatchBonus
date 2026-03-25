document.addEventListener('DOMContentLoaded', function() {
    const matchesContainer = document.getElementById('matches-container');
    const sportsNav = document.getElementById('liveSportsNav');
    let currentSportId = null; // Dynamically set from first live sport
    let autoRefreshInterval = null;
    let viewingMatchDetails = false;
    let refreshRequestId = 0;
    let currentDetailEventId = null;

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
                BmbPopup.error('Hiba a meccs adatok lekérésekor', 'Szerverhiba');
            });
    });

    // ===== SPORT NAV DINAMIKUS FELÉPÍTÉSE =====
    function buildSportsNav(liveSports) {
        // liveSports = { sportId: count, ... } — only sports with count > 0
        sportsNav.innerHTML = '';

        // Get all sport IDs that have live matches, EXCLUDING esport sports
        const liveSportIds = Object.keys(liveSports)
            .map(id => parseInt(id))
            .filter(id => liveSports[id] > 0 && !ESPORT_SPORT_IDS.includes(id));

        if (liveSportIds.length === 0) {
            sportsNav.innerHTML = '<div class="sports-nav-empty"><i class="fas fa-info-circle"></i> Jelenleg nincs élő meccs egyetlen sportágban sem.</div>';
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

        // If current sport no longer has live matches, switch to the first available
        if (!currentSportId || !liveSports[currentSportId] || liveSports[currentSportId] <= 0) {
            currentSportId = orderedSports[0];
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
                <span class="sport-name">${escapeHtml(name)}</span>
                <span class="sport-count has-live">${count}</span>
            `;

            link.addEventListener('click', function(e) {
                e.preventDefault();
                sportsNav.querySelectorAll('.sport-item').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                currentSportId = sportId;
                viewingMatchDetails = false;
                refreshMatches();
            });

            sportsNav.appendChild(link);
        });
    }

    // Helper: get sport name from dynamic details or fallback
    function getSportName(sportId) {
        if (sportDetails[sportId] && sportDetails[sportId].name) {
            return sportDetails[sportId].name;
        }
        if (SPORT_CONFIG_FALLBACK[sportId]) {
            return SPORT_CONFIG_FALLBACK[sportId].name;
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
        return fetch('../../backend/ApiRequest/get_matches_live.php')
            .then(response => response.json())
            .then(data => {
                const sports = data.sports || {};
                
                // Save sport details from backend (names, icons)
                if (data.sportDetails) {
                    sportDetails = data.sportDetails;
                }
                
                // Only keep sports with count > 0
                const liveSports = {};
                for (const [id, count] of Object.entries(sports)) {
                    if (count > 0) {
                        liveSports[parseInt(id)] = count;
                    }
                }
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
            
            attachMatchClickHandlers();
            
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
                                <h5 class="modal-title">${escapeHtml(match.name || 'Meccs')}</h5>
                                <small class="text-muted">${escapeHtml((match.country || '') + ' - ' + (match.championship || ''))}</small>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="match-info mb-4">
                                <div class="text-center">
                                    <div class="fs-5 mb-2">${escapeHtml((match.homeTeam || '') + ' ')} <strong>${escapeHtml(match.score || '0 - 0')}</strong> ${escapeHtml((match.awayTeam || ''))}</div>
                                    ${match.isLive ? `<div class="live-indicator"><span class="badge bg-danger">ÉLŐBEN</span> ${escapeHtml(match.liveTime || '')}</div>` : '<div class="text-muted small">Nem élő</div>'}
                                </div>
                            </div>
        `;

        if (markets.length > 0) {
            modalHTML += '<div class="markets">';
            
            markets.forEach(market => {
                const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                const marketFullName = (market.name || '') + specialVal;
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
            modalHTML += '<div class="alert alert-info">Nincsenek elérhető piacok ehhez a mérkőzéshez.</div>';
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
        container.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Meccs adatok betöltése...</div>';
        
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
                        const marketFullName = (market.name || '') + specialVal;
                        
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
            matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Hiba: ' + (matchData ? matchData.error : 'Ismeretlen hiba') + '</div>';
            return;
        }

        const match = matchData.match;
        if (!match) {
            console.error('[LIVE.JS] Nincs match objektum a válaszban');
            matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Hiba: Nincsenek meccs adatok</div>';
            return;
        }

        const markets = matchData.markets || [];
        console.log('[LIVE.JS] Render adatok - match:', match.name, 'markets:', markets.length);

        let html = `
            <button class="back-btn" id="back-to-matches">
                <i class="fas fa-arrow-left"></i> Vissza az élő meccsekhez
            </button>

            <div class="match-header-card">
                <div class="match-meta">
                    <span class="meta-item"><i class="fas fa-globe-europe"></i> ${escapeHtml(match.country || 'Ismeretlen')}</span>
                    <span class="meta-item"><i class="fas fa-trophy"></i> ${escapeHtml(match.championship || 'Ismeretlen')}</span>
                    <span class="meta-item"><i class="fas fa-clock"></i> ${escapeHtml(new Date(match.startUtc).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' }) || '-')}</span>
                </div>
                <div class="match-scoreboard">
                    <div class="team-side home-side">
                        <span class="team-name-big">${escapeHtml(match.homeTeam || '')}</span>
                    </div>
                    <div class="score-center">
                        <div class="score-big">${escapeHtml(match.score || '0 - 0')}</div>
                        ${match.isLive ? `<div class="live-badge"><span class="live-dot-big"></span><span class="live-time-big">${escapeHtml(match.liveTime || '-')}</span></div>` : '<div class="not-started-badge"><i class="fas fa-clock"></i> Nem élő</div>'}
                    </div>
                    <div class="team-side away-side">
                        <span class="team-name-big">${escapeHtml(match.awayTeam || '')}</span>
                    </div>
                </div>
            </div>

            <h3 class="markets-title"><i class="fas fa-chart-bar"></i> Fogadási piacok</h3>
        `;

        if (markets.length > 0) {
            html += '<div class="markets-container">';
            
            markets.forEach(market => {
                const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                const marketFullName = (market.name || '') + specialVal;
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
            html += '<div class="alert alert-info">Nincsenek elérhető piacok ehhez a mérkőzéshez.</div>';
        }

        matchesContainer.innerHTML = html;

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

    // First: fetch live sport counts, build nav, then load matches
    fetchLiveSportCounts().then(liveSports => {
        buildSportsNav(liveSports);
        if (currentSportId) {
            refreshMatches();
        } else {
            matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs élő meccs egyetlen sportágban sem.</div>';
        }
    });
    
    // Auto-frissítés 10 másodpercenként
    autoRefreshInterval = setInterval(() => {
        if (viewingMatchDetails) {
            refreshMatchDetails();
        } else {
            // Refresh both the sport nav and matches
            fetchLiveSportCounts().then(liveSports => {
                buildSportsNav(liveSports);
                if (currentSportId) {
                    refreshMatches();
                } else {
                    matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-futbol" style="font-size:40px;color:#aaa;margin-bottom:12px;display:block;"></i>Jelenleg nincs élő meccs egyetlen sportágban sem.</div>';
                }
            });
        }
    }, 10000);
    
    console.log('[LIVE.JS] Inicializálás kész!');
});