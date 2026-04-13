document.addEventListener('DOMContentLoaded', async () => {
    // Ellenőrizzük, hogy be van-e jelentkezve
    try {
        const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
        const data = await res.json();

        const container = document.getElementById("bonusContainer");
        const isLoggedIn = data.loggedIn;

        // Betöltjük a bónuszokat a DB-ből a PHP végponton keresztül!
        fetch("../../backend/ApiRequest/get_active_bonuses.php")
            .then(res => res.json())
            .then(bonuses => {
                
                if (bonuses.length === 0) {
                    container.innerHTML = '<p style="color: white; text-align: center; width: 100%;">Jelenleg nincs elérhető bónusz.</p>';
                    return;
                }

                const hasExistingBonus = bonuses.length > 0 && bonuses[0].hasExistingBonus;

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
                    } else if (hasExistingBonus) {
                        buttonHTML = `
                            <div class="bonus-already-active" style="color: #ffa726; font-size: 0.85rem; font-weight: bold; margin-bottom: 6px;">
                                ⚠️ Már van aktív bónuszod!
                            </div>
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

                    box.innerHTML = `
                        <div class="doboz-inner">
                            <div class="doboz-front">
                                <div class="doboz-kep-wrap">
                                    <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}" style="object-fit: contain; background: #0f3460; padding: 20px;
                                    ">
                                    ${bonus.amount && bonus.amount !== 'Több lépcsős' ? `<span class="bonus-amount-badge">${bonus.amount}</span>` : ""}
                                </div>
                                <div class="doboz-tartalom">
                                    <p 
                                    class="doboz-cim">${bonus.title}</p>
                                    ${bonus.status ? `<div class="bonus-meta-line bonus-meta-active">● ${bonus.status}</div>` : ''}
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
                    if (isLoggedIn && !hasExistingBonus) {
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
            })
            .catch(err => {
                console.error("Hiba az adatbázis bónuszainak betöltésekor:", err);
                container.innerHTML = '<p style="color: #ff6b6b; text-align: center; width: 100%;">A bónuszok betöltése sikertelen. Frissítsd az oldalt, vagy próbáld újra később.</p>';
            });
    } catch (err) {
        console.error('Auth check error:', err);
    }
});
