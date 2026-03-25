/**
 * BetMatchBonus - Cookie Consent (Süti hozzájárulás)
 * 
 * A felhasználó választása egy "cookie_consent" nevű sütiben tárolódik.
 * Értékek: "all" | "necessary" | "declined"
 * Lejárat: 365 nap
 * 
 * Ha nincs ilyen süti → a banner megjelenik overlay-jel együtt.
 * Ha már van → a banner nem jelenik meg.
 */

(function () {
    'use strict';

    const COOKIE_NAME = 'cookie_consent';
    const COOKIE_DAYS = 365;

    // --- Cookie kezelő segédfüggvények ---

    function getCookie(name) {
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.startsWith(name + '=')) {
                return decodeURIComponent(cookie.substring(name.length + 1));
            }
        }
        return null;
    }

    function setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        const expires = 'expires=' + date.toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + ';' + expires + ';path=/;SameSite=Lax';
    }

    // --- Banner megjelenítés / elrejtés ---

    function showBanner() {
        const banner = document.getElementById('cookieBanner');
        const overlay = document.getElementById('cookieOverlay');
        if (banner) banner.classList.add('active');
        if (overlay) overlay.classList.add('active');
    }

    function hideBanner(callback) {
        const banner = document.getElementById('cookieBanner');
        const overlay = document.getElementById('cookieOverlay');

        if (banner) {
            banner.classList.add('hiding');
            // Animáció végén eltüntetjük teljesen
            setTimeout(function () {
                banner.classList.remove('active', 'hiding');
                if (overlay) overlay.classList.remove('active');
                if (typeof callback === 'function') callback();
            }, 400);
        }
    }

    // --- Hozzájárulás mentése ---

    function saveConsent(level) {
        setCookie(COOKIE_NAME, level, COOKIE_DAYS);
        hideBanner();
    }

    // --- Inicializálás ---

    function init() {
        const consent = getCookie(COOKIE_NAME);

        // Ha már van cookie consent választás → nem jelenítjük meg
        if (consent) {
            return;
        }

        // Nincs még választás → megjelenítjük a bannert
        showBanner();

        // Gombok eseménykezelői
        const acceptAllBtn = document.getElementById('cookieAcceptAll');
        const acceptNecessaryBtn = document.getElementById('cookieAcceptNecessary');
        const declineBtn = document.getElementById('cookieDecline');

        if (acceptAllBtn) {
            acceptAllBtn.addEventListener('click', function () {
                saveConsent('all');
            });
        }

        if (acceptNecessaryBtn) {
            acceptNecessaryBtn.addEventListener('click', function () {
                saveConsent('necessary');
            });
        }

        if (declineBtn) {
            declineBtn.addEventListener('click', function () {
                saveConsent('declined');
            });
        }
    }

    // DOM betöltése után indítjuk
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Globálisan elérhetővé tesszük, ha más script-ek is akarják használni
    window.CookieConsent = {
        getConsent: function () {
            return getCookie(COOKIE_NAME);
        },
        isAccepted: function () {
            const c = getCookie(COOKIE_NAME);
            return c === 'all' || c === 'necessary';
        },
        isFullyAccepted: function () {
            return getCookie(COOKIE_NAME) === 'all';
        },
        reset: function () {
            setCookie(COOKIE_NAME, '', -1);
            showBanner();
        }
    };
})();
