const languages = {
    hu: {
        title: "Élő meccsek",
        footer: "Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2025. Minden jog fenntartva.",
        refresh: "🔄 Frissítés",
        lastUpdated: "Utolsó frissítés",
        nav: {
            home: "Főoldal",
            live: "Élő",
            bonuses: "Bónuszok",
            help: "Segítség",
            login: "Bejelentkezés",
            register: "Regisztráció"
        },
        tabs: {
            all: "Összes meccs",
            favorites: "Kedvencek"
        },
        tableHeaders: {
            league: "Bajnokság",
            match: "Mérkőzés",
            result: "Eredmény",
            status: "Állapot",
            time: "Idő",
            favorite: "Kedvenc"
        }
    },
    en: {
        title: "Live Games",
        footer: "We provide experience, create value © BetMatchBonus – 2025. All rights reserved.",
        refresh: "🔄 Refresh",
        lastUpdated: "Last updated",
        nav: {
            home: "Home",
            live: "Live",
            bonuses: "Bonuses",
            help: "Help",
            login: "Login",
            register: "Register"
        },
        tabs: {
            all: "All Matches",
            favorites: "Favorites"
        },
        tableHeaders: {
            league: "League",
            match: "Match",
            result: "Result",
            status: "Status",
            time: "Time",
            favorite: "Favorite"
        }
    }
};

let currentLang = 'hu';

function changeLanguage(lang) {
    currentLang = lang;

    const titleElement = document.getElementById('elo-title');
    if (titleElement) {
        titleElement.textContent = languages[lang].title;
    }

    const footerElement = document.getElementById('footer-text');
    if (footerElement) {
        footerElement.textContent = languages[lang].footer;
    }

    // Tab gombok fordítása
    const tabAll = document.getElementById('tab-all');
    const tabFavorites = document.getElementById('tab-favorites');
    if (tabAll && languages[lang].tabs) {
        tabAll.textContent = languages[lang].tabs.all;
        tabFavorites.textContent = languages[lang].tabs.favorites;
    }

    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (key && languages[lang]) {
            if (key.startsWith('nav.') && languages[lang].nav) {
                const navKey = key.replace('nav.', '');
                if (languages[lang].nav[navKey]) {
                    element.textContent = languages[lang].nav[navKey];
                }
            } else if (languages[lang][key]) {
                element.textContent = languages[lang][key];
            }
        }
    });

    const huBtn = document.getElementById('lang-hu');
    const enBtn = document.getElementById('lang-en');

    if (huBtn && enBtn) {
        huBtn.classList.remove('active');
        enBtn.classList.remove('active');

        if (lang === 'hu') {
            huBtn.classList.add('active');
            huBtn.title = "Magyar";
            enBtn.title = "Angol";
        } else {
            enBtn.classList.add('active');
            huBtn.title = "Hungarian";
            enBtn.title = "English";
        }
    }

    document.documentElement.lang = lang;
    localStorage.setItem('preferred-language', lang);

    // Táblázat fejlécek frissítése
    if (typeof displayFavoriteMatches === 'function') {
        displayFavoriteMatches();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const savedLang = localStorage.getItem('preferred-language');
    if (savedLang && (savedLang === 'hu' || savedLang === 'en')) {
        changeLanguage(savedLang);
    } else {
        changeLanguage('hu');
    }

    const huBtn = document.getElementById('lang-hu');
    const enBtn = document.getElementById('lang-en');

    if (huBtn) huBtn.addEventListener('click', () => changeLanguage('hu'));
    if (enBtn) enBtn.addEventListener('click', () => changeLanguage('en'));
});