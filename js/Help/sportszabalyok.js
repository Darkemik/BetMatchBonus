function getSportRulesLang() {
    const stored = String(localStorage.getItem('lang') || '').toLowerCase();
    if (stored === 'en') return 'en';
    if (stored === 'hu') return 'hu';
    return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en' : 'hu';
}

function getSportRulesPath() {
    return getSportRulesLang() === 'en' ? '../../json/sportszabalyok.en.json' : '../../json/sportszabalyok.json';
}

function normalizeSportsRulesText(value) {
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

function renderSportRules(data) {
    const container = document.getElementById('sportszabalyokContainer');
    if (!container) return;
    container.innerHTML = '';

    data.forEach((category) => {
        const card = document.createElement('div');
        card.classList.add('sport-card');

        const header = document.createElement('button');
        header.classList.add('sport-card-header');
        header.textContent = normalizeSportsRulesText(category.category);
        header.setAttribute('aria-expanded', 'false');

        const body = document.createElement('div');
        body.classList.add('sport-card-body');
        body.hidden = true;

        const list = document.createElement('ol');
        list.classList.add('sport-rules-list');

        category.rules.forEach((rule) => {
            const item = document.createElement('li');
            item.textContent = normalizeSportsRulesText(rule);
            list.appendChild(item);
        });

        body.appendChild(list);

        header.addEventListener('click', () => {
            const isOpen = !body.hidden;
            body.hidden = isOpen;
            header.setAttribute('aria-expanded', String(!isOpen));
            header.classList.toggle('open', !isOpen);
        });

        card.appendChild(header);
        card.appendChild(body);
        container.appendChild(card);
    });
}

function loadSportsRules() {
    const path = getSportRulesPath() + '?v=' + Date.now();
    fetch(path, { cache: 'no-store' })
        .then((res) => res.json())
        .then(renderSportRules)
        .catch((err) => console.error('Sportszabalyok JSON betoltesi hiba:', err));
}

loadSportsRules();
window.addEventListener('languageChanged', loadSportsRules);