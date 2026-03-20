document.addEventListener('DOMContentLoaded', function () {

  // ========== ELEMEK ==========
  const sportsList = document.getElementById('sportsList');
  const sportDetailPanel = document.getElementById('sportDetailPanel');
  const sportDetailContent = document.getElementById('sportDetailContent');
  const sidebarBackBtn = document.getElementById('sidebarBackBtn');
  const sidebarSearch = document.getElementById('sidebarSearch');
  const matchesContainer = document.getElementById('matches-container');
  const centerTitle = document.getElementById('centerTitle');
  const matchSearch = document.getElementById('matchSearch');
  const currentDateTimeSpan = document.getElementById('currentDateTime');

  let sportsData = []; // sidebar adatok cache
  let currentSportId = 0; // 0 = összes sport

  // ========== DÁTUM/IDŐ ==========
  function updateDateTime() {
      if (!currentDateTimeSpan) return;
      const now = new Date();
      const opts = {
          year: 'numeric', month: '2-digit', day: '2-digit',
          hour: '2-digit', minute: '2-digit', second: '2-digit'
      };
      currentDateTimeSpan.textContent = now.toLocaleString('hu-HU', opts);
  }
  updateDateTime();
  setInterval(updateDateTime, 1000);

  // ========== HTML ESCAPE ==========
  function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
  }

  // ========== SIDEBAR SPORTOK BETÖLTÉSE ==========
  function loadSidebarSports() {
      fetch('../../backend/ApiRequest/get_sidebar_sports.php')
          .then(res => res.json())
          .then(data => {
              sportsData = data;
              renderSportsList(data);
          })
          .catch(err => {
              console.error('[MAIN] Sidebar betöltési hiba:', err);
              sportsList.innerHTML = '<div class="sidebar-loading" style="color:#e74c3c;">Hiba a sportok betöltésekor.</div>';
          });
  }

  function renderSportsList(sports, filter) {
      if (!sportsList) return;

      const filterLower = (filter || '').toLowerCase();

      // Ha van szűrő, a meccsnevekben is keresünk
      let filtered = sports;
      if (filterLower) {
          filtered = sports.map(sport => {
              // Sport név egyezés
              const sportMatch = sport.sport_name.toLowerCase().includes(filterLower);

              // Országokon/bajnokságokon/meccseken belüli egyezés
              const filteredCountries = sport.countries.map(country => {
                  const countryMatch = country.country_name.toLowerCase().includes(filterLower);
                  const filteredComps = country.competitions.map(comp => {
                      const compMatch = comp.competition_name.toLowerCase().includes(filterLower);
                      const filteredMatches = comp.matches.filter(m =>
                          m.name.toLowerCase().includes(filterLower)
                      );
                      if (compMatch || filteredMatches.length > 0) {
                          return { ...comp, matches: compMatch ? comp.matches : filteredMatches };
                      }
                      return null;
                  }).filter(Boolean);

                  if (countryMatch || filteredComps.length > 0) {
                      return { ...country, competitions: countryMatch ? country.competitions : filteredComps };
                  }
                  return null;
              }).filter(Boolean);

              if (sportMatch || filteredCountries.length > 0) {
                  const newCount = filteredCountries.reduce((sum, c) =>
                      sum + c.competitions.reduce((s2, comp) => s2 + comp.matches.length, 0), 0
                  );
                  return {
                      ...sport,
                      countries: sportMatch ? sport.countries : filteredCountries,
                      match_count: sportMatch ? sport.match_count : newCount
                  };
              }
              return null;
          }).filter(Boolean);
      }

      if (filtered.length === 0) {
          sportsList.innerHTML = '<div class="sidebar-loading" style="color:#888;">Nincs találat.</div>';
          return;
      }

      let html = '';
      filtered.forEach(sport => {
          const countClass = sport.match_count > 0 ? 'has-matches' : '';
          const activeClass = (currentSportId === sport.sport_api_id) ? ' active' : '';
          html += `
              <div class="sidebar-sport-item${activeClass}" data-sport-id="${sport.sport_api_id}">
                  <i class="fas ${escapeHtml(sport.icon)} sidebar-sport-icon"></i>
                  <span class="sidebar-sport-name">${escapeHtml(sport.sport_name)}</span>
                  <span class="sidebar-sport-count ${countClass}">${sport.match_count}</span>
              </div>
          `;
      });
      sportsList.innerHTML = html;

      // Kattintás kezelés
      sportsList.querySelectorAll('.sidebar-sport-item').forEach(item => {
          item.addEventListener('click', function () {
              const sportId = parseInt(this.getAttribute('data-sport-id'));
              currentSportId = sportId;

              // Aktív osztály frissítése
              sportsList.querySelectorAll('.sidebar-sport-item').forEach(el => el.classList.remove('active'));
              this.classList.add('active');

              // Keressük meg a sport adatait
              const sport = sportsData.find(s => s.sport_api_id === sportId);
              if (sport && sport.countries && sport.countries.length > 0) {
                  showSportDetail(sport);
              }

              // Center meccsek frissítése erre a sportra
              loadMatches(sportId);
          });
      });
  }

  // ========== SIDEBAR SPORT RÉSZLETEK (drill-down) ==========
  function showSportDetail(sport) {
      if (!sportDetailPanel || !sportDetailContent) return;

      sportsList.style.display = 'none';
      sportDetailPanel.style.display = 'block';

      let html = `<div class="sidebar-sport-detail-title">
          <i class="fas ${escapeHtml(sport.icon)}"></i> ${escapeHtml(sport.sport_name)}
      </div>`;

      sport.countries.forEach(country => {
          html += `<div class="sidebar-country-group">
              <div class="sidebar-country-header">${escapeHtml(country.country_name)}</div>
              <div class="sidebar-country-content">`;

          country.competitions.forEach(comp => {
              html += `<div class="sidebar-comp-group">
                  <div class="sidebar-comp-header">${escapeHtml(comp.competition_name)}</div>
                  <div class="sidebar-comp-content">`;

              comp.matches.forEach(match => {
                  const time = match.start_time ? match.start_time.substring(11, 16) : '';
                  const liveIndicator = match.is_live
                      ? '<span class="live-indicator-sm"></span>'
                      : '';
                  html += `
                      <div class="sidebar-match-item" data-match-id="${match.api_id}">
                          ${liveIndicator}
                          <span class="match-name-sm">${escapeHtml(match.name)}</span>
                          <span class="match-time-sm">${escapeHtml(time)}</span>
                      </div>
                  `;
              });

              html += '</div></div>';
          });

          html += '</div></div>';
      });

      sportDetailContent.innerHTML = html;

      // Ország csoportok nyitás/zárás
      sportDetailContent.querySelectorAll('.sidebar-country-header').forEach(header => {
          header.addEventListener('click', function () {
              this.parentElement.classList.toggle('open');
          });
      });

      // Bajnokság csoportok nyitás/zárás
      sportDetailContent.querySelectorAll('.sidebar-comp-header').forEach(header => {
          header.addEventListener('click', function () {
              this.parentElement.classList.toggle('open');
          });
      });

      // Meccsre kattintás a sidebarban
      sportDetailContent.querySelectorAll('.sidebar-match-item').forEach(item => {
          item.addEventListener('click', function () {
              const matchId = parseInt(this.getAttribute('data-match-id'));
              if (matchId) {
                  loadMatchDetails(matchId);
              }
          });
      });
  }

  // Vissza gomb
  if (sidebarBackBtn) {
      sidebarBackBtn.addEventListener('click', function () {
          sportDetailPanel.style.display = 'none';
          sportsList.style.display = 'flex';
          currentSportId = 0;
          sportsList.querySelectorAll('.sidebar-sport-item').forEach(el => el.classList.remove('active'));
          loadMatches(0);
      });
  }

  // ========== SIDEBAR KERESÉS ==========
  if (sidebarSearch) {
      sidebarSearch.addEventListener('input', function () {
          const val = this.value.trim();
          // Ha a detail panel nyitva van, térjünk vissza a listához
          if (sportDetailPanel && sportDetailPanel.style.display !== 'none') {
              sportDetailPanel.style.display = 'none';
              sportsList.style.display = 'flex';
          }
          renderSportsList(sportsData, val);
      });
  }

  // ========== MECCSEK BETÖLTÉSE (CENTER) ==========
  function loadMatches(sportId) {
      if (!matchesContainer) return;

      matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Meccsek betöltése...</div>';

      let url = '../../backend/ApiRequest/mainmenu_matches.php';
      if (sportId && sportId > 0) {
          url += '?sport_id=' + sportId;
      }

      // Cím frissítése
      if (centerTitle) {
          if (sportId && sportId > 0) {
              const sport = sportsData.find(s => s.sport_api_id === sportId);
              const sportName = sport ? sport.sport_name : 'Sport';
              centerTitle.innerHTML = `<i class="fas ${sport ? sport.icon : 'fa-trophy'}"></i> ${escapeHtml(sportName)} meccsek`;
          } else {
              centerTitle.innerHTML = '<i class="fas fa-calendar-day"></i> Mai meccsek';
          }
      }

      fetch(url)
          .then(res => res.text())
          .then(html => {
              matchesContainer.innerHTML = html;
              attachMatchClickHandlers();
              attachOddsButtonHandlers();

              // Ha angol nyelvű mód van, fordítsuk le az új tartalmat
              if (typeof window.changeLanguageForContainer === 'function') {
                  const lang = localStorage.getItem('lang') || 'hu';
                  if (lang !== 'hu') {
                      window.changeLanguageForContainer(matchesContainer, lang);
                  }
              }
          })
          .catch(err => {
              console.error('[MAIN] Meccsek betöltési hiba:', err);
              matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>Hiba a meccsek betöltésekor.</div>';
          });
  }

  // ========== CENTER KERESÉS ==========
  if (matchSearch) {
      let searchTimeout = null;
      matchSearch.addEventListener('input', function () {
          clearTimeout(searchTimeout);
          const val = this.value.trim().toLowerCase();

          searchTimeout = setTimeout(() => {
              const rows = matchesContainer.querySelectorAll('.match-row');
              if (rows.length === 0) return;

              let visibleCount = 0;
              rows.forEach(row => {
                  const text = row.textContent.toLowerCase();
                  if (val === '' || text.includes(val)) {
                      row.style.display = '';
                      visibleCount++;
                  } else {
                      row.style.display = 'none';
                  }
              });

              // Ha nincs találat, jelezzük
              let noResult = matchesContainer.querySelector('.search-no-result');
              if (visibleCount === 0 && val !== '') {
                  if (!noResult) {
                      noResult = document.createElement('div');
                      noResult.className = 'search-no-result no-matches';
                      noResult.style.marginTop = '16px';
                      noResult.textContent = 'Nincs találat a keresésre.';
                      matchesContainer.appendChild(noResult);
                  }
              } else if (noResult) {
                  noResult.remove();
              }
          }, 250);
      });
  }

  // ========== MECCS SOR KATTINTÁS ==========
  function attachMatchClickHandlers() {
      const matchRows = matchesContainer.querySelectorAll('.match-row.clickable');
      matchRows.forEach(row => {
          row.addEventListener('click', function (e) {
              // Ha odds gombra kattintottak, ne nyissuk meg a részleteket
              if (e.target.closest('.selection-btn') || e.target.closest('.btn-add-bet')) {
                  return;
              }
              const matchId = parseInt(this.getAttribute('data-match-id'));
              if (matchId) {
                  loadMatchDetails(matchId);
              }
          });
      });
  }

  // ========== ODDS GOMBOK KEZELÉSE ==========
  function attachOddsButtonHandlers(container) {
      const target = container || matchesContainer;
      if (!target) return;

      const selectionBtns = target.querySelectorAll('.selection-btn');
      selectionBtns.forEach(btn => {
          btn.addEventListener('click', function (e) {
              if (this.classList.contains('disabled')) return;

              e.preventDefault();
              e.stopPropagation();

              const homeTeam = this.getAttribute('data-home');
              const awayTeam = this.getAttribute('data-away');
              const pick = this.getAttribute('data-pick');
              const odds = parseFloat(this.getAttribute('data-odd'));
              const market = this.getAttribute('data-market');
              const matchId = parseInt(this.getAttribute('data-match-id')) || 0;

              if (!homeTeam || !awayTeam || !pick || !market) return;

              if (typeof window.toggleOdds === 'function') {
                  window.toggleOdds(homeTeam, awayTeam, pick, odds, market, matchId);
                  setTimeout(() => {
                      if (typeof window.refreshAllOddsButtons === 'function') {
                          window.refreshAllOddsButtons();
                      }
                  }, 50);
              }
          });
      });
  }

  // ========== MECCS RÉSZLETEK BETÖLTÉSE ==========
  function loadMatchDetails(eventId) {
      if (!matchesContainer) return;

      matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> Meccs adatok betöltése...</div>';

      fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + eventId)
          .then(res => res.json())
          .then(data => {
              renderMatchDetails(data);
          })
          .catch(err => {
              console.error('[MAIN] Meccs részletek hiba:', err);
              matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>Hiba a meccs adatok betöltésekor.</div>';
          });
  }

  // ========== MECCS RÉSZLETEK RENDERELÉSE ==========
  function renderMatchDetails(matchData) {
      if (!matchData || matchData.error) {
          matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Hiba: ' + escapeHtml(matchData ? matchData.error : 'Ismeretlen hiba') + '</div>';
          return;
      }

      const match = matchData.match;
      if (!match) {
          matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> Nincsenek meccs adatok.</div>';
          return;
      }

      const markets = matchData.markets || [];

      let html = `
          <button class="back-btn" id="back-to-matches">
              <i class="fas fa-arrow-left"></i> Vissza a meccsekhez
          </button>

          <div class="match-header-card">
              <div class="match-meta">
                  <span class="meta-item"><i class="fas fa-globe-europe"></i> ${escapeHtml(match.country || 'Ismeretlen')}</span>
                  <span class="meta-item"><i class="fas fa-trophy"></i> ${escapeHtml(match.championship || 'Ismeretlen')}</span>
                  <span class="meta-item"><i class="fas fa-clock"></i> ${escapeHtml(match.startUtc ? new Date(match.startUtc).toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' }) : '-')}</span>
              </div>
              <div class="match-scoreboard">
                  <div class="team-side home-side">
                      <span class="team-name-big">${escapeHtml(match.homeTeam || '')}</span>
                  </div>
                  <div class="score-center">
                      <div class="score-big">${escapeHtml(match.score || '0 - 0')}</div>
                      ${match.isLive
              ? `<div class="live-badge"><span class="live-dot-big"></span><span class="live-time-big">${escapeHtml(match.liveTime || '-')}</span></div>`
              : '<div class="not-started-badge"><i class="fas fa-clock"></i> Nem élő</div>'}
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
                      const state = window.BetslipLogic
                          ? window.BetslipLogic.getButtonState(match.homeTeam, match.awayTeam, selection.name, marketFullName)
                          : null;
                      const stateClass = state ? ' ' + state : '';
                      const isDisabled = state === 'disabled' ? ' disabled' : '';

                      html += `
                          <button class="selection-btn${stateClass}"${isDisabled}
                              data-match-id="${match.id}"
                              data-home="${escapeHtml(match.homeTeam || '')}"
                              data-away="${escapeHtml(match.awayTeam || '')}"
                              data-pick="${escapeHtml(selection.name)}"
                              data-market="${escapeHtml(marketFullName)}"
                              data-odd="${oddsValue}">
                              <span class="selection-name">${escapeHtml(selection.name)}</span>
                              <span class="selection-odd">${oddsValue.toFixed(2)}</span>
                          </button>`;
                  });
              }

              html += '</div></div>';
          });

          html += '</div>';
      } else {
          html += '<div class="no-markets"><i class="fas fa-info-circle"></i> Nincsenek elérhető piacok ehhez a mérkőzéshez.</div>';
      }

      matchesContainer.innerHTML = html;

      // Vissza gomb
      const backBtn = document.getElementById('back-to-matches');
      if (backBtn) {
          backBtn.addEventListener('click', function () {
              loadMatches(currentSportId);
          });
      }

      // Odds gombok
      attachOddsButtonHandlers();

      // Nyelv
      if (typeof window.changeLanguageForContainer === 'function') {
          const lang = localStorage.getItem('lang') || 'hu';
          if (lang !== 'hu') {
              window.changeLanguageForContainer(matchesContainer, lang);
          }
      }
  }

  // ========== AUTO REFRESH (60 másodpercenként) ==========
  setInterval(() => {
      // Csak akkor frissítsünk, ha a meccsek listája látható (nem a részletek)
      if (matchesContainer && !matchesContainer.querySelector('.back-btn')) {
          loadMatches(currentSportId);
      }
      // Sidebar is frissüljön
      loadSidebarSports();
  }, 60000);

  // ========== INICIALIZÁLÁS ==========
  loadSidebarSports();
  loadMatches(0);
});