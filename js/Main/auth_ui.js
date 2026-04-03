async function refreshAuthUI() {
  const loginBtn = document.querySelector('.loginbtn');
  const regBtn = document.querySelector('.registrationbtn');
  const userMenu = document.getElementById('userMenu');

  if (!userMenu) return;

  try {
    // ABSZOLÚT útvonal, így minden frontend oldalról működik
    const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
    const data = await res.json();

    if (!data.loggedIn) {
      if (loginBtn) loginBtn.style.display = '';
      if (regBtn) regBtn.style.display = '';
      userMenu.style.display = 'none';
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
    
    if (usernameEl) usernameEl.textContent = balanceFormatted;
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