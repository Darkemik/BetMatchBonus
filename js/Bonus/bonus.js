document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById("bonusContainer");
    let isLoggedIn = false;
    const t = (key, fallback) => (typeof window.i18n === 'function' ? window.i18n(key, fallback) : (fallback || key));

    function getCurrentLang() {
        const stored = String(localStorage.getItem('lang') || '').toLowerCase();
        if (stored === 'en') return 'en';
        if (stored === 'hu') return 'hu';
        return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en' : 'hu';
    }

    function localizeBonusTitle(title, lang) {
        const src = String(title || '').trim();
        if (lang === 'en' && src === 'Vesztes fogadás cashback (30% Free Bet)') {
            return 'Losing Bet Cashback (30% Free Bet)';
        }
        if (lang === 'en') {
            const dartsPattern = /^DARTS\s+B[ÓO]NUSZ\s*\(([^)]+)\)$/i;
            const match = src.match(dartsPattern);
            if (match) {
                const details = match[1]
                    .replace(/fogadás/gi, 'bet')
                    .replace(/bónusz/gi, 'bonus')
                    .replace(/Ft/gi, 'FT');
                return `DARTS BONUS (${details})`;
            }
        }
        return src;
    }

    function localizeBonusDescription(description, lang) {
        const src = String(description || '').trim();
        const huText = 'Ha egy legalább 5.000 Ft-os fogadásod veszít (min. odds: 1.80), visszakapsz 30%-ot Free Bet formájában. Naponta egyszer aktiválódik automatikusan a vesztes szelvény lezárásakor. A kapott Free Bet-et bármilyen fogadásra felhasználhatod.';
        const enText = 'If a bet of at least 5,000 Ft loses (min. odds: 1.80), you get 30% back as a Free Bet. It is automatically activated once per day when the losing ticket is settled. You can use the received Free Bet on any bet.';

        if (lang === 'en' && (src === huText || src.includes('Ha egy legalább 5.000 Ft-os fogadásod veszít'))) {
            return enText;
        }
        return src;
    }

    // Ellenőrizzük, hogy be van-e jelentkezve
    try {
        const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
        const data = await res.json();
        isLoggedIn = data.loggedIn;
    } catch (err) {
        console.warn('Auth check failed, continuing as guest:', err);
    }

    // Betöltjük a bónuszokat a DB-ből a PHP végponton keresztül!
    try {
        const currentLang = getCurrentLang();
        const bonusUrl = "../../backend/ApiRequest/get_active_bonuses.php?lang=" + encodeURIComponent(currentLang) + "&v=" + Date.now();
        const bonusRes = await fetch(bonusUrl, { cache: 'no-store' });
        const bonuses = await bonusRes.json();

        if (bonuses.length === 0) {
            container.innerHTML = '<p style="color: white; text-align: center; width: 100%;">Jelenleg nincs elérhető bónusz.</p>';
            return;
        }

        bonuses.forEach((bonus) => {
            const box = document.createElement("div");
            box.classList.add("doboz");
            const localizedTitle = localizeBonusTitle(bonus.title, currentLang);
            const localizedLongDescription = localizeBonusDescription(bonus.longDescription, currentLang);

            let buttonHTML = '';
            if (!isLoggedIn) {
                buttonHTML = `
                    <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                        🔐 ${t('bonusPage.loginRegister', 'BEJELENTKEZÉS / REGISZTRÁCIÓ')}
                    </button>
                    <button class="tobb-info-gomb">
                        ℹ️ ${t('bonusPage.moreInfo', 'Több információ')}
                    </button>
                `;
            } else {
                const copyBtn = bonus.code 
                    ? `<button class="doboz-gomb copy-code-btn" data-code="${bonus.code}" style="background: #1a1a2e; border: 1px solid #7c4dff;">
                        📋 ${t('bonusPage.copyCode', 'Kód másolása')}
                      </button>` 
                    : '';
                buttonHTML = `
                    ${copyBtn}
                    <button class="doboz-gomb claim-btn">
                        ➡️ ${t('bonusPage.claim', 'Igénylés')}
                    </button>
                    <button class="tobb-info-gomb">
                        ℹ️ ${t('bonusPage.moreInfo', 'Több információ')}
                    </button>
                `;
            }

            const sportIcons = { DARTS: '🎯', FOOTBALL: '⚽', TENNIS: '🎾', BASKETBALL: '🏀', ESPORT: '🎮' };
            const sportBadge = bonus.sportRestriction
                ? `<div class="bonus-sport-badge" style="display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg,#7c4dff22,#b388ff33);border:1px solid #7c4dff66;color:#b388ff;font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:6px;">
                                        ${sportIcons[bonus.sportRestriction] || '🏆'} ${bonus.sportRestriction} | <span style="color:#4caf50;">● LIVE</span>
                  </div>`
                : '';

            box.innerHTML = `
                <div class="doboz-inner">
                    <div class="doboz-front">
                        <div class="doboz-kep-wrap">
                            <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}" style="object-fit: cover; border-radius: 12px 12px 0 0;">
                            ${bonus.amount && bonus.amount !== 'Több lépcsős' ? `<span class="bonus-amount-badge">${bonus.amount}</span>` : ""}
                        </div>
                        <div class="doboz-tartalom">
                            ${sportBadge}
                            <p class="doboz-cim">${localizedTitle}</p>
                            ${(!isLoggedIn && bonus.status) ? `<div class="bonus-meta-line bonus-meta-active">● ${bonus.status}</div>` : ''}
                            <div class="bonus-feltetel">${bonus.condition}</div>
                            <div class="doboz-gombok">
                                ${buttonHTML}
                            </div>
                        </div>
                    </div>
                    <div class="doboz-back">
                        <div class="doboz-back-header">
                            <p class="doboz-back-title">${localizedTitle}</p>
                        </div>
                        <div class="doboz-back-body">
                            <p class="doboz-back-text">${localizedLongDescription || "Nincs további információ."}</p>
                        </div>
                        <div class="doboz-back-footer">
                            <button class="doboz-back-close">← ${t('bonusPage.back', 'Vissza')}</button>
                        </div>
                    </div>
                </div>
            `;

            // Event listenerek hozzáadása
            if (isLoggedIn) {
                // "Kód másolása" gomb
                const copyBtn = box.querySelector(".copy-code-btn");
                if (copyBtn) {
                    copyBtn.addEventListener("click", () => {
                        const code = copyBtn.getAttribute("data-code");
                        navigator.clipboard.writeText(code).then(() => {
                            const origText = copyBtn.innerHTML;
                            copyBtn.innerHTML = "✅ Kimásolva!";
                            setTimeout(() => { copyBtn.innerHTML = origText; }, 2000);
                        });
                    });
                }

                // "Igénylés" gomb → átirányítás a Bónuszaim oldalra
                const claimBtn = box.querySelector(".claim-btn");
                if (claimBtn) {
                    claimBtn.addEventListener("click", () => {
                        window.location.href = "../../frontend/UserProfile/my_bonuses.php";
                    });
                }
            }

            // Mindkét esetben működik a kártya forgatása
            box.querySelector(".tobb-info-gomb").addEventListener("click", () => {
                box.classList.add("flipped");
            });

            box.querySelector(".doboz-back-close").addEventListener("click", () => {
                box.classList.remove("flipped");
            });

            container.appendChild(box);
        });
    } catch (err) {
        console.error("Hiba az adatbázis bónuszainak betöltésekor:", err);
        container.innerHTML = '<p style="color: #ff6b6b; text-align: center; width: 100%;">A bónuszok betöltése sikertelen. Frissítsd az oldalt, vagy próbáld újra később.</p>';
    }

    window.addEventListener('languageChanged', function () {
        window.location.reload();
    });
});
