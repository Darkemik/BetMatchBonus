/**
 * BetMatchBonus — Nyelvi rendszer (HU / EN)
 * 
 * Működés:
 *  1. A HTML elemekre data-i18n="section.key" attribútumot teszünk (textContent csere)
 *  2. data-i18n-placeholder="section.key" → placeholder csere
 *  3. data-i18n-title="section.key" → title csere
 *  4. data-i18n-html="section.key" → innerHTML csere (ikonos szövegekhez)
 *  5. A JSON-ből betöltjük az aktuális nyelv fájlját, és lecseréljük a DOM szövegeket
 *  6. window.i18n(key) → JS-ből is elérhető fordítás
 */

(function () {
    'use strict';

    let currentLang = localStorage.getItem('lang') || 'hu';
    let translations = {};

    // API-ból érkező dinamikus szövegek (sportok/piacok) gyors fordítási szótára.
    // HU oldalon az eredeti marad, EN oldalon kulcsszavas cserét végzünk.
    const DYNAMIC_TEXT_MAP = {
        en: {
            'Labdarúgás': 'Football',
            'Kosárlabda': 'Basketball',
            'Baseball': 'Baseball',
            'Amerikai foci': 'American Football',
            'Jégkorong': 'Ice Hockey',
            'Röplabda': 'Volleyball',
            'Kézilabda': 'Handball',
            'Pingpong': 'Table Tennis',
            'Téli sport': 'Winter Sports',
            'Nemzetközi': 'International',
            'Női': 'Women',
            'férfi': 'Men',
            'selejtező': 'Qualifiers',
            'Európa': 'Europe',
            'Afrika': 'Africa',
            'Ázsia': 'Asia',
            'Dél-Amerika': 'South America',
            'Észak-Amerika': 'North America',
            'Bajnokok Ligája': 'Champions League',
            'Nemzeti Liga': 'Nations League',
            'Világbajnokság': 'World Cup',
            'Lengyelország': 'Poland',
            'Írország': 'Ireland',
            'Magyarország': 'Hungary',
            'Németország': 'Germany',
            'Spanyolország': 'Spain',
            'Olaszország': 'Italy',
            'Franciaország': 'France',
            'Portugália': 'Portugal',
            'Horvátország': 'Croatia',
            'Svájc': 'Switzerland',
            'Hollandia': 'Netherlands',
            'Bosznia-Hercegovina': 'Bosnia and Herzegovina',
            'Szerbia': 'Serbia',
            'Ukrajna': 'Ukraine',
            'Ausztrália': 'Australia',
            'Döntetlen': 'Draw',
            'végeredmény': 'Result',
            'Pontos végeredmény': 'Correct Score',
            'Utolsó gól': 'Last Goal',
            'Mindkét csapat szerez gólt': 'Both Teams to Score',
            'Melyik csapat szerez gólt': 'Which team scores',
            'Melyik csapat nyeri meg a mérkőzés hátralévő részét': 'Which team wins the remainder of the match',
            'Hendikep 1X2': '1X2 Handicap',
            'Hendikep': 'Handicap',
            'gólok száma pontosan': 'Goals scored exactly',
            'gólok száma': 'Goals',
            'Mindkét csapat': 'Both teams',
            'Csak': 'Only',
            'Igen': 'Yes',
            'Nem': 'No',
            'Más': 'Other',
            'Páros': 'Even',
            'Páratlan': 'Odd',
            'páros': 'even',
            'páratlan': 'odd',
            'páratlan/páros': 'Odd/Even',
            'Félidő/végeredmény': 'Half Time/Full Time',
            '1. félidő/végeredmény': '1st Half/Full Time',
            'Vagy': 'or',
            'Egyik sem': 'Neither',
            'főidő': 'Full Time',
            'félidő': '1st Half',
            'gólok száma': 'Total Goals',
            'Kétesély': 'Double Chance',
            'Döntetlennél a tét visszajár': 'Draw No Bet',
            'felett': 'Over',
            'alatt': 'Under',
            'száma': 'Number'
        }
    };

    function escapeRegExp(str) {
        return String(str).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function dynamicTranslate(text) {
        if (text == null) return text;
        const input = String(text);
        if (currentLang !== 'en') return input;

        const map = DYNAMIC_TEXT_MAP.en || {};
        const keys = Object.keys(map).sort((a, b) => b.length - a.length);
        let out = input;
        keys.forEach(key => {
            out = out.replace(new RegExp(escapeRegExp(key), 'gi'), map[key]);
        });

        // Női jelölés és over/under formátumok egységesítése EN felületen.
        out = out.replace(/\(N\)/g, '(W)');
        out = out.replace(/(\d+(?:[.,]\d+)?)\s*felett/gi, function (_, n) {
            return 'Over ' + String(n).replace(',', '.');
        });
        out = out.replace(/(\d+(?:[.,]\d+)?)\s*alatt/gi, function (_, n) {
            return 'Under ' + String(n).replace(',', '.');
        });

        // "1. gól" / "2. gól" jellegű piacok és opciók fordítása.
        out = out.replace(/(\d+)\.\s*gól/gi, function (_, n) {
            var num = parseInt(n, 10);
            var suffix = 'th';
            if (num % 100 < 11 || num % 100 > 13) {
                if (num % 10 === 1) suffix = 'st';
                else if (num % 10 === 2) suffix = 'nd';
                else if (num % 10 === 3) suffix = 'rd';
            }
            return num + suffix + ' Goal';
        });

        // Vegyesen érkező jelölések normalizálása.
        out = out.replace(/1st\s*Half\s*\/\s*Result/gi, '1st Half/Full Time');
        out = out.replace(/total\s*goals\s*odd\s*\/\s*even/gi, 'Total Goals Odd/Even');

        // Gyakoribb összetett piac kifejezések.
        out = out.replace(/goals\s+scored\s+exactly/gi, 'Exact Goals');
        out = out.replace(/which\s+team\s+wins\s+the\s+remainder\s+of\s+the\s+match/gi, 'Team To Win Rest Of Match');
        return out;
    }

    // JSON betöltés — megkeresi a megfelelő relatív útvonalat
    function getJsonBasePath() {
        const scripts = document.querySelectorAll('script[src*="language.js"]');
        if (scripts.length > 0) {
            const src = scripts[0].getAttribute('src');
            // pl. ../../js/Main/language.js → ../../json/lang/
            return src.replace('js/Main/language.js', 'json/lang/');
        }
        return '../../json/lang/';
    }

    async function loadTranslations(lang) {
        try {
            const basePath = getJsonBasePath();
            const url = basePath + lang + '.json?v=' + Date.now();
            const res = await fetch(url);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            translations = await res.json();
            currentLang = lang;
            localStorage.setItem('lang', lang);
            applyTranslations();
            updateFlagButton();
            // Event kiváltása — más JS fájlok is reagálhatnak
            window.dispatchEvent(new CustomEvent('languageChanged', { detail: { lang: lang } }));
        } catch (err) {
            console.error('[LANG] Nyelvi fájl betöltési hiba:', err);
        }
    }

    // Kulcs feloldása: "nav.home" → translations.nav.home
    // {{PLACEHOLDER}} tokenek cseréje SITE_SETTINGS értékekre
    function resolve(key) {
        if (!key || !translations) return null;
        const parts = key.split('.');
        let obj = translations;
        for (const p of parts) {
            if (obj == null) return null;
            obj = obj[p];
        }
        if (typeof obj === 'string' && window.SITE_SETTINGS) {
            obj = obj.replace(/\{\{(\w+)\}\}/g, function(_, token) {
                var val = window.SITE_SETTINGS[token.toLowerCase()];
                return val != null ? Number(val).toLocaleString(currentLang === 'en' ? 'en-US' : 'hu-HU') : '{{' + token + '}}';
            });
        }
        return obj || null;
    }

    // Globálisan elérhető fordítás-lekérés JS-ből
    window.i18n = function (key, fallback) {
        return resolve(key) || fallback || key;
    };

    // Aktuális nyelv lekérése
    window.i18nLang = function () {
        return currentLang;
    };

    // API-ból jövő dinamikus feliratok fordításához.
    window.i18nDynamic = function (text) {
        return dynamicTranslate(text);
    };

    // DOM szövegek cseréje
    function applyTranslations(container) {
        const root = container || document;

        // textContent csere
        root.querySelectorAll('[data-i18n]').forEach(el => {
            const val = resolve(el.getAttribute('data-i18n'));
            if (val) el.textContent = val;
        });

        // innerHTML csere (ikonos szövegekhez)
        root.querySelectorAll('[data-i18n-html]').forEach(el => {
            const val = resolve(el.getAttribute('data-i18n-html'));
            if (val) el.innerHTML = val;
        });

        // placeholder csere
        root.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const val = resolve(el.getAttribute('data-i18n-placeholder'));
            if (val) el.placeholder = val;
        });

        // title csere
        root.querySelectorAll('[data-i18n-title]').forEach(el => {
            const val = resolve(el.getAttribute('data-i18n-title'));
            if (val) el.title = val;
        });

        // aria-label csere
        root.querySelectorAll('[data-i18n-aria]').forEach(el => {
            const val = resolve(el.getAttribute('data-i18n-aria'));
            if (val) el.setAttribute('aria-label', val);
        });
    }

    // Zászló gomb frissítése — melyik zászló legyen a fő gomb
    function updateFlagButton() {
        const mainBtn = document.getElementById('btn-hu');
        if (!mainBtn) return;

        const huFlag = '<svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg"><rect width="9" height="2" y="0" fill="#c8102e"/><rect width="9" height="2" y="2" fill="#ffffff"/><rect width="9" height="2" y="4" fill="#436f4d"/></svg>';
        const enFlag = '<svg viewBox="0 0 9 6" xmlns="http://www.w3.org/2000/svg"><rect width="9" height="6" fill="#ffffff"/><rect x="4" width="1" height="6" fill="#c8102e"/><rect y="2.5" width="9" height="1" fill="#c8102e"/></svg>';

        mainBtn.innerHTML = currentLang === 'en' ? enFlag : huFlag;
    }

    // Konténer utólagos fordítása (AJAX tartalomhoz)
    window.applyI18n = function (container) {
        applyTranslations(container || document);
    };

    // Nyelv váltás globálisan elérhető
    window.setLanguage = function (lang) {
        if (lang === currentLang && Object.keys(translations).length > 0) return;
        loadTranslations(lang);
    };

    // Inicializálás — DOMContentLoaded után
    document.addEventListener('DOMContentLoaded', function () {
        loadTranslations(currentLang);

        // Nyelváltó gombok kezelése
        const btnHuSwitch = document.getElementById('btn-hu-switch');
        const btnEn = document.getElementById('btn-en');
        const dropdown = document.getElementById('lang-dropdown');

        if (btnHuSwitch) {
            btnHuSwitch.addEventListener('click', function () {
                window.setLanguage('hu');
                if (dropdown) dropdown.classList.remove('open');
            });
        }

        if (btnEn) {
            btnEn.addEventListener('click', function () {
                window.setLanguage('en');
                if (dropdown) dropdown.classList.remove('open');
            });
        }
    });

})();
