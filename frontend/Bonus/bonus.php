<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bónuszok | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/main.css">
    <link rel="stylesheet" href="../../css/Bonus/bonus.css">
    <link rel="stylesheet" href="../../css/Footer/footer.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
    <header class="header">
        <div class="logo-box">
            <a href="../../frontend/MainMenu/MainMenu.php"><img class="kep" src="../../img/logo.png" alt="logo"></a>
            <div class="logo"><a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a></div>
        </div>


        <nav class="nav">
            <a href="../../frontend/MainMenu/MainMenu.php" id="fooldalszoveg" onmouseover="this.style.color='#ffc89b'"
                onmouseout="this.style.color=''">Főoldal</a>

            <a href="../../frontend/Live/live.php" id="eloszoveg" onmouseover="this.style.color='#ffc89b'"
                onmouseout="this.style.color=''">Élő</a>

            <a href="../../frontend/Bonus/bonus.php" id="bonuszszoveg" style="color: orange;">Bónuszok</a>

            <a href="../../frontend/Help/help.php" id="segitsegszoveg" onmouseover="this.style.color='#ffc89b'"
                onmouseout="this.style.color=''">Segítség</a>
        </nav>
        <div class="right_side">
            <button class="loginbtn" onclick="location.href='../../frontend/Login/login.php'">Bejelentkezés</button>
            <button class="registrationbtn" onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
        </div>
    </header>
    <div class="container">
        <div class="row">
            <!-- 1. doboz DAILY BONUS -->
            <div class="doboz">
                <img src="../../img/dailybonus.jpeg" alt="Feltöltési bónusz" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">BÓNUSZ HÉTKÖZNAP</p>
                    <div class="bonus-osszeg">5 000 FT BÓNUSZT</div>
                    <div class="bonus-feltetel">
                        <strong>Töltsön fel minimum 5000 FT ÉRTÉKBEN ÉS DUPLÁZZA MEG A BEFIZETT ÖSSZEGÉT</strong>
                    </div>
                    <p class="doboz-szoveg"></p>
                    <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                    <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>

            <!-- 2. doboz ODDS PIRAMISOS -->
            <div class="doboz">
                <img src="../../img/oddspiramid.jpeg" alt="Odds Piramis" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">ODDS PIRAMIS</p>
                    <div class="bonus-feltetel">
                        <strong>Fogadjon legalább 5-ös kötésben és kapjon extra szorzót a kötésére</strong>
                    </div>
                    <p class="doboz-szoveg">A szelvény piacai szorzói minimum legyen fejenként 1.3 (Így ha például 5 ös
                        kötésben 5*1,3 akkor mi ehhez hozzá teszünk még 1,3-as oddsot a végső odds: 7,8!)</p>
                        <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                        <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>

            <!-- 3. doboz DARTSOS BÓNUSZ-->
            <div class="doboz">
                <img src="../../img/dartsbonus.jpeg" alt="Darts" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">DARTS BÓNUSZ</p>
                    <div class="bonus-osszeg">10 000 FORINT</div>
                    <div class="bonus-feltetel">
                        <strong>Fogadjon 2-es kötésben és a szelvénye eredő oddsa legyen 2.</strong>
                    </div>
                    <p class="doboz-szoveg">Kapjon 10.000FT ingyenes fogadást melyet bármire tehet.</p>
                    <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                    <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>

            <!-- 4. doboz -->
            <div class="doboz">
                <img src="../../img/oddsspaceship.jpeg" alt="Odds Űrhajó" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">ODDS ŰRHAJÓ</p>
                    <div class="bonus-osszeg"></div>
                    <div class="bonus-feltetel">
                        <strong>Speciális fogadások amelyeket mi teszünk ki, minden nap fixen 1-et!</strong>
                    </div>
                    <p class="doboz-szoveg"></p>
                    <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                    <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- 5. doboz INGYEN FOGADÁS BÓNUSZ KÉP KELL-->
            <div class="doboz">
                <img src="../../img/superodds.jpeg" alt="Fogadás" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">SZUPER ODDS</p>
                    <div class="bonus-feltetel">
                        <strong>A nap legfontosabb foci mérkőzésére az 1X2 piacra megnöveljük a szorzót!</strong>
                    </div>
                    <p class="doboz-szoveg"></p>
                    <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                    <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>

            <!-- 6. doboz -->
            <div class="doboz">
                <img src="../../img/focipalya.jpeg" alt="Focipálya" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">ELSŐ REGISZTRÁCIÓS BÓNUSZ</p>
                    <div class="bonus-osszeg">5000FT</div>
                    <div class="bonus-feltetel">
                        <strong>2-es szorzó, 2-es kötés</strong>
                    </div>
                    <p class="doboz-szoveg">Töltsön fel 5000FT-ot és mi azt megduplázzuk!</p>
                    <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                    <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>

            <!-- 7. doboz KOSÁRLABDÁS KÉP KELL-->
            <div class="doboz">
                <img src="../../img/basketballbonus.jpeg" alt="Kosárlabda" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">KOSÁRLABDA SPECIAL</p>
                    <div class="bonus-osszeg">+0.5 ODDS EXTRA</div>
                    <div class="bonus-feltetel">
                        <strong>Kosárlabda mérkőzéseken extra odds +0.5 minden fogadásra!</strong>
                    </div>
                    <p class="doboz-szoveg">NBA és EuroLeague mérkőzéseken minden fogadáshoz +0.5 odds bónuszt kap
                        automatikusan!</p>
                        <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                        <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>

            <!-- 8. doboz -->
            <div class="doboz">
                <img src="../../img/cashout.jpeg" alt="Cash out" class="doboz-kep">
                <div class="doboz-tartalom">
                    <p class="doboz-cim">CASH OUT - AZONNALI KIFIZETÉS</p>
                    <strong>Fogadásának mérkőzésektől függően pénzt vehet ki!</strong>
                    <p class="doboz-szoveg">Amennyiben a meccs/meccsek még nem indultak el 80%ban kiveheti azt. Ha a
                        szelvénye bukásfelé áll ez már csak 20%, viszont ha a szelvény nyerésre áll azt kiveheti 130%os
                        profittal.</p>
                        <a href="#" class="doboz-gomb">BEJELENTKEZÉS / REGISZTRÁCIÓ</a>
                        <button class="tobb-info-gomb" id="tobbInfoGomb">Több információ</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="simple-footer">
        <div class="footer-top">
            <div class="footer-links">
                <a href="../Help/adatkezelesi_tajekoztatok.php" class="footer-link">ADATKEZELÉSI TÁJÉKOZTATÓ</a>
                <a href="../Help/reszveteli-szabalyzat.php" class="footer-link">RÉSZVÉTELI SZABÁLYZAT</a>
                <a href="../Help/kapcsolat.php" class="footer-link">UGYFELSZOLGALAT@BETMATCHBONUS.COM</a>
                <a href="../Help/GYIK.php" class="footer-link">GYIK</a>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-content">
                <div class="responsible-text">
                    <h2>Ajánlott felelős szervező!</h2>
                    <p>Maradjon játék! 18+. A túlzásba vitt szerencsejáték ártalmas, függőséget okozhat! Kérje
                        bejegyzését a játékosvédelmi nyilvántartásba!
                        <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank">Tudj meg többet!</a>
                    </p>
                </div>
                <p>Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>
            </div>
        </div>
    </footer>

</body>

</html>