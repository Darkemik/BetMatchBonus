<!-- Cookie Consent Banner -->
<div class="cookie-overlay" id="cookieOverlay"></div>
<div class="cookie-banner" id="cookieBanner">
    <div class="cookie-banner-inner">
        <div class="cookie-icon">
            <i class="fas fa-cookie-bite"></i>
        </div>
        <div class="cookie-text">
            <h3 data-i18n="cookie.title">🍪 Süti (Cookie) beállítások</h3>
            <p>
                <span data-i18n="cookie.description">A BetMatchBonus weboldalon sütiket használunk a felhasználói élmény javítása, a munkamenet kezelése és statisztikai célok érdekében. A sütik segítenek abban, hogy az oldal megfelelően működjön, megjegyezze a bejelentkezési állapotot és személyre szabott tartalmat nyújtson.</span></p>
            <p class="cookie-details">
                <span data-i18n="cookie.moreInfo">További információkért olvasd el az</span>
                <a href="<?php 
                    $cookieBasePath = '';
                    $currentPath = $_SERVER['PHP_SELF'];
                    if (strpos($currentPath, '/frontend/Components/') !== false) {
                        $cookieBasePath = '../Help/';
                    } elseif (strpos($currentPath, '/frontend/Help/') !== false) {
                        $cookieBasePath = '';
                    } else {
                        $cookieBasePath = '../../frontend/Help/';
                    }
                    // Ha a Help mappában vagyunk, relatív útvonal
                    if (strpos($currentPath, '/frontend/Help/') !== false) {
                        echo 'adatkezelesi_tajekoztatok.php';
                    } else {
                        echo $cookieBasePath . 'adatkezelesi_tajekoztatok.php';
                    }
                ?>" class="cookie-link" data-i18n="cookie.privacyLink">Adatkezelési Tájékoztatót</a>.
            </p>
        </div>
        <div class="cookie-actions">
            <button class="cookie-btn cookie-btn-accept" id="cookieAcceptAll">
                <i class="fas fa-check"></i> <span data-i18n="cookie.acceptAll">Összes elfogadása</span>
            </button>
            <button class="cookie-btn cookie-btn-necessary" id="cookieAcceptNecessary">
                <i class="fas fa-shield-alt"></i> <span data-i18n="cookie.acceptNecessary">Csak szükségesek</span>
            </button>
            <button class="cookie-btn cookie-btn-decline" id="cookieDecline">
                <i class="fas fa-times"></i> <span data-i18n="cookie.decline">Elutasítás</span>
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php
    // CSS útvonal dinamikus meghatározása
    if (strpos($currentPath, '/frontend/Help/') !== false ||
        strpos($currentPath, '/frontend/MainMenu/') !== false ||
        strpos($currentPath, '/frontend/Live/') !== false ||
        strpos($currentPath, '/frontend/Esport/') !== false ||
        strpos($currentPath, '/frontend/Bonus/') !== false ||
        strpos($currentPath, '/frontend/UserProfile/') !== false ||
        strpos($currentPath, '/frontend/Admin/') !== false) {
        echo '../../css/Cookie/cookie.css';
    } else {
        echo '../css/Cookie/cookie.css';
    }
?>">
<script src="<?php
    if (strpos($currentPath, '/frontend/Help/') !== false ||
        strpos($currentPath, '/frontend/MainMenu/') !== false ||
        strpos($currentPath, '/frontend/Live/') !== false ||
        strpos($currentPath, '/frontend/Esport/') !== false ||
        strpos($currentPath, '/frontend/Bonus/') !== false ||
        strpos($currentPath, '/frontend/UserProfile/') !== false ||
        strpos($currentPath, '/frontend/Admin/') !== false) {
        echo '../../js/Cookie/cookie.js';
    } else {
        echo '../js/Cookie/cookie.js';
    }
?>"></script>
