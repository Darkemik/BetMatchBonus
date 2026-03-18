document.addEventListener('DOMContentLoaded', function() {

  // ===== ÓRA =====
  function updateDateTime() {
    const now = new Date();
    const formatted = now.toLocaleString('hu-HU', {
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', second: '2-digit'
    });
    const el = document.getElementById('currentDateTime');
    if (el) el.innerText = formatted;
  }
  updateDateTime();
  setInterval(updateDateTime, 1000);

  // ===== SIDEBAR BETÖLTÉS =====
  const sportsMenu = document.getElementById('sports-menu');
  
  function loadSidebar() {
    fetch('../../backend/ApiRequest/get_sports_sidebar.php')
      .then(function(res) { return res.json(); })
      .then(function(sports) {
        if (!Array.isArray(sports) || sports.length === 0) {
          sportsMenu.innerHTML = '<div class="sidebar-loading">Nincs elérhető sport.</div>';
          return;
        }
        renderSidebar(sports);
      })
      .catch(function(err) {
        console.error('Sidebar betöltés hiba:', err);
        sportsMenu.innerHTML = '<div class="sidebar-loading">Hiba a sportok betöltésekor.</div>';
      });
  }

  var sportIconMap = {
    66: 'fa-futbol', 67: 'fa-basketball-ball', 78: 'fa-bullseye',
    83: 'fa-swimmer', 73: 'fa-hand-rock', 70: 'fa-hockey-puck',
    145: 'fa-gamepad', 77: 'fa-table-tennis'
  };

  function renderSidebar(sports) {
    var html = '';
    sports.forEach(function(sport) {
      var icon = sportIconMap[sport.api_id] || 'fa-trophy';
      var matchCount = sport.match_count || 0;
      var comps = sport.competitions || [];

      html += '<details class="level1" data-sidebar-sport="' + sport.api_id + '">';
      html += '<summary>';
      html += '<span><i class="fas ' + icon + ' sport-icon-sidebar"></i> ' + escapeHtml(sport.name) + '</span>';
      if (matchCount > 0) {
        html += '<span class="sport-badge">' + matchCount + '</span>';
      }
      html += '</summary>';

      comps.forEach(function(comp) {
        if (!comp.events || comp.events.length === 0) return;

        var countryLabel = comp.country ? comp.country + ' – ' : '';
        html += '<details class="level2">';
        html += '<summary>' + countryLabel + escapeHtml(comp.name) + '</summary>';
        html += '<ul class="level3">';

        comp.events.forEach(function(ev) {
          var timeStr = ev.start_time ? ev.start_time.substring(11, 16) : '';
          var liveHtml = ev.is_live ? '<span class="live-indicator-small"></span> ' : '';
          html += '<li><a href="#" data-event-id="' + ev.api_id + '">';
          html += liveHtml;
          html += '<span>' + timeStr + '</span> ';
          html += escapeHtml(ev.name);
          html += '</a></li>';
        });

        html += '</ul></details>';
      });

      html += '</details>';
    });

    sportsMenu.innerHTML = html;
  }

  loadSidebar();

  // ===== SIDEBAR KERESŐ =====
  var sidebarSearch = document.getElementById('sidebarSearch');
  if (sidebarSearch) {
    sidebarSearch.addEventListener('input', function() {
      var query = this.value.toLowerCase().trim();
      var details1 = sportsMenu.querySelectorAll('details.level1');
      
      details1.forEach(function(d1) {
        var sportName = d1.querySelector('summary').textContent.toLowerCase();
        var details2 = d1.querySelectorAll('details.level2');
        var hasVisibleChild = false;

        details2.forEach(function(d2) {
          var compName = d2.querySelector('summary').textContent.toLowerCase();
          var items = d2.querySelectorAll('.level3 li');
          var hasVisibleItem = false;

          items.forEach(function(li) {
            var text = li.textContent.toLowerCase();
            if (query === '' || text.indexOf(query) !== -1 || compName.indexOf(query) !== -1 || sportName.indexOf(query) !== -1) {
              li.style.display = '';
              hasVisibleItem = true;
            } else {
              li.style.display = 'none';
            }
          });

          if (hasVisibleItem || query === '') {
            d2.style.display = '';
            hasVisibleChild = true;
            if (query !== '') d2.setAttribute('open', '');
          } else {
            d2.style.display = 'none';
          }
        });

        if (hasVisibleChild || sportName.indexOf(query) !== -1 || query === '') {
          d1.style.display = '';
          if (query !== '' && hasVisibleChild) d1.setAttribute('open', '');
        } else {
          d1.style.display = 'none';
        }
      });
    });
  }

  // ===== FŐ KERESŐ (meccs lista szűrés) =====
  var mainSearch = document.getElementById('mainSearch');
  if (mainSearch) {
    mainSearch.addEventListener('input', function() {
      var query = this.value.toLowerCase().trim();
      var rows = document.querySelectorAll('.matches-table tbody tr.match-row');

      rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        if (query === '' || text.indexOf(query) !== -1) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  }

  // ===== SPORT NAV SZŰRÉS =====
  var sportNavItems = document.querySelectorAll('#sports-nav .sport-item');
  sportNavItems.forEach(function(item) {
    item.addEventListener('click', function(e) {
      e.preventDefault();
      
      sportNavItems.forEach(function(s) { s.classList.remove('active'); });
      item.classList.add('active');

      var sportId = item.getAttribute('data-sport-id');
      var rows = document.querySelectorAll('.matches-table tbody tr.match-row');

      rows.forEach(function(row) {
        if (sportId === '0' || row.getAttribute('data-sport') === sportId) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });

  // ===== HTML ESCAPE =====
  function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

});