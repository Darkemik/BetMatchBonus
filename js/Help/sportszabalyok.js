/*fetch("../../json/sportszabalyok.json")
    .then(res => res.json())
    .then(data => {
        const container = document.getElementById("sportszabalyokContainer");

        data.forEach(category => {
            const cat = document.createElement("details");
            cat.classList.add("level0");

            cat.innerHTML = `
                <summary class="help-summary">${category.category}</summary>
            `;

            category.rules.forEach(rule => {
                const ruleEl = document.createElement("p");
                ruleEl.classList.add("rule-text");
                ruleEl.textContent = rule;

                cat.appendChild(ruleEl);
            });

            container.appendChild(cat);
        });
    })
    .catch(err => console.error("Sportszabályok JSON betöltési hiba:", err));
 */