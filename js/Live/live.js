document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("matches-container");
    let refreshInterval = null;
    let viewingDetails = false; // ha meccs részleteket nézünk, ne frissítsünk

    function refreshLiveMatches() {
        if (viewingDetails) return; // ne írjuk felül a részletes nézetet

        fetch("../../backend/ApiRequest/get_matches_live.php")
            .then(() => {
                return fetch("../../backend/ApiRequest/live_table.php");
            })
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
                attachMatchClickHandlers();
                // Nyelv újra alkalmazása
                const savedLang = localStorage.getItem('lang') || 'hu';
                if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
                    changeLanguageForContainer(container, savedLang);
                }
            })
            .catch(err => {
                console.error("Hiba a meccsek frissítésekor:", err);
                container.innerHTML = `
                    <p class="text-center mt-3">Hiba történt a meccsek betöltésekor.</p>
                `;
            });
    }

    function attachMatchClickHandlers() {
        document.querySelectorAll('.match-row.clickable').forEach(row => {
            row.addEventListener('click', () => {
                const matchId = row.getAttribute('data-match-id');
                if (matchId) {
                    loadMatchDetails(matchId);
                }
            });
        });
    }

    function loadMatchDetails(eventId) {
        viewingDetails = true;
        container.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Meccs adatok betöltése...</div>';

        fetch(`../../backend/ApiRequest/get_match_details.php?eventId=${eventId}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    container.innerHTML = `<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ${data.error}</div>`;
                    return;
                }
                renderMatchDetails(data);
                // Nyelv alkalmazása
                const savedLang = localStorage.getItem('lang') || 'hu';
                if (savedLang !== 'hu' && typeof changeLanguageForContainer === 'function') {
                    changeLanguageForContainer(container, savedLang);
                }
            })
            .catch(err => {
                console.error("Hiba:", err);
                container.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Hiba történt az adatok betöltésekor.</div>';
            });
    }

    function renderMatchDetails(data) {
        const match = data.match;
        const markets = data.markets || [];

        const nameParts = match.name.split(' - ');
        const homeTeam = match.homeTeam || nameParts[0] || '';
        const awayTeam = match.awayTeam || (nameParts[1] || '');
        const score = match.score || '0 - 0';
        const liveTime = match.liveTime || '-';
        const startTime = match.startUtc ? new Date(match.startUtc).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' }) : '-';

        let marketsHtml = '';

        if (markets.length === 0) {
            marketsHtml = '<div class="no-markets">Jelenleg nincsenek elérhető fogadási piacok ehhez a meccshez.</div>';
        } else {
            // Szűrjük ki az üres selections-öket
            const validMarkets = markets.filter(m => m.selections && m.selections.length > 0);

            if (validMarkets.length === 0) {
                marketsHtml = '<div class="no-markets">Jelenleg nincsenek elérhető fogadási piacok ehhez a meccshez.</div>';
            } else {
                validMarkets.forEach(market => {
                    const specialVal = market.specialValue ? ` (${market.specialValue})` : '';
                    marketsHtml += `
                        <div class="market-card">
                            <div class="market-header">
                                <span class="market-name">${escapeHtml(market.name)}${escapeHtml(specialVal)}</span>
                            </div>
                            <div class="market-selections">
                                ${market.selections.map(sel => `
                                    <button class="selection-btn" 
                                        onclick="addToBetslip('${escapeJs(homeTeam)}', '${escapeJs(awayTeam)}', '${escapeJs(sel.name)}', ${sel.odd})">
                                        <span class="selection-name">${escapeHtml(sel.name)}</span>
                                        <span class="selection-odd">${sel.odd.toFixed(2)}</span>
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                    `;
                });
            }
        }

        container.innerHTML = `
            <div class="match-details">
                <button class="back-btn" id="back-to-matches">
                    <i class="fas fa-arrow-left"></i> Vissza az élő meccsekhez
                </button>

                <div class="match-header-card">
                    <div class="match-meta">
                        <span class="meta-item"><i class="fas fa-globe-europe"></i> ${escapeHtml(match.country)}</span>
                        <span class="meta-item"><i class="fas fa-trophy"></i> ${escapeHtml(match.championship)}</span>
                        <span class="meta-item"><i class="fas fa-clock"></i> ${startTime}</span>
                    </div>
                    <div class="match-scoreboard">
                        <div class="team-side home-side">
                            <span class="team-name-big">${escapeHtml(homeTeam)}</span>
                        </div>
                        <div class="score-center">
                            <div class="score-big">${escapeHtml(score)}</div>
                            <div class="live-badge">
                                <span class="live-dot-big"></span>
                                <span class="live-time-big">${escapeHtml(liveTime)}</span>
                            </div>
                        </div>
                        <div class="team-side away-side">
                            <span class="team-name-big">${escapeHtml(awayTeam)}</span>
                        </div>
                    </div>
                </div>

                <h3 class="markets-title"><i class="fas fa-chart-bar"></i> Fogadási piacok</h3>
                <div class="markets-container">
                    ${marketsHtml}
                </div>
            </div>
        `;

        // Visszagomb
        document.getElementById('back-to-matches').addEventListener('click', () => {
            viewingDetails = false;
            refreshLiveMatches();
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeJs(str) {
        if (!str) return '';
        return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
    }

    function startAutoRefresh() {
        refreshInterval = setInterval(refreshLiveMatches, 60000);
    }

    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }
    });

    // Első betöltéskor is csatoljuk a kattintás kezelőket
    attachMatchClickHandlers();
    startAutoRefresh();
});