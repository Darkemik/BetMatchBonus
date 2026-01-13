// Nyelv beállítások
const languages = {
    hu: {
        title: "Élő meccsek",
        footer: "Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2025. Minden jog fenntartva.",
        live: "Élő",
        halftime: "Félidő",
        nav: {
            home: "Főoldal",
            live: "Élő",
            bonuses: "Bónuszok",
            help: "Segítség",
            login: "Bejelentkezés",
            register: "Regisztráció"
        }
    },
    en: {
        title: "Live Games",
        footer: "We provide experience, create value © BetMatchBonus – 2025. All rights reserved.",
        live: "Live",
        halftime: "Half Time",
        nav: {
            home: "Home",
            live: "Live",
            bonuses: "Bonuses",
            help: "Help",
            login: "Login",
            register: "Register"
        }
    }
};

// Nyelv váltás függvény
function changeLanguage(lang) {
    // Cím frissítése
    const titleElement = document.getElementById('elo-title');
    if (titleElement && languages[lang]) {
        titleElement.textContent = languages[lang].title;
    }
    
    // Footer frissítése
    const footerElement = document.getElementById('footer-text');
    if (footerElement && languages[lang]) {
        footerElement.textContent = languages[lang].footer;
    }
    
    // "Élő" szövegek frissítése a táblázatban
    const liveTexts = ['live-text-1', 'live-text-2', 'live-text-3', 'live-text-4'];
    liveTexts.forEach((id, index) => {
        const element = document.getElementById(id);
        if (element) {
            // Az első két sorban "Élő", a másodikban "Félidő"
            if (index === 1) {
                element.textContent = languages[lang].halftime;
            } else {
                element.textContent = languages[lang].live;
            }
        }
    });
    
    // Navigáció elemek frissítése
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (key && languages[lang] && languages[lang].nav) {
            const navKey = key.replace('nav.', '');
            if (languages[lang].nav[navKey]) {
                element.textContent = languages[lang].nav[navKey];
            }
        }
    });
    
    // Gombok állapota
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
    
    // HTML nyelv attribútum
    document.documentElement.lang = lang;
    
    // Mentés localStorage-ba
    localStorage.setItem('preferred-language', lang);
}

// Oldal betöltésekor
document.addEventListener('DOMContentLoaded', function() {
    console.log('Oldal betöltve, JS fájl működik!');
    
    // Gombok eseménykezelői
    const huBtn = document.getElementById('lang-hu');
    const enBtn = document.getElementById('lang-en');
    
    if (huBtn) {
        huBtn.addEventListener('click', function() {
            changeLanguage('hu');
        });
    }
    
    if (enBtn) {
        enBtn.addEventListener('click', function() {
            changeLanguage('en');
        });
    }
    
    // Mentett nyelv betöltése
    const savedLang = localStorage.getItem('preferred-language');
    if (savedLang && (savedLang === 'hu' || savedLang === 'en')) {
        console.log('Mentett nyelv betöltve:', savedLang);
        changeLanguage(savedLang);
    } else {
        // Alapértelmezett magyar
        console.log('Alapértelmezett magyar nyelv');
        changeLanguage('hu');
    }
    
    // Hibakezelés
    window.addEventListener('error', function(e) {
        console.error('Hiba történt:', e.message);
    });
});

// Hibakezelés: ha a fájl nem töltődik be
window.addEventListener('load', function() {
    const titleElement = document.getElementById('elo-title');
    if (titleElement && !titleElement.textContent) {
        console.log('JS fájl nem működik, alapértelmezett cím beállítása');
        titleElement.textContent = "Élő meccsek";
    }
});