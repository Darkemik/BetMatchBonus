function getSzotarLang() {
    const stored = String(localStorage.getItem('lang') || '').toLowerCase();
    if (stored === 'en') return 'en';
    if (stored === 'hu') return 'hu';
    return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en' : 'hu';
}

function getSzotarPath() {
    return getSzotarLang() === 'en' ? '../../json/szotar.en.json' : '../../json/szotar.json';
}

function normalizeDictionaryText(value) {
    if (value == null) return '';

    return String(value)
        .split('\u00e2\u20ac\u201c').join('–')
        .split('\u00e2\u20ac\u201d').join('—')
        .split('\u00e2\u20ac\u02dc').join("'")
        .split('\u00e2\u20ac\u2122').join("'")
        .split('\u00e2\u20ac\u0153').join('"')
        .split('\u00e2\u20ac\u009d').join('"')
        .split('\u00c3\u00a9').join('é')
        .split('\u00c3\u00a1').join('á')
        .split('\u00c3\u00b6').join('ö')
        .split('\u00c3\u00bc').join('ü')
        .replace(/\u00a0/g, ' ')
        .trim();
}

function renderSzotar(data) {
    const container = document.getElementById('szotarContainer');
    if (!container) return;
    container.innerHTML = '';

    const navBar = document.createElement('div');
    navBar.classList.add('szotar-nav');

    data.forEach((section) => {
        const navBtn = document.createElement('a');
        navBtn.classList.add('szotar-nav-btn');
        navBtn.href = '#szotar-' + section.letter;
        navBtn.textContent = normalizeDictionaryText(section.letter);
        navBar.appendChild(navBtn);
    });

    container.appendChild(navBar);

    data.forEach((section) => {
        const sectionEl = document.createElement('div');
        sectionEl.classList.add('szotar-section');
        sectionEl.id = 'szotar-' + section.letter;

        const letterHeading = document.createElement('h2');
        letterHeading.classList.add('szotar-letter');
        letterHeading.textContent = normalizeDictionaryText(section.letter);
        sectionEl.appendChild(letterHeading);

        const dl = document.createElement('dl');
        dl.classList.add('szotar-list');

        section.terms.forEach((term) => {
            const dt = document.createElement('dt');
            dt.classList.add('szotar-term');
            dt.textContent = normalizeDictionaryText(term.word);

            const dd = document.createElement('dd');
            dd.classList.add('szotar-def');
            dd.textContent = normalizeDictionaryText(term.definition);

            dl.appendChild(dt);
            dl.appendChild(dd);
        });

        sectionEl.appendChild(dl);
        container.appendChild(sectionEl);
    });
}

function loadSzotar() {
    const path = getSzotarPath() + '?v=' + Date.now();
    fetch(path, { cache: 'no-store' })
        .then((res) => res.json())
        .then(renderSzotar)
        .catch((err) => console.error('Szótár JSON betöltési hiba:', err));
}

loadSzotar();
window.addEventListener('languageChanged', loadSzotar);
