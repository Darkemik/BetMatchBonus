async function refreshAuthUI() {
  const loginBtn = document.querySelector('.loginbtn');
  const regBtn = document.querySelector('.registrationbtn');
  const userMenu = document.getElementById('userMenu');
  const sessionBetEl = document.getElementById('sessionBetDisplay');
  const sessionLoginDurationEl = document.getElementById('sessionLoginDurationDisplay');

  if (window.__sessionLoginDurationTimer) {
    clearInterval(window.__sessionLoginDurationTimer);
    window.__sessionLoginDurationTimer = null;
  }

  const formatDuration = (totalSeconds) => {
    const sec = Math.max(0, Math.floor(totalSeconds || 0));
    const hh = String(Math.floor(sec / 3600)).padStart(2, '0');
    const mm = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
    const ss = String(sec % 60).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
  };

  if (!userMenu) return;

  try {
    // ABSZOLÚT útvonal, így minden frontend oldalról működik
    const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
    const data = await res.json();

    if (!data.loggedIn) {
      if (loginBtn) loginBtn.style.display = '';
      if (regBtn) regBtn.style.display = '';
      userMenu.style.display = 'none';
      if (sessionBetEl) sessionBetEl.textContent = '0 FT';
      if (sessionLoginDurationEl) sessionLoginDurationEl.textContent = '00:00:00';
      return;
    }

    if (loginBtn) loginBtn.style.display = 'none';
    if (regBtn) regBtn.style.display = 'none';
    userMenu.style.display = '';

    const u = data.user || {};
    const usernameEl = document.getElementById('userMenuUsername');
    const fullNameEl = document.getElementById('userFullName');
    const emailEl = document.getElementById('userEmail');

    // Balance megjelenítése formázva
    const balance = parseFloat(u.balance) || 0;
    const balanceFormatted = balance.toLocaleString('hu-HU', { style: 'currency', currency: 'HUF' }).replace('Ft', 'FT').trim();
    const sessionBetTotal = parseFloat(u.session_bet_total) || 0;
    const sessionBetFormatted = sessionBetTotal.toLocaleString('hu-HU', {
      maximumFractionDigits: 0,
      minimumFractionDigits: 0
    }) + ' FT';
    const loginStartedAt = parseInt(u.login_started_at, 10) || Math.floor(Date.now() / 1000);
    
    if (usernameEl) usernameEl.textContent = balanceFormatted;
    if (sessionBetEl) sessionBetEl.textContent = sessionBetFormatted;
    if (sessionLoginDurationEl) {
      const updateDuration = () => {
        const nowSec = Math.floor(Date.now() / 1000);
        sessionLoginDurationEl.textContent = formatDuration(nowSec - loginStartedAt);
      };
      updateDuration();
      window.__sessionLoginDurationTimer = setInterval(updateDuration, 1000);
    }
    if (fullNameEl) fullNameEl.textContent = u.full_name || u.username || '-';
    if (emailEl) emailEl.textContent = u.email || '-';
  } catch (e) {
    console.error('refreshAuthUI error:', e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  refreshAuthUI();

  // Csak egyenleg-frissítéshez (pl. szelvény leadás után), oldal újratöltés nélkül
  document.addEventListener('balance:changed', () => {
    refreshAuthUI();
  });

  // login/register után ezt hívjuk
  document.addEventListener('auth:changed', () => {
    refreshAuthUI();
    // Frissítsd az oldalt, hogy a bónusz kártyák is frissüljenek
    setTimeout(() => {
      location.reload();
    }, 500);
  });

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try {
        const res = await fetch('/BetMatchBonus/backend/Auth/logout.php', { method: 'POST' });
        if (res.ok) {
          // Kijelentkezés után menjünk a főoldalra
          window.location.href = '/BetMatchBonus/frontend/MainMenu/MainMenu.php';
        }
      } catch (e) {
        console.error('Logout error:', e);
        window.location.href = '/BetMatchBonus/frontend/MainMenu/MainMenu.php';
      }
    });
  }
});