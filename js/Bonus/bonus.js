fetch("../../json/bonuses.json")
    .then(res => res.json())
    .then(bonuses => {
        const container = document.getElementById("bonusContainer");

        bonuses.forEach((bonus) => {
            const box = document.createElement("div");
            box.classList.add("doboz");

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
                                <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                                    🔐 BEJELENTKEZÉS / REGISZTRÁCIÓ
                                </button>
                                <button class="tobb-info-gomb">
                                    ℹ️ Több információ
                                </button>
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

            box.querySelector(".tobb-info-gomb").addEventListener("click", () => {
                box.classList.add("flipped");
            });

            box.querySelector(".doboz-back-close").addEventListener("click", () => {
                box.classList.remove("flipped");
            });

            container.appendChild(box);
        });
    })
    .catch(err => console.error("Hiba a JSON betöltésekor:", err));