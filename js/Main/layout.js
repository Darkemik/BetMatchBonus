const apiKey = "7QmRda4MADvUC4jJI2IV9WEYJzct3xAWOFXpKsQYn7cEu4YyY1jtJQQJ99CBACPV0roXJ3w3AAAbACOG627C";
const endpoint = "https://api.cognitive.microsofttranslator.com/";
const region = "germanywestcentral";

let currentLang = localStorage.getItem('lang') || 'hu';

const SKIP_SELECTORS = [
    '.logo',
    '.lang-switcher',
];

function shouldSkip(element) {
    if (!element) return true;
    return SKIP_SELECTORS.some(sel => element.closest(sel));
}

function getAllTextNodes(root) {
    const textNodes = [];
    const walker = document.createTreeWalker(
        root,
        NodeFilter.SHOW_TEXT,
        {
            acceptNode(node) {
                if (!node.textContent.trim()) return NodeFilter.FILTER_REJECT;
                if (shouldSkip(node.parentElement)) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        }
    );
    let node;
    while ((node = walker.nextNode())) {
        textNodes.push(node);
    }
    return textNodes;
}

// Csak egy adott konténer szövegeit fordítja le (AJAX frissítéshez)
async function changeLanguageForContainer(container, lang) {
    const textNodes = getAllTextNodes(container);
    const texts = textNodes.map(node => ({ Text: node.textContent.trim() }));
    if (texts.length === 0) return;

    const url = `${endpoint}/translate?api-version=3.0&to=${lang}`;
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Ocp-Apim-Subscription-Key': apiKey,
                'Ocp-Apim-Subscription-Region': region,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(texts)
        });

        if (!response.ok) return;

        const data = await response.json();
        data.forEach((result, i) => {
            textNodes[i].textContent = result.translations[0].text;
        });
    } catch (error) {
        console.error("Fordítási hiba (konténer):", error);
    }
}

// Globálisan elérhetővé tesszük
window.changeLanguageForContainer = changeLanguageForContainer;

async function changeLanguage(lang) {
    const textNodes = getAllTextNodes(document.body);
    const texts = textNodes.map(node => ({ Text: node.textContent.trim() }));
    if (texts.length === 0) return;

    const url = `${endpoint}/translate?api-version=3.0&to=${lang}`;
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Ocp-Apim-Subscription-Key': apiKey,
                'Ocp-Apim-Subscription-Region': region,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(texts)
        });

        if (!response.ok) {
            const err = await response.text();
            console.error("API hiba:", err);
            return;
        }

        const data = await response.json();
        console.log("Fordítás sikeres, elemek:", data.length);

        data.forEach((result, i) => {
            textNodes[i].textContent = result.translations[0].text;
        });

        currentLang = lang;
        localStorage.setItem('lang', lang);

    } catch (error) {
        console.error("Fordítási hiba:", error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const mainBtn = document.getElementById('btn-hu');
    const dropdown = document.getElementById('lang-dropdown');
    const enBtn = document.getElementById('btn-en');
    const huBtn = document.getElementById('btn-hu-switch');

    const svgHU = `<svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
        <rect width="9" height="2" y="0" fill="#c8102e" />
        <rect width="9" height="2" y="2" fill="#ffffff" />
        <rect width="9" height="2" y="4" fill="#436f4d" />
    </svg>`;

    const svgEN = `<svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg">
        <rect width="9" height="6" fill="#ffffff" />
        <rect x="4" width="1" height="6" fill="#c8102e" />
        <rect y="2.5" width="9" height="1" fill="#c8102e" />
    </svg>`;

    // Oldal betöltésekor automatikusan alkalmazza az elmentett nyelvet
    if (currentLang === 'en') {
        if (mainBtn) mainBtn.innerHTML = svgEN;
        changeLanguage('en');
    }

    if (mainBtn && dropdown) {
        mainBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });
    }

    document.addEventListener('click', (e) => {
        if (!dropdown) return;
        if (!dropdown.contains(e.target) && e.target !== mainBtn) {
            dropdown.classList.remove('open');
        }
    });

    if (enBtn) {
        enBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.remove('open');
            if (currentLang !== 'en') {
                changeLanguage('en');
                mainBtn.innerHTML = svgEN;
            }
        });
    }

    if (huBtn) {
        huBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.remove('open');
            if (currentLang !== 'hu') {
                localStorage.setItem('lang', 'hu');
                location.reload();
            }
        });
    }
});