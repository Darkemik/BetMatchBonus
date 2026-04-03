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

                bonuses.forEach((bonus) => {
                    const box = document.createElement("div");
                    box.classList.add("doboz");

                    let claimButtonText = bonus.code ? `📋 IGÉNYLÉS KÓDDAL (${bonus.code})` : `🎫 IGÉNYLÉS (KÓD NÉLKÜL)`;

                    const buttonHTML = isLoggedIn 
                        ? `
                            <button class="doboz-gomb claim-btn" data-code="${bonus.code || ''}">
                                ${claimButtonText}
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
                                    <div class="claim-message mt-2" 
                                    style="font-size: 0.8rem; font-weight: bold; display:none;"></div>
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
                    if (isLoggedIn) 
                    {
                        const igenylesBtn = box.querySelector(".claim-btn");
                        const msgDiv = box.querySelector(".claim-message");

                        igenylesBtn.addEventListener("click", () => {
                            const bCode = igenylesBtn.getAttribute("data-code");
                            
                            if(!bCode) {
                                msgDiv.style.display = "block";
                                msgDiv.style.color = "#ffc107";
                                msgDiv.innerHTML = "Ehhez a bónuszhoz nincs kód, automatikusan jár vagy ügyfélszolgálaton igényelhető!";
                    
                                return;
                            }

                            // Átirányítás a Bónuszaim oldalra, ahol be tudja váltani a kódot
                            window.location.href = '/BetMatchBonus/frontend/UserProfile/my_bonuses.php';
                        });
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
