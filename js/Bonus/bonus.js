document.addEventListener('DOMContentLoaded', async () => {
    // Ellenorizzuk, hogy be van-e jelentkezve
    try {
        const res = await fetch('/BetMatchBonus/backend/Auth/me.php', { cache: 'no-store' });
        const data = await res.json();

        const container = document.getElementById("bonusContainer");
        const isLoggedIn = data.loggedIn;

        // Betoltjuk a bonuszokat a DB-bol a PHP vegponton keresztul!
        fetch("../../backend/ApiRequest/get_active_bonuses.php")
            .then(res => res.json())
            .then(bonuses => {
                
                if (bonuses.length === 0) {
                    container.innerHTML = '<p style="color: white; text-align: center; width: 100%;">Jelenleg nincs elerheto bonusz.</p>';
                    return;
                }

                bonuses.forEach((bonus) => {
                    const box = document.createElement("div");
                    box.classList.add("doboz");

                    let claimButtonText = bonus.code ? `📋 IGENYLES KODDAL (${bonus.code})` : `🎫 IGENYLES (KOD NELKUL)`;

                    const buttonHTML = isLoggedIn 
                        ? `
                            <button class="doboz-gomb claim-btn" data-code="${bonus.code || ''}">
                                ${claimButtonText}
                            </button>
                            <button class="tobb-info-gomb">
                                ℹ️ Tobb informacio
                            </button>
                        `
                        : `
                            <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                                🔐 BEJELENTKEZES / REGISZTRACIO
                            </button>
                            <button class="tobb-info-gomb">
                                ℹ️ Tobb informacio
                            </button>
                        `;

                    box.innerHTML = `
                        <div class="doboz-inne
                        r">
                            <div class="doboz-front">
                                <div class="doboz-kep-wrap">
                                    <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}" style="object-fit: contain; background: #0f3460; padding: 20px;
                                    ">
                                    ${bonus.amount && bonus.amount !== 'Tobb lepcsős' ? `<span class="bonus-amount-badge">${bonus.amount}</span>` : ""}
                                </div>
                                <div class="doboz-tartalom">
                                    <p 
                                    class="doboz-cim">${bonus.title}</p>
                                    ${bonus.status ? `<div class="bonus-feltetel" style="color:#36e28f; font-weight:700;">● ${bonus.status}</div>` : ''}
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
                                    <p class="doboz-back-text">${bonus.longDescription || "Nincs tovabbi informacio."}</p>
                                </div>
                                <div class="doboz-back-footer">
                                    <button class="doboz-back-close">← Vissza</button>
                                </div>
                            </div>
                        </div>
                    `;

                    // Event listenerek hozzaadasa
                    if (isLoggedIn) 
                    {
                        const igenylesBtn = box.querySelector(".claim-btn");
                        const msgDiv = box.querySelector(".claim-message");

                        igenylesBtn.addEventListener("click", () => {
                            const bCode = igenylesBtn.getAttribute("data-code");
                            
                            if(!bCode) {
                                msgDiv.style.display = "block";
                                msgDiv.style.color = "#ffc107";
                                msgDiv.innerHTML = "Ehhez a bonuszhoz nincs kod, automatikusan jar vagy ugyfelszolgalaton igenyelheto!";
                    
                                return;
                            }

                            // Atiranyitas a Bonuszaim oldalra, ahol be tudja valtani a kodot
                            window.location.href = '/BetMatchBonus/frontend/UserProfile/my_bonuses.php';
                        });
                    }

                    // Mindket esetben mukodik a kartya forgatasa
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
                console.error("Hiba az adatbazis bonuszainak betoltesekor:", err);
                container.innerHTML = '<p style="color: #ff6b6b; text-align: center; width: 100%;">A bónuszok betöltése sikertelen. Frissítsd az oldalt, vagy próbáld újra később.</p>';
            });
    } catch (err) {
        console.error('Auth check error:', err);
    }
});
