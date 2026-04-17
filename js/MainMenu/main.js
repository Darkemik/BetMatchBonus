document.addEventListener('DOMContentLoaded', function () {
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));
    const td = (text) => (typeof window.i18nDynamic === 'function' ? window.i18nDynamic(text) : text);

  // ========== ELEMEK ==========
  const sportsList = document.getElementById('sportsList');
  const sportDetailPanel = document.getElementById('sportDetailPanel');
  const sportDetailContent = document.getElementById('sportDetailContent');
  const sidebarBackBtn = document.getElementById('sidebarBackBtn');
  const matchesContainer = document.getElementById('matches-container');
  const centerTitle = document.getElementById('centerTitle');
  const matchSearch = document.getElementById('matchSearch');
  const currentDateTimeSpan = document.getElementById('currentDateTime');
    const contentParent = document.querySelector('.content-parent');
    const mainContentWrap = contentParent ? contentParent.querySelector('.main_content') : null;
    const rightColumnWrap = contentParent ? contentParent.querySelector('.right-container') : null;
    const mobileBetslipFab = document.getElementById('mobile-betslip-fab');
    const mobileBetslipFabCount = document.getElementById('mobile-betslip-fab-count');
    const mobileBetslipBackdrop = document.getElementById('mobile-betslip-backdrop');
    const mobileBetslipClose = document.getElementById('mobile-betslip-close');
    const mobileBetslipSlot = document.getElementById('mainmenu-betslip-slot');
    const mobileViewportQuery = window.matchMedia('(max-width: 768px)');

  let sportsData = []; // sidebar adatok cache
  let currentSportId = 66; // 66 = Foci (alapértelmezett)
  let isFinishedView = false; // Lejátszott meccsek nézet
  let currentSortMode = 'priority'; // 'priority' vagy 'time'
  const sortToggleBtn = document.getElementById('sortToggleBtn');

  // ========== LAPOZÁS ==========
  const PAGE_SIZE = 20;
  let currentPage = 1;

  // ========== LIGA PRIORITÁS ==========
  const PRIORITY_LEAGUES = [
      'világbajnokság', 'world cup', 'vb',
      'nemzetek ligája', 'nations league',
      'európa-bajnokság', 'euro 20', 'uefa euro',
      'bajnokok ligája', 'champions league',
      'europa league', 'európa liga',
      'conference league', 'konferencia liga',
      'nb i', 'nb1', 'nemzeti bajnokság', 'otp bank liga',
      'premier league',
      'la liga', 'laliga',
      'bundesliga',
      'serie a',
      'ligue 1',
      'nb ii', 'nb2',
      'eredivisie',
      'primeira liga',
  ];

  function getLeaguePriority(leagueName) {
      const lower = (leagueName || '').toLowerCase();
      for (let i = 0; i < PRIORITY_LEAGUES.length; i++) {
          if (lower.includes(PRIORITY_LEAGUES[i])) return i;
      }
      return PRIORITY_LEAGUES.length;
  }

  function filterNARows() {
      if (!matchesContainer) return;
      matchesContainer.querySelectorAll('.match-row').forEach(row => {
          const league = (row.querySelector('.league-name')?.textContent || '').trim().toLowerCase();
          const country = (row.querySelector('.country-name')?.textContent || '').trim().toLowerCase();
          if (league === 'n/a' || country === 'n/a') {
              row.remove();
          }
      });
  }

  function sortMatchesByPriority() {
      if (!matchesContainer) return;
      const tbody = matchesContainer.querySelector('tbody');
      if (!tbody) return;
      const rows = Array.from(tbody.querySelectorAll('.match-row'));
      if (rows.length < 2) return;
      rows.sort((a, b) => {
          const aLive = a.querySelector('.live-dot') ? 0 : 1;
          const bLive = b.querySelector('.live-dot') ? 0 : 1;
          if (aLive !== bLive) return aLive - bLive;
          const aLeague = (a.querySelector('.league-name')?.textContent || '').trim();
          const bLeague = (b.querySelector('.league-name')?.textContent || '').trim();
          return getLeaguePriority(aLeague) - getLeaguePriority(bLeague);
      });
      rows.forEach(row => tbody.appendChild(row));
  }

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

  function applyDynamicTranslations(root) {
      if (!root) return;
      root.querySelectorAll('.country-name, .league-name, .league-country, .league-title, .market-name, .selection-name, .sidebar-country-header, .sidebar-comp-header, .user-bet-market, .tip-league, .tip-combo-market').forEach(el => {
          el.textContent = td(el.textContent);
      });
  }

  function syncColumnsToTipsBottom() {
      if (!mainContentWrap || !rightColumnWrap) return;

      if (window.innerWidth <= 1100) {
          mainContentWrap.style.height = 'auto';
          return;
      }

      const rightHeight = Math.ceil(rightColumnWrap.getBoundingClientRect().height);
      if (rightHeight > 0) {
          mainContentWrap.style.height = rightHeight + 'px';
      }
  }

  function scheduleColumnsSync() {
      requestAnimationFrame(function() {
          requestAnimationFrame(syncColumnsToTipsBottom);
      });
  }

  function isMobileViewport() {
      return !!(mobileViewportQuery && mobileViewportQuery.matches);
  }

  function openMobileBetslip() {
      if (!isMobileViewport()) return;
      document.body.classList.add('mobile-betslip-open');
      if (mobileBetslipFab) {
          mobileBetslipFab.setAttribute('aria-expanded', 'true');
      }
      if (mobileBetslipSlot) {
          mobileBetslipSlot.setAttribute('aria-hidden', 'false');
      }
  }

  function closeMobileBetslip() {
      document.body.classList.remove('mobile-betslip-open');
      if (mobileBetslipFab) {
          mobileBetslipFab.setAttribute('aria-expanded', 'false');
      }
      if (mobileBetslipSlot) {
          mobileBetslipSlot.setAttribute('aria-hidden', 'true');
      }
  }

  function syncMobileBetslipCount() {
      if (!mobileBetslipFabCount) return;
      const countEl = document.getElementById('betslip-count');
      const countText = countEl ? String(countEl.textContent || '').trim() : '0';
      const normalized = countText === '' ? '0' : countText;
      mobileBetslipFabCount.textContent = normalized;
  }

  // ========== SIDEBAR SPORTOK BETÖLTÉSE ==========
  function loadSidebarSports() {
      fetch('../../backend/ApiRequest/get_sidebar_sports.php')
          .then(res => res.json())
          .then(data => {
              sportsData = data;
              renderSportsList(data);
              scheduleColumnsSync();
          })
          .catch(err => {
              console.error('[MAIN] Sidebar betöltési hiba:', err);
              sportsList.innerHTML = '<div class="sidebar-loading" style="color:#e74c3c;">' + t('mainMenu.sidebarLoadError', 'Hiba a sportok betöltésekor.') + '</div>';
          });
  }

  function renderSportsList(sports, filter) {
      if (!sportsList) return;

      const filterLower = (filter || '').toLowerCase();
      const hiddenSportApiIds = new Set([146, 147, 148]);
      const hiddenSportNames = new Set(['e-labdarúgás', 'e-kosárlabda', 'e-jégkorong', 'e-labdarugas', 'e-kosarlabda', 'e-jegkorong']);
      const isEsportSport = (sport) => {
          const name = ((sport && sport.sport_name) ? String(sport.sport_name) : '').trim().toLowerCase();
          return name === 'e-sportok' || name === 'e-sport' || name === 'esport' || name === 'esports';
      };
      const isHiddenMainMenuSport = (sport) => {
          const sportApiId = Number((sport && sport.sport_api_id) || 0);
          const name = ((sport && sport.sport_name) ? String(sport.sport_name) : '').trim().toLowerCase();
          return hiddenSportApiIds.has(sportApiId) || hiddenSportNames.has(name);
      };

      let filtered = sports;
      if (filterLower) {
          filtered = sports.map(sport => {
              const sportMatch = sport.sport_name.toLowerCase().includes(filterLower);
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

    // Főoldali bal sáv: az E-sportok gyűjtő, és a 3 e-sport variáns ne jelenjen meg külön blokkban.
    filtered = filtered.filter(sport => !isEsportSport(sport) && !isHiddenMainMenuSport(sport));

      if (filtered.length === 0) {
          sportsList.innerHTML = '<div class="sidebar-loading" style="color:#888;">' + t('mainMenu.noSearchResult', 'Nincs találat a keresésre.') + '</div>';
          return;
      }

      // N/A országok és bajnokságok szűrése a sidebarból
      filtered = filtered.map(sport => {
          const cleanCountries = (sport.countries || [])
              .filter(c => !c.country_name || c.country_name.trim().toLowerCase() !== 'n/a')
              .map(c => ({
                  ...c,
                  competitions: (c.competitions || []).filter(
                      comp => !comp.competition_name || comp.competition_name.trim().toLowerCase() !== 'n/a'
                  )
              }));
          return { ...sport, countries: cleanCountries };
      });

      let html = '';
      filtered.forEach(sport => {
          const displayCount = isFinishedView ? (sport.finished_count || 0) : sport.match_count;
          const countClass = displayCount > 0 ? (isFinishedView ? 'has-finished' : 'has-matches') : '';
          const activeClass = (currentSportId === sport.sport_api_id) ? ' active' : '';
          html += `
              <div class="sidebar-sport-item${activeClass}" data-sport-id="${sport.sport_api_id}">
                  <i class="fas ${escapeHtml(sport.icon)} sidebar-sport-icon"></i>
                  <span class="sidebar-sport-name">${escapeHtml(td(sport.sport_name))}</span>
                  <span class="sidebar-sport-count ${countClass}">${displayCount}</span>
              </div>
          `;
      });
      sportsList.innerHTML = html;

      // Kattintás kezelés — csak meccsek töltése, liga panel NEM nyílik
      sportsList.querySelectorAll('.sidebar-sport-item').forEach(item => {
          item.addEventListener('click', function () {
              const sportId = parseInt(this.getAttribute('data-sport-id'));
              currentSportId = sportId;

              // Aktív osztály frissítése
              sportsList.querySelectorAll('.sidebar-sport-item').forEach(el => el.classList.remove('active'));
              this.classList.add('active');

              // Center meccsek frissítése erre a sportra
              if (isFinishedView) {
                  loadFinishedMatches(sportId);
              } else {
                  loadMatches(sportId);
              }
          });
      });
  }

  // ========== SIDEBAR SPORT RÉSZLETEK (drill-down) ==========
  function showSportDetail(sport) {
      if (!sportDetailPanel || !sportDetailContent) return;

      sportsList.style.display = 'none';
      sportDetailPanel.style.display = 'block';

      let html = `<div class="sidebar-sport-detail-title">
          <i class="fas ${escapeHtml(sport.icon)}"></i> ${escapeHtml(td(sport.sport_name))}
      </div>`;

      sport.countries.forEach(country => {
          html += `<div class="sidebar-country-group">
              <div class="sidebar-country-header">${escapeHtml(td(country.country_name))}</div>
              <div class="sidebar-country-content">`;

          country.competitions.forEach(comp => {
              html += `<div class="sidebar-comp-group">
                  <div class="sidebar-comp-header">${escapeHtml(td(comp.competition_name))}</div>
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

      sportDetailContent.querySelectorAll('.sidebar-country-header').forEach(header => {
          header.addEventListener('click', function () {
              this.parentElement.classList.toggle('open');
          });
      });

      sportDetailContent.querySelectorAll('.sidebar-comp-header').forEach(header => {
          header.addEventListener('click', function () {
              this.parentElement.classList.toggle('open');
          });
      });

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
          currentSportId = 66;
          sportsList.querySelectorAll('.sidebar-sport-item').forEach(el => el.classList.remove('active'));
          const fociItem = sportsList.querySelector('[data-sport-id="66"]');
          if (fociItem) fociItem.classList.add('active');
          loadMatches(66);
      });
  }

  // ========== LAPOZÁS (bajnokság-csoportonként) ==========
  const LEAGUE_PAGE_SIZE = 15; // Ennyi bajnokság-csoport látható alapból

  function applyPagination() {
      currentPage = 1;
      const groups = matchesContainer.querySelectorAll('.league-group');

      // Meglévő "Több betöltése" gomb eltávolítása
      const existingBtn = matchesContainer.querySelector('.load-more-btn-wrapper');
      if (existingBtn) existingBtn.remove();

      if (groups.length <= LEAGUE_PAGE_SIZE) {
          groups.forEach(g => { g.style.display = ''; });
          return;
      }

      groups.forEach((g, i) => {
          g.style.display = i < LEAGUE_PAGE_SIZE ? '' : 'none';
      });

      createLoadMoreBtn(groups.length);
  }

  function createLoadMoreBtn(totalCount) {
      const existing = matchesContainer.querySelector('.load-more-btn-wrapper');
      if (existing) existing.remove();

      const visible = currentPage * LEAGUE_PAGE_SIZE;
      if (visible >= totalCount) return;

      const remaining = totalCount - visible;
      const wrapper = document.createElement('div');
      wrapper.className = 'load-more-btn-wrapper';

      const btn = document.createElement('button');
      btn.className = 'load-more-btn';
      btn.innerHTML = `<i class="fas fa-chevron-down"></i> ${t('mainMenu.loadMore', 'Több betöltése')} (${remaining} ${t('mainMenu.leaguesWord', 'bajnokság')})`;

      btn.addEventListener('click', () => {
          currentPage++;
          const allGroups = matchesContainer.querySelectorAll('.league-group');
          allGroups.forEach((g, i) => {
              if (i < currentPage * LEAGUE_PAGE_SIZE) g.style.display = '';
          });
          const newRemaining = Math.max(0, totalCount - currentPage * LEAGUE_PAGE_SIZE);
          if (newRemaining === 0) {
              wrapper.remove();
          } else {
              btn.innerHTML = `<i class="fas fa-chevron-down"></i> ${t('mainMenu.loadMore', 'Több betöltése')} (${newRemaining} ${t('mainMenu.leaguesWord', 'bajnokság')})`;
          }
      });

      wrapper.appendChild(btn);
      matchesContainer.appendChild(wrapper);
  }

  // ========== MECCSEK BETÖLTÉSE (CENTER) ==========
  function loadMatches(sportId) {
      if (!matchesContainer) return;

    matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> ' + t('mainMenu.loadingMatches', 'Meccsek betöltése...') + '</div>';

      let url = '../../backend/ApiRequest/mainmenu_matches.php';
      if (sportId && sportId > 0) {
          url += '?sport_id=' + sportId;
      }
      // Rendezési mód hozzáadása
      url += (url.includes('?') ? '&' : '?') + 'sort=' + currentSortMode;

      // Cím frissítése
      if (centerTitle) {
          if (sportId && sportId > 0) {
              const sport = sportsData.find(s => s.sport_api_id === sportId);
              const sportName = sport ? td(sport.sport_name) : 'Sport';
              centerTitle.innerHTML = `<i class="fas ${sport ? sport.icon : 'fa-trophy'}"></i> ${escapeHtml(sportName)} ${t('mainMenu.matchesWord', 'meccsek')}`;
          } else {
              centerTitle.innerHTML = '<i class="fas fa-calendar-day"></i> ' + t('mainMenu.todayMatches', 'Mai meccsek');
          }
      }

      fetch(url)
          .then(res => res.text())
          .then(html => {
              matchesContainer.innerHTML = html;
              filterNARows();
              sortMatchesByPriority();
              attachMatchClickHandlers();
              attachOddsButtonHandlers();
              applyPagination();
              if (typeof window.applyI18n === 'function') window.applyI18n(matchesContainer);
              applyDynamicTranslations(matchesContainer);
              scheduleColumnsSync();

          })
          .catch(err => {
              console.error('[MAIN] Meccsek betöltési hiba:', err);
              matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>' + t('mainMenu.errorLoading', 'Hiba a meccsek betöltésekor.') + '</div>';
              scheduleColumnsSync();
          });
  }

  // ========== RENDEZÉS VÁLTÓ GOMB ==========
  if (sortToggleBtn) {
      sortToggleBtn.addEventListener('click', function () {
          if (isFinishedView) return; // lejátszott nézetben nem váltunk
          currentSortMode = currentSortMode === 'priority' ? 'time' : 'priority';
          updateSortButtonUI();
          loadMatches(currentSportId);
      });
  }
  function updateSortButtonUI() {
      if (!sortToggleBtn) return;
      if (currentSortMode === 'priority') {
          sortToggleBtn.innerHTML = '<i class="fas fa-trophy"></i><span class="sort-toggle-label">Fontosság</span>';
          sortToggleBtn.title = 'Váltás időrendi sorrendre';
          sortToggleBtn.classList.remove('sort-mode-time');
          sortToggleBtn.classList.add('sort-mode-priority');
      } else {
          sortToggleBtn.innerHTML = '<i class="fas fa-clock"></i><span class="sort-toggle-label">Időrend</span>';
          sortToggleBtn.title = 'Váltás fontossági sorrendre';
          sortToggleBtn.classList.remove('sort-mode-priority');
          sortToggleBtn.classList.add('sort-mode-time');
      }
  }
  updateSortButtonUI();

  // ========== CENTER KERESÉS ==========
  if (matchSearch) {
      let searchTimeout = null;
      matchSearch.addEventListener('input', function () {
          clearTimeout(searchTimeout);
          const val = this.value.trim().toLowerCase();

          searchTimeout = setTimeout(() => {
              const rows = matchesContainer.querySelectorAll('.match-row');
              const leagueGroups = matchesContainer.querySelectorAll('.league-group');
              if (rows.length === 0) return;

              if (val === '') {
                  // Keresés ürítve: minden league-group és első meccs látható
                  const noResult = matchesContainer.querySelector('.search-no-result');
                  if (noResult) noResult.remove();
                  leagueGroups.forEach(lg => {
                      lg.style.display = '';
                      lg.classList.remove('expanded');
                  });
                  rows.forEach(row => { row.style.display = ''; });
                  applyPagination();
                  return;
              }

              // Keresési mód: meccseket szűrjük, league-group-okat is kezeljük
              let visibleCount = 0;
              leagueGroups.forEach(lg => {
                  const lgRows = lg.querySelectorAll('.match-row');
                  let hasVisible = false;
                  lgRows.forEach(row => {
                      const text = row.textContent.toLowerCase();
                      if (text.includes(val)) {
                          row.style.display = '';
                          hasVisible = true;
                          visibleCount++;
                      } else {
                          row.style.display = 'none';
                      }
                  });
                  lg.style.display = hasVisible ? '' : 'none';
                  if (hasVisible) lg.classList.add('expanded');
              });

              // "Több betöltése" gomb elrejtése keresés alatt
              const loadMoreWrapper = matchesContainer.querySelector('.load-more-btn-wrapper');
              if (loadMoreWrapper) loadMoreWrapper.style.display = 'none';

              let noResult = matchesContainer.querySelector('.search-no-result');
              if (visibleCount === 0) {
                  if (!noResult) {
                      noResult = document.createElement('div');
                      noResult.className = 'search-no-result no-matches';
                      noResult.style.marginTop = '16px';
                      noResult.textContent = t('mainMenu.noSearchResult', 'Nincs találat a keresésre.');
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
              if (this.classList.contains('disabled') || this.classList.contains('market-locked')) return;

              e.preventDefault();
              e.stopPropagation();

              const homeTeam = this.getAttribute('data-home');
              const awayTeam = this.getAttribute('data-away');
              const pick = this.getAttribute('data-pick');
              const odds = parseFloat(this.getAttribute('data-odd'));
              const market = this.getAttribute('data-market');
              const matchId = parseInt(this.getAttribute('data-match-id')) || 0;
              const isBoostedSel = this.hasAttribute('data-boosted');
              const originalOddsAttr = this.getAttribute('data-original-odd');
              const originalOdds = originalOddsAttr !== null ? parseFloat(originalOddsAttr) : null;

              if (!homeTeam || !awayTeam || !pick || !market) return;

              if (typeof window.toggleOdds === 'function') {
                  window.toggleOdds(homeTeam, awayTeam, pick, odds, market, matchId, false, isBoostedSel, originalOdds);
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

    matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> ' + t('mainMenu.loadingMatchDetails', 'Meccs adatok betöltése...') + '</div>';

      if (isFinishedView) {
          fetch('../../backend/ApiRequest/get_finished_match_details.php?eventId=' + eventId)
              .then(res => res.json())
              .then(data => { renderFinishedMatchDetails(data); })
              .catch(err => {
                  console.error('[MAIN] Lejátszott meccs részletek hiba:', err);
                  matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>' + t('mainMenu.errorMatchDetails', 'Hiba a meccs adatok betöltésekor.') + '</div>';
              });
          return;
      }

      fetch('../../backend/ApiRequest/get_match_details.php?eventId=' + eventId)
          .then(res => res.json())
          .then(data => {
              if (data && !data.error && data.markets && data.markets.length > 0) {
                  renderMatchDetails(data);
              } else {
                  // Nincs piac → próbáljuk lejátszott meccsként
                  fetch('../../backend/ApiRequest/get_finished_match_details.php?eventId=' + eventId)
                      .then(res2 => res2.json())
                      .then(data2 => {
                          if (data2 && !data2.error) {
                              renderFinishedMatchDetails(data2);
                          } else {
                              // Ha lejátszott sem talált, rendereljük a normál adatot odds nélkül
                              renderMatchDetails(data);
                          }
                      })
                      .catch(() => { renderMatchDetails(data); });
              }
          })
          .catch(err => {
              console.error('[MAIN] Meccs részletek hiba:', err);
              matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>' + t('mainMenu.errorMatchDetails', 'Hiba a meccs adatok betöltésekor.') + '</div>';
          });
  }

  // ========== MECCS RÉSZLETEK RENDERELÉSE ==========
  function renderMatchDetails(matchData) {
      if (!matchData || matchData.error) {
          matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('mainMenu.errorPrefix', 'Hiba:') + ' ' + escapeHtml(matchData ? matchData.error : t('mainMenu.unknown', 'Ismeretlen')) + '</div>';
          return;
      }

      const match = matchData.match;
      if (!match) {
          matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('mainMenu.noMatchData', 'Nincsenek meccs adatok.') + '</div>';
          return;
      }

      const markets = matchData.markets || [];
      const isBoostedMatch = match.isBoosted || false;

      let html = `
          <button class="back-btn" id="back-to-matches">
              <i class="fas fa-arrow-left"></i> ${t('mainMenu.backToMatches', 'Vissza a meccsekhez')}
          </button>

          <div class="match-header-card">
              ${isBoostedMatch ? '<div class="boosted-match-banner"><i class="fas fa-rocket"></i> ' + t('mainMenu.boostedMatchBanner', 'Oddsűrhajó — Kiemelt szorzó ezen a meccsen!') + '</div>' : ''}
              <div class="match-meta">
                  <span class="meta-item"><i class="fas fa-globe-europe"></i> ${escapeHtml(td(match.country || t('mainMenu.unknown', 'Ismeretlen')))}</span>
                  <span class="meta-item"><i class="fas fa-trophy"></i> ${escapeHtml(td(match.championship || t('mainMenu.unknown', 'Ismeretlen')))}</span>
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
              : '<div class="not-started-badge"><i class="fas fa-clock"></i> ' + t('mainMenu.notLive', 'Nem élő') + '</div>'}
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
                      const isBoosted = selection.boosted || false;
                      const originalOdds = parseFloat(selection.originalOdds) || 0;
                      const state = window.BetslipLogic
                          ? window.BetslipLogic.getButtonState(match.homeTeam, match.awayTeam, selection.name, marketFullName)
                          : null;
                      const isLockedByOdds = oddsValue <= 1;
                      const stateClass = state ? ' ' + state : '';
                      const lockClass = isLockedByOdds ? ' disabled market-locked' : '';
                      const boostedClass = isBoosted ? ' boosted-selection' : '';
                      const isDisabled = (state === 'disabled' || isLockedByOdds) ? ' disabled' : '';
                      const oddsContent = isLockedByOdds
                          ? `<span class="selection-lock" title="Nem fogadható"><i class="fas fa-lock"></i></span>`
                          : (isBoosted
                              ? `<span class="selection-odd boosted-odd-display">
                                    <span class="original-odd-crossed">${originalOdds.toFixed(2)}</span>
                                    <i class="fas fa-rocket boosted-icon-small"></i>
                                    ${oddsValue.toFixed(2)}
                                 </span>`
                              : `<span class="selection-odd">${oddsValue.toFixed(2)}</span>`);

                      html += `
                          <button class="selection-btn${stateClass}${lockClass}${boostedClass}"${isDisabled}
                              data-match-id="${match.id}"
                              data-home="${escapeHtml(match.homeTeam || '')}"
                              data-away="${escapeHtml(match.awayTeam || '')}"
                              data-pick="${escapeHtml(selection.name)}"
                              data-market="${escapeHtml(marketFullName)}"
                              data-odd="${oddsValue}"
                              ${isBoosted && originalOdds > 0 ? `data-original-odd="${originalOdds}"` : ''}
                              ${isBoosted ? 'data-boosted="1"' : ''}>
                              <span class="selection-name">${escapeHtml(td(selection.name))}</span>
                              ${oddsContent}
                          </button>`;
                  });
              }

              html += '</div></div>';
          });

          html += '</div>';
      } else {
          html += '<div class="no-markets"><i class="fas fa-info-circle"></i> ' + t('mainMenu.noMarkets', 'Nincsenek elérhető piacok ehhez a mérkőzéshez.') + '</div>';
      }

      matchesContainer.innerHTML = html;
    if (typeof window.applyI18n === 'function') window.applyI18n(matchesContainer);

      // Vissza gomb
      const backBtn = document.getElementById('back-to-matches');
      if (backBtn) {
          backBtn.addEventListener('click', function () {
              if (isFinishedView) {
                  loadFinishedMatches(currentSportId);
              } else {
                  loadMatches(currentSportId);
              }
          });
      }

      // Odds gombok
      attachOddsButtonHandlers();

      // Nyelv

  }

  // ========== LEJÁTSZOTT MECCS RÉSZLETEK ==========
  function renderFinishedMatchDetails(data) {
      if (!data || data.error) {
          matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('mainMenu.errorPrefix', 'Hiba:') + ' ' + escapeHtml(data ? data.error : t('mainMenu.unknown', 'Ismeretlen')) + '</div>';
          return;
      }

      const match = data.match;
      if (!match) {
          matchesContainer.innerHTML = '<div class="error-msg"><i class="fas fa-exclamation-triangle"></i> ' + t('mainMenu.noMatchData', 'Nincsenek meccs adatok.') + '</div>';
          return;
      }

      const markets = data.markets || [];
      const userBets = data.userBets || [];

      let html = `
          <button class="back-btn" id="back-to-matches">
              <i class="fas fa-arrow-left"></i> ${t('mainMenu.backToMatches', 'Vissza a meccsekhez')}
          </button>

          <div class="match-header-card finished-header">
              <div class="match-meta">
                  <span class="meta-item"><i class="fas fa-globe-europe"></i> ${escapeHtml(td(match.country || t('mainMenu.unknown', 'Ismeretlen')))}</span>
                  <span class="meta-item"><i class="fas fa-trophy"></i> ${escapeHtml(td(match.championship || t('mainMenu.unknown', 'Ismeretlen')))}</span>
                  <span class="meta-item"><i class="fas fa-calendar-alt"></i> ${escapeHtml(match.startTime || '-')}</span>
              </div>
              <div class="match-scoreboard">
                  <div class="team-side home-side">
                      <span class="team-name-big">${escapeHtml(match.homeTeam || '')}</span>
                  </div>
                  <div class="score-center">
                      <div class="score-big finished-score-big">${escapeHtml(match.score || '- - -')}</div>
                      <div class="finished-badge"><i class="fas fa-flag-checkered"></i> ${t('mainMenu.finished', 'Vége')}</div>
                  </div>
                  <div class="team-side away-side">
                      <span class="team-name-big">${escapeHtml(match.awayTeam || '')}</span>
                  </div>
              </div>
          </div>
      `;

      // ── MECCS ÖSSZESÍTŐ KÁRTYÁK ──
      html += `<div class="finished-info-cards">`;

      html += `<div class="info-card">
          <div class="info-card-icon"><i class="fas fa-futbol"></i></div>
          <div class="info-card-label">${t('mainMenu.finalResult', 'Végeredmény')}</div>
          <div class="info-card-value">${escapeHtml(match.score || '- - -')}</div>
      </div>`;

      html += `<div class="info-card">
          <div class="info-card-icon"><i class="fas fa-calendar-alt"></i></div>
          <div class="info-card-label">${t('mainMenu.matchDate', 'Dátum')}</div>
          <div class="info-card-value">${escapeHtml(match.startTime || '-')}</div>
      </div>`;

      html += `<div class="info-card">
          <div class="info-card-icon"><i class="fas fa-trophy"></i></div>
          <div class="info-card-label">${t('mainMenu.competition', 'Bajnokság')}</div>
          <div class="info-card-value">${escapeHtml(td(match.championship || '-'))}</div>
      </div>`;

      // Győztes megállapítás
      let winner = t('mainMenu.noData', 'Nincs adat');
      if (match.homeScore !== null && match.awayScore !== null) {
          if (match.homeScore > match.awayScore) winner = escapeHtml(match.homeTeam);
          else if (match.awayScore > match.homeScore) winner = escapeHtml(match.awayTeam);
          else winner = t('mainMenu.draw', 'Döntetlen');
      }
      html += `<div class="info-card">
          <div class="info-card-icon"><i class="fas fa-medal"></i></div>
          <div class="info-card-label">${t('mainMenu.winner', 'Győztes')}</div>
          <div class="info-card-value">${winner}</div>
      </div>`;

      html += `</div>`;

      // ── GÓL VIZUALIZÁCIÓ ──
      if (match.homeScore !== null && match.awayScore !== null) {
          const totalGoals = match.homeScore + match.awayScore;
          const homePct = totalGoals > 0 ? Math.round((match.homeScore / totalGoals) * 100) : 50;
          const awayPct = totalGoals > 0 ? 100 - homePct : 50;
          html += `
          <div class="goal-vis-section">
              <h3 class="section-title-sm"><i class="fas fa-chart-pie"></i> ${t('mainMenu.goalBreakdown', 'Gólmegoszlás')}</h3>
              <div class="goal-bar-wrapper">
                  <span class="goal-bar-label home">${escapeHtml(match.homeTeam)} (${match.homeScore})</span>
                  <div class="goal-bar">
                      <div class="goal-bar-home" style="width:${totalGoals > 0 ? homePct : 50}%">${totalGoals > 0 ? match.homeScore : ''}</div>
                      <div class="goal-bar-away" style="width:${totalGoals > 0 ? awayPct : 50}%">${totalGoals > 0 ? match.awayScore : ''}</div>
                  </div>
                  <span class="goal-bar-label away">${escapeHtml(match.awayTeam)} (${match.awayScore})</span>
              </div>
              <div class="goal-total">${t('mainMenu.totalGoals', 'Összes gól:')} <strong>${totalGoals}</strong></div>
          </div>`;
      }

      // ── FOGADÁSI STATISZTIKA ──
      const stats = data.bettingStats || {};
      if (stats.totalBets > 0) {
          const winRate = stats.wonCount + stats.lostCount > 0
              ? Math.round((stats.wonCount / (stats.wonCount + stats.lostCount)) * 100)
              : 0;
          html += `
          <h3 class="section-title-sm"><i class="fas fa-poll"></i> ${t('mainMenu.bettingStats', 'Fogadási statisztika')}</h3>
          <div class="betting-stats-grid">
              <div class="stat-card">
                  <div class="stat-icon"><i class="fas fa-users"></i></div>
                  <div class="stat-value">${stats.uniqueUsers}</div>
                  <div class="stat-label">${t('mainMenu.bettors', 'Fogadó')}</div>
              </div>
              <div class="stat-card">
                  <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                  <div class="stat-value">${stats.totalBets}</div>
                  <div class="stat-label">${t('mainMenu.totalBetsCount', 'Fogadás')}</div>
              </div>
              <div class="stat-card">
                  <div class="stat-icon"><i class="fas fa-percentage"></i></div>
                  <div class="stat-value">${winRate}%</div>
                  <div class="stat-label">${t('mainMenu.winRate', 'Nyerési arány')}</div>
              </div>
              <div class="stat-card">
                  <div class="stat-icon"><i class="fas fa-fire"></i></div>
                  <div class="stat-value">${escapeHtml(stats.topPick || '-')}</div>
                  <div class="stat-label">${t('mainMenu.popularPick', 'Népszerű tipp')} (${stats.topPickCount}x)</div>
              </div>
          </div>`;
      }

      // ── ZÁRÓ PIACOK ──
      if (markets.length > 0) {
          html += `<h3 class="markets-title"><i class="fas fa-chart-bar"></i> ${t('mainMenu.closingOdds', 'Záró szorzók')}</h3>`;
          html += '<div class="markets-container">';

          markets.forEach(market => {
              const specialVal = market.specialValue ? ' (' + market.specialValue + ')' : '';
              const marketFullName = td(market.name || '') + specialVal;
              html += `<div class="market-card finished-market">
                  <div class="market-header"><span class="market-name">${escapeHtml(marketFullName)}</span>
                  <span class="market-status-badge">${market.status || 'CLOSED'}</span></div>
                  <div class="market-selections">`;

              if (market.outcomes && Array.isArray(market.outcomes)) {
                  market.outcomes.forEach(outcome => {
                      const statusClass = outcome.status === 'WON' ? 'outcome-won' : (outcome.status === 'LOST' ? 'outcome-lost' : '');
                      const statusIcon = outcome.status === 'WON' ? '<i class="fas fa-check-circle"></i> ' : (outcome.status === 'LOST' ? '<i class="fas fa-times-circle"></i> ' : '');
                      html += `
                          <div class="selection-btn finished-outcome ${statusClass}">
                              <span class="selection-name">${statusIcon}${escapeHtml(td(outcome.label))}</span>
                              <span class="selection-odd">${outcome.odds.toFixed(2)}</span>
                          </div>`;
                  });
              }

              html += '</div></div>';
          });

          html += '</div>';
      }

      // ── USER FOGADÁSAI ──
      if (userBets.length > 0) {
          html += `<h3 class="markets-title"><i class="fas fa-ticket-alt"></i> ${t('mainMenu.yourBets', 'A te fogadásaid')}</h3>`;
          html += '<div class="user-bets-container">';

          userBets.forEach(bet => {
              const statusClass = bet.ticketStatus === 'WON' ? 'bet-won' : (bet.ticketStatus === 'LOST' ? 'bet-lost' : 'bet-open');
              const statusIcon = bet.ticketStatus === 'WON' ? '✅' : (bet.ticketStatus === 'LOST' ? '❌' : '⏳');
              html += `
                  <div class="user-bet-card ${statusClass}">
                      <div class="user-bet-header">
                          <span class="user-bet-market">${escapeHtml(td(bet.market || ''))}</span>
                          <span class="user-bet-status">${statusIcon} ${escapeHtml(bet.ticketStatus)}</span>
                      </div>
                      <div class="user-bet-body">
                          <div class="user-bet-row">
                              <span>${t('mainMenu.yourPick', 'Tipped:')}</span>
                              <span class="user-bet-pick">${escapeHtml(td(bet.pick || ''))}</span>
                          </div>
                          <div class="user-bet-row">
                              <span>${t('mainMenu.oddsAtPick', 'Odds:')}</span>
                              <span>${bet.oddsAtPick.toFixed(2)}</span>
                          </div>
                          <div class="user-bet-row">
                              <span>${t('mainMenu.stake', 'Tét:')}</span>
                              <span>${bet.stake.toLocaleString('hu-HU')} Ft</span>
                          </div>
                          <div class="user-bet-row highlight">
                              <span>${t('mainMenu.potentialWin', 'Nyeremény:')}</span>
                              <span>${bet.potentialWin.toLocaleString('hu-HU')} Ft</span>
                          </div>
                      </div>
                  </div>`;
          });

          html += '</div>';
      }

      // ── EGYMÁS ELLENI ELŐZMÉNYEK (H2H) ──
      const h2h = data.h2h || [];
      if (h2h.length > 0) {
          html += `<h3 class="section-title-sm"><i class="fas fa-exchange-alt"></i> ${t('mainMenu.h2hHistory', 'Egymás elleni előzmények')}</h3>`;
          html += '<div class="h2h-container">';
          h2h.forEach(m => {
              const scoreStr = (m.homeScore !== null && m.awayScore !== null)
                  ? m.homeScore + ' - ' + m.awayScore : '-';
              html += `
                  <div class="h2h-row">
                      <span class="h2h-date">${escapeHtml(m.date)}</span>
                      <span class="h2h-teams">${escapeHtml(m.homeTeam)} <span class="h2h-vs">vs</span> ${escapeHtml(m.awayTeam)}</span>
                      <span class="h2h-score">${scoreStr}</span>
                      <span class="h2h-league">${escapeHtml(m.league)}</span>
                  </div>`;
          });
          html += '</div>';
      }

      // ── BAJNOKSÁG TÖBBI MECCSE ──
      const sameComp = data.sameCompetition || [];
      if (sameComp.length > 0) {
          html += `<h3 class="section-title-sm"><i class="fas fa-list"></i> ${t('mainMenu.sameCompMatches', 'Ugyanebben a bajnokságban')}</h3>`;
          html += '<div class="same-comp-container">';
          sameComp.forEach(m => {
              const finClass = m.finished ? 'comp-finished' : 'comp-upcoming';
              html += `
                  <div class="same-comp-row ${finClass}" data-match-id="${m.apiId}">
                      <span class="comp-time">${escapeHtml(m.time)}</span>
                      <span class="comp-name">${escapeHtml(m.name)}</span>
                      <span class="comp-score">${escapeHtml(m.score)}</span>
                      ${m.finished ? '<span class="comp-status"><i class="fas fa-check-circle"></i></span>' : '<span class="comp-status"><i class="fas fa-clock"></i></span>'}
                  </div>`;
          });
          html += '</div>';
      }

      matchesContainer.innerHTML = html;
      if (typeof window.applyI18n === 'function') window.applyI18n(matchesContainer);

      // Vissza gomb
      const backBtn = document.getElementById('back-to-matches');
      if (backBtn) {
          backBtn.addEventListener('click', function () {
              loadFinishedMatches(currentSportId);
          });
      }

      // Bajnokság meccseire kattintás
      matchesContainer.querySelectorAll('.same-comp-row.comp-finished').forEach(row => {
          row.style.cursor = 'pointer';
          row.addEventListener('click', function () {
              const mId = parseInt(this.getAttribute('data-match-id'));
              if (mId) loadMatchDetails(mId);
          });
      });
  }

  // ========== LEJÁTSZOTT MECCSEK ==========
  function setFinishedViewMode(enabled) {
      isFinishedView = !!enabled;
      const finishedBtn = document.getElementById('show-finished-matches');
      if (finishedBtn) {
          finishedBtn.classList.toggle('active', isFinishedView);
      }
      // Rendezés gomb elrejtése lejátszott nézetben
      if (sortToggleBtn) {
          sortToggleBtn.style.display = isFinishedView ? 'none' : '';
      }
      if (sportsData.length) {
          renderSportsList(sportsData);
      }
  }

  function loadFinishedMatches(sportId) {
      if (!matchesContainer) return;
      setFinishedViewMode(true);

      matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> ' + t('mainMenu.loadingMatches', 'Meccsek betöltése...') + '</div>';

      let url = '../../backend/ApiRequest/get_finished_matches.php';
      if (sportId && sportId > 0) {
          url += '?sport_id=' + sportId;
      }

      // Cím frissítése
      if (centerTitle) {
          const sport = sportsData.find(s => s.sport_api_id === sportId);
          const sportName = sport ? sport.sport_name : '';
          const prefix = sportName ? escapeHtml(sportName) + ' — ' : '';
          centerTitle.innerHTML = '<i class="fas fa-flag-checkered"></i> ' + prefix + t('mainMenu.finishedMatchesTitle', 'Lejátszott meccsek (utolsó 3 nap)');
      }

      fetch(url)
          .then(res => res.text())
          .then(html => {
              // Vissza gomb hozzáadása a lista tetejére
              const backHtml = '<button class="back-btn" id="back-to-today"><i class="fas fa-arrow-left"></i> ' + t('mainMenu.backToToday', 'Vissza a mai meccsekhez') + '</button>';
              matchesContainer.innerHTML = backHtml + html;

              filterNARows();
              attachMatchClickHandlers();
              applyPagination();
              if (typeof window.applyI18n === 'function') window.applyI18n(matchesContainer);
              scheduleColumnsSync();

              // Vissza gomb kezelése
              const backBtn = document.getElementById('back-to-today');
              if (backBtn) {
                  backBtn.addEventListener('click', function () {
                      setFinishedViewMode(false);
                      loadMatches(currentSportId);
                  });
              }
          })
          .catch(err => {
              console.error('[MAIN] Lejátszott meccsek betöltési hiba:', err);
              matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>' + t('mainMenu.errorLoading', 'Hiba a meccsek betöltésekor.') + '</div>';
              scheduleColumnsSync();
          });
  }

  // Lejátszott meccsek gomb kezelése
  const finishedMatchesBtn = document.getElementById('show-finished-matches');
  if (finishedMatchesBtn) {
      finishedMatchesBtn.addEventListener('click', function (e) {
          e.preventDefault();
          if (isFinishedView) {
              // Ha már a finished nézetben vagyunk, visszaváltunk
              setFinishedViewMode(false);
              loadMatches(currentSportId);
          } else {
              loadFinishedMatches(currentSportId);
          }
      });
  }

  // ========== ODDSŰRHAJÓ ==========
  const boostedMatchBtn = document.getElementById('show-boosted-match');
  if (boostedMatchBtn) {
      boostedMatchBtn.addEventListener('click', function (e) {
          e.preventDefault();
          setFinishedViewMode(false);
          if (centerTitle) {
              centerTitle.innerHTML = '<i class="fas fa-rocket"></i> ' + t('mainMenu.oddsShipTitle', 'Oddsűrhajó — Mai kiemelt szorzó');
          }
          matchesContainer.innerHTML = '<div class="loading-details"><i class="fas fa-spinner fa-spin"></i> ' + t('mainMenu.loadingBoostedMatch', 'Oddsűrhajó betöltése...') + '</div>';

          fetch('../../backend/ApiRequest/get_boosted_match.php')
              .then(res => res.json())
              .then(data => {
                  if (data.error) {
                      matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-rocket" style="font-size:40px;color:#f5c518;margin-bottom:12px;display:block;"></i>' + escapeHtml(data.error) + '</div>';
                      return;
                  }

                  if (parseInt(data.isLive, 10) === 1) {
                      matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-info-circle" style="font-size:40px;color:#f5c518;margin-bottom:12px;display:block;"></i>Jelenleg élőben megy ez a meccs, az oddsűrhajó jelenleg nem aktív!</div>';
                      return;
                  }

                  // Egyből a meccs részleteihez ugrunk
                  loadMatchDetails(data.eventId);
              })
              .catch(err => {
                  console.error('[BOOST] Hiba:', err);
                  matchesContainer.innerHTML = '<div class="no-matches"><i class="fas fa-exclamation-triangle" style="font-size:40px;color:#e74c3c;margin-bottom:12px;display:block;"></i>' + t('mainMenu.errorLoading', 'Hiba az Oddsűrhajó betöltésekor.') + '</div>';
              });
      });
  }

  // ========== ODDSŰRHAJÓ AUTO-OPEN (ha ?boosted=1 paraméterrel érkezünk) ==========
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('boosted') === '1' && boostedMatchBtn) {
      boostedMatchBtn.click();
      // Töröljük a paramétert az URL-ből, hogy frissítéskor ne nyíljon meg újra
      const cleanUrl = window.location.pathname;
      window.history.replaceState({}, document.title, cleanUrl);
  }

  // ========== MECCS MEGNYITÁSA URL-BŐL (ha ?eventId=123 paraméterrel érkezünk) ==========
  const eventIdParam = urlParams.get('eventId');
  if (eventIdParam) {
      const eid = parseInt(eventIdParam);
      if (eid) {
          loadMatchDetails(eid);
          const cleanUrl2 = window.location.pathname;
          window.history.replaceState({}, document.title, cleanUrl2);
      }
  }

  // Globálisan elérhető, pl. betslip előzményekből
  window.loadMatchDetails = loadMatchDetails;

  // ========== HÁTTÉR SZINKRON (API → DB) ==========
  function syncFromApi() {
      fetch('../../backend/refresh_all.php', { method: 'GET' })
          .then(r => r.json())
          .then(data => {
              if (data && data.success === false) {
                  console.warn('[SYNC] Frissítési hiba:', data);
              }
          })
          .catch(err => console.warn('[SYNC] Hálózati hiba:', err));
  }

  // Első betöltéskor késleltetve szinkronizálunk (ne blokkolja a UI betöltést)
  // + localStorage throttle: max 2 percenként egyszer
  function throttledSync() {
      var SYNC_INTERVAL_MS = 120000;
      var lastSync = parseInt(localStorage.getItem('bmb_last_sync') || '0', 10);
      var now = Date.now();
      if (now - lastSync < SYNC_INTERVAL_MS) return;
      localStorage.setItem('bmb_last_sync', String(now));
      syncFromApi();
  }
  setTimeout(throttledSync, 5000);
  setInterval(throttledSync, 120000);

  // ========== AUTO REFRESH (30 másodpercenként, DB → UI) ==========
  setInterval(() => {
      // Csak akkor frissítsünk, ha a meccsek listája látható (nem a részletek és nem finished nézet)
      if (matchesContainer && !matchesContainer.querySelector('.back-btn') && !isFinishedView) {
          loadMatches(currentSportId);
      }
      // Sidebar is frissüljön
      loadSidebarSports();
  }, 30000);

  // ========== NAPI TIPPEK ==========
  function syncTipButtonStates() {
      document.querySelectorAll('.tip-add-btn').forEach(btn => {
          const card = btn.closest('.daily-tip-card');
          if (!card) return;
          const pickEls = card.querySelectorAll('.tip-combo-pick');
          let allActive = true;
          pickEls.forEach(el => {
              const home = el.getAttribute('data-home');
              const away = el.getAttribute('data-away');
              const pick = el.getAttribute('data-pick');
              const market = el.getAttribute('data-market');
              if (!home || !away || !pick || !market) { allActive = false; return; }
              const state = window.BetslipLogic
                  ? window.BetslipLogic.getButtonState(home, away, pick, market)
                  : null;
              if (state !== 'active') allActive = false;
          });
          btn.classList.toggle('tip-added', allActive);
      });
  }
  window.syncTipButtons = syncTipButtonStates;

  function loadDailyTips() {
      const tipsList = document.getElementById('daily-tips-list');
      if (!tipsList) return;

      fetch('../../backend/ApiRequest/get_daily_tips.php')
          .then(res => res.json())
          .then(tips => {
              if (!Array.isArray(tips) || tips.length === 0) {
                  tipsList.innerHTML = '<div class="daily-tips-empty">' + t('mainMenu.noTips', 'Nincs elérhető tipp.') + '</div>';
                  scheduleColumnsSync();
                  return;
              }
              let html = '';
              tips.forEach((tip, idx) => {
                  const picks = tip.picks || [];
                  const comboOdds = parseFloat(tip.comboOdds || 0).toFixed(2);
                  let picksHtml = '';
                  picks.forEach(p => {
                      picksHtml += `
                          <div class="tip-combo-pick"
                               data-event-id="${tip.eventId}"
                               data-home="${escapeHtml(tip.homeTeam)}"
                               data-away="${escapeHtml(tip.awayTeam)}"
                               data-pick="${escapeHtml(p.pick)}"
                               data-market="${escapeHtml(p.market)}"
                               data-odd="${parseFloat(p.odds).toFixed(2)}">
                              <span class="tip-combo-market">${escapeHtml(p.market)}</span>
                              <span class="tip-combo-value">${escapeHtml(p.pick)} <span class="tip-combo-odd">${parseFloat(p.odds).toFixed(2)}</span></span>
                          </div>`;
                  });
                  html += `
                      <div class="daily-tip-card">
                          <div class="tip-match-info">
                              <span class="tip-league">${escapeHtml(tip.league)}</span>
                              <span class="tip-time">${escapeHtml(tip.startTime)}</span>
                          </div>
                          <div class="tip-teams">${escapeHtml(tip.homeTeam)} - ${escapeHtml(tip.awayTeam)}</div>
                          <div class="tip-combo-picks">${picksHtml}</div>
                          <button class="tip-add-btn" data-tip-index="${idx}">
                              <span class="tip-combo-odds">${comboOdds}</span>
                              <i class="fas fa-plus"></i>
                          </button>
                      </div>`;
              });
              tipsList.innerHTML = html;
              if (typeof window.applyI18n === 'function') window.applyI18n(tipsList);
              applyDynamicTranslations(tipsList);

              tipsList.querySelectorAll('.tip-add-btn').forEach(btn => {
                  btn.addEventListener('click', function () {
                      const card = this.closest('.daily-tip-card');
                      if (!card) return;
                      const pickEls = card.querySelectorAll('.tip-combo-pick');
                      pickEls.forEach(el => {
                          const home = el.getAttribute('data-home');
                          const away = el.getAttribute('data-away');
                          const pick = el.getAttribute('data-pick');
                          const odd = parseFloat(el.getAttribute('data-odd'));
                          const market = el.getAttribute('data-market');
                          const matchId = parseInt(el.getAttribute('data-event-id'));
                          if (typeof window.toggleOdds === 'function') {
                              window.toggleOdds(home, away, pick, odd, market, matchId, true);
                          }
                      });
                      syncTipButtonStates();
                  });
              });

              syncTipButtonStates();
              scheduleColumnsSync();
          })
          .catch(err => {
              console.error('[TIPS] Hiba:', err);
              tipsList.innerHTML = '<div class="daily-tips-empty">' + t('mainMenu.errorTips', 'Tippek betöltése sikertelen.') + '</div>';
              scheduleColumnsSync();
          });
  }

  // ========== INICIALIZÁLÁS ==========
  window.addEventListener('languageChanged', function () {
      if (sportsData.length) {
          renderSportsList(sportsData, matchSearch ? matchSearch.value : '');
      }

      const activeSportId = currentSportId || 66;
      if (isFinishedView) {
          loadFinishedMatches(activeSportId);
      } else {
          loadMatches(activeSportId);
      }

      loadDailyTips();

      if (centerTitle && currentSportId) {
          const sport = sportsData.find(s => s.sport_api_id === currentSportId);
          if (sport) {
              centerTitle.innerHTML = `<i class="fas ${sport.icon}"></i> ${escapeHtml(td(sport.sport_name))} ${t('mainMenu.matchesWord', 'meccsek')}`;
          }
      }

      applyDynamicTranslations(matchesContainer);
      scheduleColumnsSync();
  });

  if (rightColumnWrap) {
      const rightColumnObserver = new MutationObserver(function() {
          scheduleColumnsSync();
      });
      rightColumnObserver.observe(rightColumnWrap, {
          childList: true,
          subtree: true,
          attributes: true,
          characterData: true
      });
  }

  window.addEventListener('resize', scheduleColumnsSync);

  if (mobileBetslipFab) {
      mobileBetslipFab.addEventListener('click', function() {
          if (document.body.classList.contains('mobile-betslip-open')) {
              closeMobileBetslip();
          } else {
              openMobileBetslip();
          }
      });
  }

  if (mobileBetslipBackdrop) {
      mobileBetslipBackdrop.addEventListener('click', closeMobileBetslip);
  }

  if (mobileBetslipClose) {
      mobileBetslipClose.addEventListener('click', closeMobileBetslip);
  }

  if (mobileViewportQuery && typeof mobileViewportQuery.addEventListener === 'function') {
      mobileViewportQuery.addEventListener('change', function(e) {
          if (!e.matches) {
              closeMobileBetslip();
          }
      });
  }

  document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
          closeMobileBetslip();
      }
  });

  const betslipCountObserverTarget = document.getElementById('betslip-count');
  if (betslipCountObserverTarget && typeof MutationObserver !== 'undefined') {
      const betslipCountObserver = new MutationObserver(syncMobileBetslipCount);
      betslipCountObserver.observe(betslipCountObserverTarget, {
          childList: true,
          subtree: true,
          characterData: true
      });
  }
  syncMobileBetslipCount();

  loadSidebarSports();
  loadMatches(66); // Alapértelmezett: Foci
  loadDailyTips();
  scheduleColumnsSync();
});