document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById("bonusContainer");
    let isLoggedIn = false;

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
        const bonusRes = await fetch("../../backend/ApiRequest/get_active_bonuses.php");
        const bonuses = await bonusRes.json();

        if (bonuses.length === 0) {
            container.innerHTML = '<p style="color: white; text-align: center; width: 100%;">Jelenleg nincs elérhető bónusz.</p>';
            return;
        }

        bonuses.forEach((bonus) => {
            const box = document.createElement("div");
            box.classList.add("doboz");

            let buttonHTML = '';
            if (!isLoggedIn) {
                buttonHTML = `
                    <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                        🔐 BEJELENTKEZÉS / REGISZTRÁCIÓ
                    </button>
                    <button class="tobb-info-gomb">
                        ℹ️ Több információ
                    </button>
                `;
            } else {
                const copyBtn = bonus.code 
                    ? `<button class="doboz-gomb copy-code-btn" data-code="${bonus.code}" style="background: #1a1a2e; border: 1px solid #7c4dff;">
                        📋 Kód másolása
                      </button>` 
                    : '';
                buttonHTML = `
                    ${copyBtn}
                    <button class="doboz-gomb claim-btn">
                        ➡️ Igénylés
                    </button>
                    <button class="tobb-info-gomb">
                        ℹ️ Több információ
                    </button>
                `;
            }

            const sportIcons = { DARTS: '🎯', FOOTBALL: '⚽', TENNIS: '🎾', BASKETBALL: '🏀', ESPORT: '🎮' };
            const sportBadge = bonus.sportRestriction
                ? `<div class="bonus-sport-badge" style="display:inline-flex;align-items:center;gap:5px;background:linear-gradient(135deg,#7c4dff22,#b388ff33);border:1px solid #7c4dff66;color:#b388ff;font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:6px;">
                    ${sportIcons[bonus.sportRestriction] || '🏆'} ${bonus.sportRestriction} | <span style="color:#4caf50;">● ÉLŐ</span>
                  </div>`
                : '';

            box.innerHTML = `
                <div class="doboz-inner">
                    <div class="doboz-front">
                        <div class="doboz-kep-wrap">
                            <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}" style="object-fit: contain; background: #0f3460; padding: 20px;">
                            ${bonus.amount && bonus.amount !== 'Több lépcsős' ? `<span class="bonus-amount-badge">${bonus.amount}</span>` : ""}
                        </div>
                        <div class="doboz-tartalom">
                            ${sportBadge}
                            <p class="doboz-cim">${bonus.title}</p>
                            ${(!isLoggedIn && bonus.status) ? `<div class="bonus-meta-line bonus-meta-active">● ${bonus.status}</div>` : ''}
                            <div class="bonus-feltetel">${bonus.condition}</div>
                            <div class="doboz-gombok">
                                ${buttonHTML}
                            </div>
                        </div>
                    </div>
                    <div class="doboz-back">
                        <div class="doboz-back-header">
                            <p class="doboz-back-title">${bonus.title}</p>
                        </div>
                        <div class="doboz-back-body">
                            <p class="doboz-back-text">${bonus.longDescription || "Nincs további információ."}</p>
                        </div>
                        <div class="doboz-back-footer">
                            <button class="doboz-back-close">← Vissza</button>
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
});
