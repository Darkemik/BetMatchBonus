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

    if (usernameEl) usernameEl.textContent = u.username || 'Fiókom';
    if (fullNameEl) fullNameEl.textContent = u.full_name || u.username || '-';
    if (emailEl) emailEl.textContent = u.email || '-';
  } catch (e) {
    console.error('refreshAuthUI error:', e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  refreshAuthUI();

  // login/register után ezt hívjuk
  document.addEventListener('auth:changed', () => {
    refreshAuthUI();
  });

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try {
        await fetch('/BetMatchBonus/backend/Auth/logout.php', { method: 'POST' });
      } catch (e) {
        console.error(e);
      }
      document.dispatchEvent(new CustomEvent('auth:changed'));
    });
  }
});