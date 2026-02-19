fetch("../../json/gyik.json")
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById("gyikContainer");

        data.forEach(category => {
            const cat = document.createElement("details");
            cat.classList.add("level0");

            cat.innerHTML = `
                <summary class="help-summary">${category.category}</summary>
            `;

            category.questions.forEach(q => {
                const qEl = document.createElement("details");
                qEl.classList.add("level1");

                qEl.innerHTML = `
                    <summary>${q.question}</summary>
                    <p>${q.answer}</p>
                `;

                cat.appendChild(qEl);
            });

            container.appendChild(cat);
        });
    })
    .catch(err => console.error("GYIK JSON betöltési hiba:", err));
