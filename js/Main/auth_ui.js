// --- Inaktivitás-figyelés (30 perc) ---
window.__lastUserActivity = Date.now();
const INACTIVITY_LIMIT_MS = 30 * 60 * 1000; // 30 perc
['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
  document.addEventListener(evt, () => { window.__lastUserActivity = Date.now(); }, { passive: true });
});

async function refreshAuthUI() {
  const loginBtn = document.querySelector('.loginbtn');
  const regBtn = document.querySelector('.registrationbtn');
  const userMenu = document.getElementById('userMenu');
  const sessionBetEl = document.getElementById('sessionBetDisplay');
  const sessionLoginDurationEl = document.getElementById('sessionLoginDurationDisplay');
  const sessionBadgeEl = document.querySelector('.session-login-badge');

  if (window.__sessionLoginDurationTimer) {
    clearInterval(window.__sessionLoginDurationTimer);
    window.__sessionLoginDurationTimer = null;
  }

  const SESSION_MAX = 3600; // 1 óra

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
      if (sessionLoginDurationEl) sessionLoginDurationEl.textContent = '01:00:00';

      // Ha lejárt a session, üzenetet mutatunk
      if (data.expired) {
        alert('A munkameneted lejárt (1 óra). Kérjük, jelentkezz be újra!');
        window.location.href = '/BetMatchBonus/frontend/MainMenu/MainMenu.php';
      }
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
    const sessionBetTotal = parseFloat(u.balance) || 0;
    const sessionBetFormatted = sessionBetTotal.toLocaleString('hu-HU', {
      maximumFractionDigits: 0,
      minimumFractionDigits: 0
    }) + ' FT';

    // Visszaszámlálás: szerver megmondja mennyi van hátra
    let remaining = parseInt(u.session_remaining, 10);
    if (isNaN(remaining) || remaining < 0) remaining = 0;
    // Tároljuk a kliens oldalon a pontos indulási időt
    const countdownStartedAt = Date.now();
    const initialRemaining = remaining;
    
    if (usernameEl) usernameEl.textContent = u.username || '-';

    // Avatar: felhasználónév első betűje
    const avatarEl = document.getElementById('profileAvatar');
    const dropdownAvatarEl = document.getElementById('profileDropdownAvatar');
    const initial = (u.username || '?')[0].toUpperCase();
    if (avatarEl) avatarEl.textContent = initial;
    if (dropdownAvatarEl) dropdownAvatarEl.textContent = initial;

    if (sessionBetEl) sessionBetEl.textContent = sessionBetFormatted;

    // Bónusz egyenleg megjelenítése
    const bonusBalance = parseFloat(u.bonus_balance) || 0;
    const bonusBadge = document.getElementById('bonusBalanceBadge');
    const bonusDisplay = document.getElementById('bonusBalanceDisplay');
    if (bonusBadge && bonusDisplay) {
      if (bonusBalance > 0) {
        bonusBadge.style.display = '';
        bonusDisplay.textContent = bonusBalance.toLocaleString('hu-HU', {
          maximumFractionDigits: 0,
          minimumFractionDigits: 0
        }) + ' FT';
      } else {
        bonusBadge.style.display = 'none';
      }
    }

    if (sessionLoginDurationEl) {
      const updateCountdown = () => {
        const elapsedClient = Math.floor((Date.now() - countdownStartedAt) / 1000);
        const left = Math.max(0, initialRemaining - elapsedClient);
        sessionLoginDurationEl.textContent = formatDuration(left);

        // Szín váltás: sárga ha < 10 perc, piros ha < 2 perc
        if (sessionBadgeEl) {
          sessionBadgeEl.classList.remove('session-warning', 'session-danger');
          if (left <= 120) {
            sessionBadgeEl.classList.add('session-danger');
          } else if (left <= 600) {
            sessionBadgeEl.classList.add('session-warning');
          }
        }

        // Inaktivitás ellenőrzés (30 perc)
        const idleMs = Date.now() - (window.__lastUserActivity || Date.now());
        if (idleMs >= INACTIVITY_LIMIT_MS) {
          clearInterval(window.__sessionLoginDurationTimer);
          fetch('/BetMatchBonus/backend/Auth/logout.php', { method: 'POST' }).finally(() => {
            alert('30 percig inaktív voltál, ezért kijelentkeztettünk. Kérjük, jelentkezz be újra!');
            window.location.href = '/BetMatchBonus/frontend/MainMenu/MainMenu.php';
          });
          return;
        }

        // Ha lejárt az 1 órás limit: automatikus kijelentkezés
        if (left <= 0) {
          clearInterval(window.__sessionLoginDurationTimer);
          fetch('/BetMatchBonus/backend/Auth/logout.php', { method: 'POST' }).finally(() => {
            alert('A munkameneted lejárt (1 óra). Kérjük, jelentkezz be újra!');
            window.location.href = '/BetMatchBonus/frontend/MainMenu/MainMenu.php';
          });
        }
      };
      updateCountdown();
      window.__sessionLoginDurationTimer = setInterval(updateCountdown, 1000);
    }
    if (fullNameEl) fullNameEl.textContent = u.full_name || u.username || '-';
    if (emailEl) emailEl.textContent = u.email || '-';

    // Notification badge update
    updateNotifBadge();
  } catch (e) {
    console.error('refreshAuthUI error:', e);
  }
}

async function updateNotifBadge() {
  try {
    const res = await fetch('/BetMatchBonus/backend/ApiRequest/UserProfile/get_notifications.php?count=1', { cache: 'no-store' });
    const data = await res.json();
    if (!data.success) return;
    const count = data.unread_count || 0;

    // Bell icon in header
    const bellLink = document.getElementById('notifBellLink');
    const bellBadge = document.getElementById('notifBellBadge');
    if (bellLink) bellLink.style.display = '';
    if (bellBadge) {
      if (count > 0) {
        bellBadge.textContent = count > 99 ? '99+' : count;
        bellBadge.style.display = 'inline-flex';
      } else {
        bellBadge.style.display = 'none';
      }
    }

    // Dropdown menu badge
    const dropdownBadge = document.getElementById('notifDropdownBadge');
    if (dropdownBadge) {
      if (count > 0) {
        dropdownBadge.textContent = count;
        dropdownBadge.style.display = 'inline-flex';
      } else {
        dropdownBadge.style.display = 'none';
      }
    }
  } catch {}
}

document.addEventListener('DOMContentLoaded', () => {
  refreshAuthUI();

  // Értesítés badge frissítés percenként
  setInterval(updateNotifBadge, 60000);

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