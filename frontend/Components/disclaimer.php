<!-- Disclaimer / Jogi nyilatkozat Banner -->
<div class="disclaimer-overlay" id="disclaimerOverlay"></div>
<div class="disclaimer-banner" id="disclaimerBanner">
    <div class="disclaimer-banner-inner">
        <div class="disclaimer-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="disclaimer-text">
            <h3>⚠️ Jogi nyilatkozat</h3>
            <p>
                Ez az oldal egy <strong>szimulációs vizsgaprojekt</strong>, amely kizárólag <strong>oktatási és bemutatási célból</strong> készült.
                Az oldalon megjelenő pénzmozgások <strong>virtuálisak</strong>, valódi pénz nem kerül felhasználásra, befizetésre vagy kifizetésre.
            </p>
            <p>
                A BetMatchBonus <strong>nem valódi szerencsejáték-szolgáltató</strong>, nem rendelkezik szerencsejáték-szervezési engedéllyel,
                és nem folytat valódi fogadási tevékenységet. Az oldalon látható odds-ok, meccsek és eredmények kizárólag a projekt működésének bemutatására szolgálnak.
            </p>
            <p class="disclaimer-warning">
                <i class="fas fa-ban"></i> A weboldal használata nem jelent valódi pénzügyi kockázatot és nem minősül szerencsejátéknak.
            </p>
        </div>
        <div class="disclaimer-actions">
            <button class="disclaimer-btn disclaimer-btn-accept" id="disclaimerAccept">
                <i class="fas fa-check-circle"></i> Megértettem és elfogadom
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php
    $currentPath = isset($currentPath) ? $currentPath : $_SERVER['PHP_SELF'];
    if (strpos($currentPath, '/frontend/Help/') !== false ||
        strpos($currentPath, '/frontend/MainMenu/') !== false ||
        strpos($currentPath, '/frontend/Live/') !== false ||
        strpos($currentPath, '/frontend/Esport/') !== false ||
        strpos($currentPath, '/frontend/Bonus/') !== false ||
        strpos($currentPath, '/frontend/UserProfile/') !== false ||
        strpos($currentPath, '/frontend/Admin/') !== false) {
        echo '../../css/Disclaimer/disclaimer.css';
    } else {
        echo '../css/Disclaimer/disclaimer.css';
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
        echo '../../js/Disclaimer/disclaimer.js';
    } else {
        echo '../js/Disclaimer/disclaimer.js';
    }
?>"></script>
