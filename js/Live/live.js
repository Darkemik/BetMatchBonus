document.addEventListener('DOMContentLoaded', function() {
    const sportButtons = document.querySelectorAll('.sport-item');
    const matchesContainer = document.getElementById('matches-container');
    let currentSportId = 66; // Alapértelmezetten foci
    let autoRefreshInterval = null;

    console.log('[LIVE.JS] Inicializálás...', {
        sportButtonsCount: sportButtons.length,
        hasMatchesContainer: !!matchesContainer
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
            
            // Táblázat frissítése
            refreshMatches();
        });
    });

    // Meccsek frissítése AJAX-szal
    function refreshMatches() {
        console.log('[LIVE.JS] Meccsek frissítése, sport ID:', currentSportId);
        
        const url = '../../backend/ApiRequest/live_table.php?sport_id=' + currentSportId;
        console.log('[LIVE.JS] Fetch URL:', url);
        
        fetch(url, {
            method: 'GET'
        })
        .then(response => response.text())
        .then(html => {
            console.log('[LIVE.JS] HTML kapott, hossz:', html.length);
            matchesContainer.innerHTML = html;
            attachMatchClickHandlers();
            attachOddsButtonHandlers();
            updateSportCounts();
            if (typeof window.refreshAllOddsButtons === 'function') {
                console.log('[LIVE.JS] refreshAllOddsButtons meghívása');
                window.refreshAllOddsButtons();
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
                if (e.target.closest('.btn-add-bet')) {
                    console.log('[LIVE.JS] btn-add-bet gombra kattintás');
                    return;
                }
                
                const matchId = parseInt(this.getAttribute('data-match-id'));
                console.log('[LIVE.JS] Meccs soron kattintás, matchId:', matchId);
                loadMatchDetails(matchId);
            });
        });
    }

    // Odds gombok kattintás kezelése
    function attachOddsButtonHandlers(container) {
        const targetContainer = container || document;
        
        console.log('[LIVE.JS] attachOddsButtonHandlers - container:', !!container);
        
        // Btn-add-bet gombok (a táblázatban az "Akció" oszlopban)
        const addBetBtns = targetContainer.querySelectorAll('.btn-add-bet');
        console.log('[LIVE.JS] btn-add-bet gombok talált:', addBetBtns.length);
        
        addBetBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const matchId = parseInt(this.getAttribute('data-match-id'));
                const matchName = this.getAttribute('data-match-name');
                
                console.log('[LIVE.JS] btn-add-bet kattintás, matchId:', matchId);
                
                // Meccs részleteit lekérjük
                fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + matchId)
                    .then(response => response.json())
                    .then(data => {
                        console.log('[LIVE.JS] Match details kapott');
                        openMatchModal(data);
                    })
                    .catch(error => console.error('[LIVE.JS] Hiba a meccs adatok lekérésekor:', error));
            });
        });

        // Selection button (odds) kattintás - MODAL-ban és MÁSHOL
        const selectionBtns = targetContainer.querySelectorAll('.selection-btn');
        console.log('[LIVE.JS] selection-btn gombok talált:', selectionBtns.length);
        
        selectionBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (this.classList.contains('disabled')) {
                    console.log('[LIVE.JS] Disabled selection-btn, ignored');
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();

                const homeTeam = this.getAttribute('data-home');
                const awayTeam = this.getAttribute('data-away');
                const pick = this.getAttribute('data-pick');
                const odds = parseFloat(this.getAttribute('data-odd'));
                const market = this.getAttribute('data-market');
                const matchId = parseInt(this.getAttribute('data-match-id')) || 0;

                console.log('[LIVE.JS] selection-btn kattintás:', {
                    homeTeam, awayTeam, pick, odds, market, matchId
                });

                if (!homeTeam || !awayTeam || !pick || !market) {
                    console.error('[LIVE.JS] Hiányzó adatok a selection-btn-ben');
                    return;
                }

                if (typeof window.toggleOdds === 'function') {
                    window.toggleOdds(homeTeam, awayTeam, pick, odds, market, matchId);
                    
                    // Frissítjük az összes gombot
                    setTimeout(function() {
                        if (typeof window.refreshAllOddsButtons === 'function') {
                            window.refreshAllOddsButtons();
                        }
                    }, 50);
                } else {
                    console.error('[LIVE.JS] toggleOdds függvény nem érhető el');
                }
            });
        });
    }

    // Meccs modal megnyitása
    function openMatchModal(matchData) {
        console.log('[LIVE.JS] openMatchModal - kapott adat:', matchData);

        // Ellenőrzés: van-e error vagy nincs match adat?
        if (!matchData || matchData.error) {
            console.error('[LIVE.JS] Hiba a match data-ban:', matchData);
            alert('Hiba: ' + (matchData ? matchData.error : 'Ismeretlen hiba'));
            return;
        }

        const match = matchData.match;
        if (!match) {
            console.error('[LIVE.JS] Nincs match objektum a válaszban');
            alert('Hiba: Nincsenek meccs adatok');
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

        // Modal megnyitása után AZONNAL csatoljuk az odds gombokat
        const modalElement = document.getElementById('matchModal');
        attachOddsButtonHandlers(modalElement);

        // Modal bezáráskor is csatoljuk újra (a fő táblázat gombait)
        modalElement.addEventListener('hidden.bs.modal', function() {
            console.log('[LIVE.JS] Modal bezárva, odds gombok újra csatolása');
            attachOddsButtonHandlers();
        });
    }

    // Meccs részletek megjelenítése (teljes oldal, nem modal)
    function loadMatchDetails(eventId) {
        console.log('[LIVE.JS] loadMatchDetails, eventId:', eventId);
        
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

        // Vissza gomb kattintás
        document.getElementById('back-to-matches').addEventListener('click', function() {
            console.log('[LIVE.JS] Vissza a meccsekhez');
            refreshMatches();
        });

        // Odds gombok kezelése
        attachOddsButtonHandlers();
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
    
    // Auto-frissítés 30 másodpercenként
    autoRefreshInterval = setInterval(() => {
        refreshMatches();
    }, 30000);
    
    console.log('[LIVE.JS] Inicializálás kész!');
});