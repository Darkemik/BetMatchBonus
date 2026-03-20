document.addEventListener('DOMContentLoaded', function() {
    const sportButtons = document.querySelectorAll('.sport-item');
    const matchesContainer = document.getElementById('matches-container');
    let currentSportId = 66; // Alapértelmezetten foci
    let autoRefreshInterval = null;
    let viewingMatchDetails = false; // Flag: meccs részletek nézetben vagyunk-e
    let refreshRequestId = 0; // Minden refreshMatches híváshoz egyedi ID
    let currentDetailEventId = null; // Melyik meccs részleteit nézzük éppen

    console.log('[LIVE.JS] Inicializálás...', {
        sportButtonsCount: sportButtons.length,
        hasMatchesContainer: !!matchesContainer
    });

    // ===== GLOBÁLIS EVENT DELEGATION az odds gombokhoz =====
    // Ez működik az AJAX után új gombok esetén is
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
            
            // Azonnal frissítjük az összes gombot
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
    // Ez működik az AJAX után új gombok esetén is
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

    // Sport gomb kattintások
    sportButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            console.log('[LIVE.JS] Sport gomb kattintás');
            
            // Aktív státusz frissítése
            sportButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Sport ID lekérése
            const sportCount = this.querySelector('.sport-count');
            if (!sportCount) {
                console.error('[LIVE.JS] Nincs .sport-count elem!');
                return;
            }
            
            currentSportId = parseInt(sportCount.getAttribute('data-sport-id'));
            console.log('[LIVE.JS] Kiválasztott sport ID:', currentSportId);
            
            // Ha a részletek nézetben voltunk, visszaállítjuk a flag-et
            viewingMatchDetails = false;
            
            // Táblázat frissítése
            refreshMatches();
        });
    });

    // Meccsek frissítése AJAX-szal
    function refreshMatches() {
        // Ha a meccs részletek nézet van megnyitva, NE frissítsünk
        if (viewingMatchDetails) {
            console.log('[LIVE.JS] refreshMatches() kihagyva - meccs részletek nézet aktív');
            return;
        }

        // Egyedi request ID - ha közben új hívás jön vagy nézet vált, eldobjuk a régit
        const myRequestId = ++refreshRequestId;

        console.log('[LIVE.JS] Meccsek frissítése, sport ID:', currentSportId, 'requestId:', myRequestId);
        
        const url = '../../backend/ApiRequest/live_table.php?sport_id=' + currentSportId;
        
        fetch(url, { method: 'GET' })
        .then(response => response.text())
        .then(html => {
            // Ha közben a részletek nézetre váltottunk VAGY újabb refresh indult, NE írjuk felül!
            if (viewingMatchDetails || myRequestId !== refreshRequestId) {
                console.log('[LIVE.JS] refreshMatches response eldobva (viewingDetails:', viewingMatchDetails, ', requestId:', myRequestId, '/', refreshRequestId, ')');
                return;
            }

            console.log('[LIVE.JS] HTML kapott, hossz:', html.length);
            matchesContainer.innerHTML = html;
            
            attachMatchClickHandlers();
            updateSportCounts();
            
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
                // Ha az odds gombra kattintottak, ne nyiljon meg a modal
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

        // Ellenőrzés: van-e error vagy nincs match adat?
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

        // Frissítjük az odds gombokat a modal-ben
        if (typeof window.refreshAllOddsButtons === 'function') {
            window.refreshAllOddsButtons(50);
        }
    }

    // Meccs részletek megjelenítése (teljes oldal, nem modal)
    function loadMatchDetails(eventId) {
        console.log('[LIVE.JS] loadMatchDetails, eventId:', eventId);
        viewingMatchDetails = true; // Jelöljük, hogy a részletek nézetben vagyunk
        currentDetailEventId = eventId; // Elmentjük melyik meccset nézzük
        refreshRequestId++; // Érvénytelenítjük a még futó refreshMatches fetch-eket
        
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

    // Meccs részletek frissítése (élő adatok: állás, perc, oddsok)
    function refreshMatchDetails() {
        if (!viewingMatchDetails || !currentDetailEventId) return;
        
        console.log('[LIVE.JS] refreshMatchDetails, eventId:', currentDetailEventId);
        
        fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + currentDetailEventId)
            .then(response => response.json())
            .then(data => {
                // Közben visszamentünk a listához? Ne csináljunk semmit
                if (!viewingMatchDetails || !currentDetailEventId) return;
                
                if (data && !data.error && data.match) {
                    // Állás és perc frissítése
                    const scoreBig = matchesContainer.querySelector('.score-big');
                    if (scoreBig) scoreBig.textContent = data.match.score || '0 - 0';
                    
                    const liveTimeBig = matchesContainer.querySelector('.live-time-big');
                    if (liveTimeBig) liveTimeBig.textContent = data.match.liveTime || '-';
                    
                    // Oddsok frissítése - végigmegyünk az összes selection-btn-en
                    const markets = data.markets || [];
                    markets.forEach(market => {
                        const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
                        const marketFullName = (market.name || '') + specialVal;
                        
                        if (market.selections && Array.isArray(market.selections)) {
                            market.selections.forEach(selection => {
                                const newOdds = parseFloat(selection.odds) || 0;
                                // Megkeressük a megfelelő gombot
                                const btns = matchesContainer.querySelectorAll(`.selection-btn[data-market="${CSS.escape(marketFullName)}"][data-pick="${CSS.escape(selection.name)}"]`);
                                btns.forEach(btn => {
                                    const oddsEl = btn.querySelector('.selection-odds');
                                    if (oddsEl) {
                                        const oldOdds = parseFloat(btn.getAttribute('data-odd')) || 0;
                                        if (oldOdds !== newOdds) {
                                            // Nyíl irány meghatározása
                                            const arrowClass = newOdds > oldOdds ? 'odds-arrow-up' : 'odds-arrow-down';
                                            const arrowIcon = newOdds > oldOdds ? '▲' : '▼';
                                            
                                            // Régi nyíl eltávolítása ha van
                                            const oldArrow = btn.querySelector('.odds-arrow');
                                            if (oldArrow) oldArrow.remove();
                                            
                                            // Új nyíl hozzáadása
                                            const arrowSpan = document.createElement('span');
                                            arrowSpan.className = 'odds-arrow ' + arrowClass;
                                            arrowSpan.textContent = arrowIcon;
                                            oddsEl.appendChild(arrowSpan);
                                            
                                            // Odds érték frissítése
                                            // Az oddsEl-ben az első szövegcsomópont az odds szám
                                            oddsEl.firstChild.textContent = newOdds.toFixed(2);
                                            btn.setAttribute('data-odd', newOdds);
                                            
                                            // Vizuális jelzés
                                            btn.classList.add('odds-changed');
                                            setTimeout(() => {
                                                btn.classList.remove('odds-changed');
                                                // Nyíl eltávolítása 3 mp után
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

    // Meccs részletek renderelése (teljes oldal nézet)
    function renderMatchDetails(matchData) {
        console.log('[LIVE.JS] renderMatchDetails - kapott adat:', matchData);

        // Ellenőrzés: van-e error vagy nincs match adat?
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

        // Frissítjük az odds gombokat az új gombok után
        if (typeof window.refreshAllOddsButtons === 'function') {
            window.refreshAllOddsButtons(50);
        }

        // Vissza gomb kattintás
        document.getElementById('back-to-matches').addEventListener('click', function() {
            console.log('[LIVE.JS] Vissza a meccsekhez');
            viewingMatchDetails = false; // Visszaállítjuk a flag-et
            currentDetailEventId = null; // Töröljük a meccs ID-t
            refreshMatches();
        });
    }

    // Sport meccsek számlálásának frissítése
    function updateSportCounts() {
        const sportCounts = document.querySelectorAll('.sport-count');
        
        sportCounts.forEach(countSpan => {
            const sportId = parseInt(countSpan.getAttribute('data-sport-id'));
            
            fetch('../../backend/ApiRequest/live_table.php?sport_id=' + sportId)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const rowCount = doc.querySelectorAll('.match-row').length;
                    countSpan.textContent = rowCount;
                })
                .catch(() => {
                    countSpan.textContent = '-';
                });
        });
    }

    // HTML escape függvény
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Inicializálás
    console.log('[LIVE.JS] Az oldal inicializálása...');
    refreshMatches();
    
    // Auto-frissítés 10 másodpercenként
    autoRefreshInterval = setInterval(() => {
        if (viewingMatchDetails) {
            // Ha a meccs részletek nézetben vagyunk, csak a meccs adatait frissítjük
            refreshMatchDetails();
        } else {
            refreshMatches();
        }
    }, 10000);
    
    console.log('[LIVE.JS] Inicializálás kész!');
});