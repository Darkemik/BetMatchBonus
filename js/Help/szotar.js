fetch("../../json/szotar.json")
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById("szotarContainer");

        const navBar = document.createElement("div");
        navBar.classList.add("szotar-nav");

        data.forEach(section => {
            const navBtn = document.createElement("a");
            navBtn.classList.add("szotar-nav-btn");
            navBtn.href = "#szotar-" + section.letter;
            navBtn.textContent = section.letter;
            navBar.appendChild(navBtn);
        });

        container.appendChild(navBar);

        data.forEach(section => {
            const sectionEl = document.createElement("div");
            sectionEl.classList.add("szotar-section");
            sectionEl.id = "szotar-" + section.letter;

            const letterHeading = document.createElement("h2");
            letterHeading.classList.add("szotar-letter");
            letterHeading.textContent = section.letter;
            sectionEl.appendChild(letterHeading);

            const dl = document.createElement("dl");
            dl.classList.add("szotar-list");

            section.terms.forEach(term => {
                const dt = document.createElement("dt");
                dt.classList.add("szotar-term");
                dt.textContent = term.word;

                const dd = document.createElement("dd");
                dd.classList.add("szotar-def");
                dd.textContent = term.definition;

                dl.appendChild(dt);
                dl.appendChild(dd);
            });

            sectionEl.appendChild(dl);
            container.appendChild(sectionEl);
        });
    })
    .catch(err => console.error("Szótár JSON betöltési hiba:", err));
