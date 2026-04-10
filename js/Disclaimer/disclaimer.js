/**
 * BetMatchBonus - Disclaimer (Jogi nyilatkozat)
 * 
 * A cookie consent UTÁN jelenik meg, ha a felhasználó még nem fogadta el.
 * Értéke a "disclaimer_accepted" sessionStorage-ban tárolódik,
 * tehát minden új böngészőablak-megnyitáskor újra megjelenik.
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'disclaimer_accepted';

    function showDisclaimer() {
        const banner = document.getElementById('disclaimerBanner');
        const overlay = document.getElementById('disclaimerOverlay');
        if (banner) banner.classList.add('active');
        if (overlay) overlay.classList.add('active');
    }

    function hideDisclaimer() {
        const banner = document.getElementById('disclaimerBanner');
        const overlay = document.getElementById('disclaimerOverlay');

        if (banner) {
            banner.classList.add('hiding');
            setTimeout(function () {
                banner.classList.remove('active', 'hiding');
                if (overlay) overlay.classList.remove('active');
            }, 400);
        }
    }

    function init() {
        // Ha már elfogadta ebben a session-ben → ne jelenjen meg
        if (sessionStorage.getItem(STORAGE_KEY) === '1') {
            return;
        }

        // Megvárjuk, hogy a cookie banner eltűnjön (ha van)
        function tryShow() {
            const cookieBanner = document.getElementById('cookieBanner');
            // Ha a cookie banner még aktív, várunk
            if (cookieBanner && cookieBanner.classList.contains('active')) {
                setTimeout(tryShow, 500);
                return;
            }
            showDisclaimer();
        }

        // Kis késleltetés, hogy a cookie banner először jelenjen meg
        setTimeout(tryShow, 600);

        // Elfogadás gomb
        const acceptBtn = document.getElementById('disclaimerAccept');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                sessionStorage.setItem(STORAGE_KEY, '1');
                hideDisclaimer();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
