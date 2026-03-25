<!-- Cookie Consent Banner -->
<div class="cookie-overlay" id="cookieOverlay"></div>
<div class="cookie-banner" id="cookieBanner">
    <div class="cookie-banner-inner">
        <div class="cookie-icon">
            <i class="fas fa-cookie-bite"></i>
        </div>
        <div class="cookie-text">
            <h3>🍪 Süti (Cookie) beállítások</h3>
            <p>
                A BetMatchBonus weboldalon sütiket használunk a felhasználói élmény javítása, 
                a munkamenet kezelése és statisztikai célok érdekében. 
                A sütik segítenek abban, hogy az oldal megfelelően működjön, 
                megjegyezze a bejelentkezési állapotot és személyre szabott tartalmat nyújtson.
            </p>
            <p class="cookie-details">
                További információkért olvasd el az 
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
                ?>" class="cookie-link">Adatkezelési Tájékoztatót</a>.
            </p>
        </div>
        <div class="cookie-actions">
            <button class="cookie-btn cookie-btn-accept" id="cookieAcceptAll">
                <i class="fas fa-check"></i> Összes elfogadása
            </button>
            <button class="cookie-btn cookie-btn-necessary" id="cookieAcceptNecessary">
                <i class="fas fa-shield-alt"></i> Csak szükségesek
            </button>
            <button class="cookie-btn cookie-btn-decline" id="cookieDecline">
                <i class="fas fa-times"></i> Elutasítás
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
