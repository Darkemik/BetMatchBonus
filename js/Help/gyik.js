function getGyikLang() {
    const stored = String(localStorage.getItem('lang') || '').toLowerCase();
    if (stored === 'en') return 'en';
    if (stored === 'hu') return 'hu';
    return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en' : 'hu';
}

function getGyikPath() {
    return getGyikLang() === 'en' ? '../../json/gyik.en.json' : '../../json/gyik.json';
}

function normalizeFaqText(value) {
    if (value == null) return '';

    return String(value)
        // Typical mojibake sequences from UTF-8 interpreted as Latin-1/CP1252.
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

function renderGyik(data) {
    const container = document.getElementById('gyikContainer');
    if (!container) return;
    container.innerHTML = '';

    data.forEach((category) => {
        const cat = document.createElement('details');
        cat.classList.add('level0');
        cat.innerHTML = '<summary class="help-summary">' + normalizeFaqText(category.category) + '</summary>';

        category.questions.forEach((q) => {
            const qEl = document.createElement('details');
            qEl.classList.add('level1');
            qEl.innerHTML =
                '<summary>' + normalizeFaqText(q.question) + '</summary>' +
                '<p>' + normalizeFaqText(q.answer) + '</p>';
            cat.appendChild(qEl);
        });

        container.appendChild(cat);
    });
}

function loadGyik() {
    const path = getGyikPath() + '?v=' + Date.now();
    fetch(path, { cache: 'no-store' })
        .then((res) => res.json())
        .then(renderGyik)
        .catch((err) => console.error('GYIK JSON betoltesi hiba:', err));
}

loadGyik();
window.addEventListener('languageChanged', loadGyik);
