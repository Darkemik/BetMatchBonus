fetch("../../json/bonuses.json")
    .then(res => res.json())
    .then(bonuses => {
        const container = document.getElementById("bonusContainer");

        bonuses.forEach((bonus, index) => {
            const offcanvasId = "offcanvasBonus" + index;

            const box = document.createElement("div");
            box.classList.add("doboz");

            box.innerHTML = `
                <img src="${bonus.image}" class="doboz-kep" alt="${bonus.title}">

                <div class="doboz-tartalom">
                    <p class="doboz-cim">${bonus.title}</p>

                    ${bonus.amount ? `<div class="bonus-osszeg">${bonus.amount}</div>` : ""}

                    <div class="bonus-feltetel">
                        <strong>${bonus.condition}</strong>
                    </div>

                    <p class="doboz-szoveg">${bonus.description}</p>

                    <div class="doboz-gombok">
                        <button class="doboz-gomb" data-bs-toggle="modal" data-bs-target="#loginModal">
                            BEJELENTKEZÉS / REGISZTRÁCIÓ
                        </button>

                        <button class="tobb-info-gomb"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#${offcanvasId}">
                            Több információ
                        </button>
                    </div>
                </div>

                <div class="offcanvas offcanvas-start" tabindex="-1" id="${offcanvasId}">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title">${bonus.title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                    </div>
                    <div class="offcanvas-body">
                        <p>${bonus.description || "Nincs további információ."}</p>
                    </div>
                </div>
            `;

            container.appendChild(box);
        });
    })
    .catch(err => console.error("Hiba a JSON betöltésekor:", err));
