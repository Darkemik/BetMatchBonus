fetch("../../json/sportszabalyok.json")
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById("sportszabalyokContainer");

        data.forEach(category => {
            const card = document.createElement("div");
            card.classList.add("sport-card");

            const header = document.createElement("button");
            header.classList.add("sport-card-header");
            header.textContent = category.category;
            header.setAttribute("aria-expanded", "false");

            const body = document.createElement("div");
            body.classList.add("sport-card-body");
            body.hidden = true;

            const list = document.createElement("ol");
            list.classList.add("sport-rules-list");

            category.rules.forEach(rule => {
                const item = document.createElement("li");
                item.textContent = rule;
                list.appendChild(item);
            });

            body.appendChild(list);

            header.addEventListener("click", () => {
                const isOpen = !body.hidden;
                body.hidden = isOpen;
                header.setAttribute("aria-expanded", String(!isOpen));
                header.classList.toggle("open", !isOpen);
            });

            card.appendChild(header);
            card.appendChild(body);
            container.appendChild(card);
        });
    })
    .catch(err => console.error("Sportszabályok JSON betöltési hiba:", err));