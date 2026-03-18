document.addEventListener('DOMContentLoaded', async () => {
    // Ellenőrizzük, hogy be van-e jelentkezve
    try {
        const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
        const data = await res.json();

        const container = document.getElementById("bonusContainer");
        const isLoggedIn = data.loggedIn;

        // Betöltjük a bónuszokat
        fetch("../../json/bonuses.json")
            .then(res => res.json())
            .then(bonuses => {
                bonuses.forEach((bonus) => {
                    const box = document.createElement("div");
                    box.classList.add("doboz");

                    // Ha bejelentkezve van, más gombokat mutatunk
                    const buttonHTML = isLoggedIn 
                        ? `
                            <button class="doboz-gomb" title="Igénylés gomb">
                                📋 IGÉNYLÉS
                            </button>
                            <button class="tobb-info-gomb">
                                ℹ️ Több információ
                            </button>
                        `
                        : `
                            <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                                🔐 BEJELENTKEZÉS / REGISZTRÁCIÓ
                            </button>
                            <button class="tobb-info-gomb">
                                ℹ️ Több információ
                            </button>
                        `;

                    box.innerHTML = `
                        <div class="doboz-inner">
                            <div class="doboz-front">
                                <div class="doboz-kep-wrap">
                                    <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}">
                                    ${bonus.amount ? `<span class="bonus-amount-badge">${bonus.amount}</span>` : ""}
                                </div>
                                <div class="doboz-tartalom">
                                    <p class="doboz-cim">${bonus.title}</p>
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
                        // Bejelentkezve: Igénylés gomb és Több információ
                        const igenylesBtn = box.querySelector(".doboz-gombok .doboz-gomb");
                        igenylesBtn.addEventListener("click", () => {
                            // Igénylés funkcionálisa később
                        });
                        
                        // Több információ gomb flip-el
                        box.querySelector(".tobb-info-gomb").addEventListener("click", () => {
                            box.classList.add("flipped");
                        });
                    } else {
                        // Nincs bejelentkezve: információ gomb flip-el
                        box.querySelector(".tobb-info-gomb").addEventListener("click", () => {
                            box.classList.add("flipped");
                        });
                    }

                    box.querySelector(".doboz-back-close").addEventListener("click", () => {
                        box.classList.remove("flipped");
                    });

                    container.appendChild(box);
                });
            })
            .catch(err => console.error("Hiba a JSON betöltésekor:", err));
    } catch (err) {
        console.error('Auth check error:', err);
    }
});