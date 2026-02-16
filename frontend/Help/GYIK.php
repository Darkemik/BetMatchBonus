<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segítség | BetMatchBonus</title>
    <link rel="stylesheet" href="../../css/Main/layout.css">
    <link rel="stylesheet" href="../../css/Help/help.css">
    <link rel="stylesheet" href="../../css/RootColor/root.css">
    <link rel="icon" href="../../img/logo.png" type="image/x-icon">
</head>

<body>
    <header class="header">
        <div class="logo-box">
            <a href="../../frontend/MainMenu/MainMenu.php"><img class="kep" src="../../img/logo.png" alt="logo"></a>
            <div class="logo"><a href="../../frontend/MainMenu/MainMenu.php" class="mainpage">BetMatchBonus</a></div>
        </div>

        <nav class="nav">
        <a href="../../frontend/MainMenu/MainMenu.php" data-i18n="nav.home" id="fooldalszoveg">Főoldal</a>
            <a href="../../frontend/Live/live.php" data-i18n="nav.live" id="eloszoveg">Élő</a>
            <a href="../../frontend/Bonus/bonus.php" data-i18n="nav.bonuses" id="bonuszszoveg">Bónuszok</a>
            <a href="../../frontend/Help/help.php" data-i18n="nav.help" id="segitsegszoveg">Segítség</a>
        </nav>
        <div class="right_side">
            <button class="loginbtn" onclick="location.href='../../frontend/Login/login.php'">Bejelentkezés</button>
            <button class="registrationbtn"
                onclick="location.href='../../frontend/Register/register.php'">Regisztráció</button>
        </div>
    </header>

    <div class="help-container">
        <!-- Bal oldali menü sáv -->
        <aside class="left-sidebar">
            <div class="sidebar-section">
                <h3>INFORMÁCIÓK</h3>
                <ul>
                    <li><a href="../Help/GYIK.php">GYIK</a></li>
                    <li><a href="../Help/uj_funkcio.php">Új funkciók</a></li>
                    <li><a href="../Help/sportszabalyok.php">Sportszabályok</a></li>
                    <li><a href="../Help/szotar.php">Szótár</a></li>
                    <li><a href="../Help/fizetesi_lehetosegek.php">Fizetési lehetőségek</a></li>
                    <li><a href="../Help/jatekosvedelem.php">Játékosvédelem</a></li>
                    <li><a href="../Help/informaciobiztonsag.php">Információbiztonság</a></li>
                    <li><a href="../Help/panaszkezeles.php">Panaszkezelés</a></li>
                    <li><a href="../Help/kapcsolat.php">Kapcsolat</a></li>
                    <li><a href="../Help/adatkezelesi_tajekoztatok.php">Adatkezelési tájékoztatók</a></li>
                    <li><a href="../Help/reszveteli-szabalyzat.php">Részvételi szabályzat</a></li>
                </ul>
            </div>
        </aside>

        <!-- Fő tartalom (középső rész)-->
        <main class="main-content">
            <div class="help-menu-container">
                <form>
                    <div class="help-menu">
                        <details class="level0">
                            <summary class="help-summary">REGISZTRÁCIÓ, BEJELENTKEZÉS</summary>

                            <details class="level1">
                                <summary>Hogyan tudom az e-mail címemet hitelesíteni?</summary>
                                <p>A regisztrációt követően levelet küldünk a megadott email címedre, melyben
                                    megtalálható az email cím hitelesítésére szolgáló link. A linkre kattintva a
                                    regisztrációkor megadott email cím hitelesítése megtörténik. Amennyiben az email nem
                                    érkezett meg, érdemes a spam fiókot is megnézni. Szükség esetén írj az
                                    ügyfélszolgálatnak!</p>
                            </details>
                            <details class="level1">
                                <summary>Regisztrálhatok más adataival?</summary>
                                <p>Regisztrálni kizárólag csak saját nevedben, az okmányaidban található személyes
                                    adatokkal lehet.</p>
                            </details>
                            <details class="level1">
                                <summary>Megváltoztathatom a felhasználónevemet?</summary>
                                <p>Nem, a felhasználónév nem módosítható.</p>
                            </details>
                            <details class="level1">
                                <summary>Mik a regisztráció feltételei?</summary>
                                <p>A BetMatchBonus oldalon csak 18. életévét betöltött, magyarországi tartózkodási
                                    hellyel
                                    és magyarországi bankban vezetett forint alapú bankszámlával rendelkező fogadó
                                    regisztrálhat. A regisztrációkor minden játékosnak el kell fogadnia a magára nézve
                                    kötelező BetMatchBonus Részvételi Szabályzatot, valamint az adatvédelmi
                                    tájékoztatót.
                                </p>
                            </details>
                            <details class="level1">
                                <summary>Hogyan tudok regisztrálni?</summary>
                                <p>“Kattints a jobb felső sarokban található Regisztráció gombra!
                                    A megjelenő űrlapon első lépésként a bejelentkezéshez szükséges adatokat kell
                                    megadnod: felhasználónevedet, e-mail címedet, valamint jelszót.
                                    A következő oldalon a személyes adataid megadására van szükség. Kérjük, hogy
                                    pontosan add meg az lakcímadatokat.
                                    A jóváhagyást követően a fogadási rendszer a regisztráció során megadott e-mail
                                    címedre küldi azt az aktiváló linket, melyre kattintva véglegessé válik a
                                    regisztrációd.</p>
                            </details>
                            <details class="level1">
                                <summary>Mit tehetek, ha a regisztrációkor rossz adatot adtam meg?</summary>
                                <p>A bejelentkezést követően a Beállítások/Személyes adatok menüponton belül lehetőség
                                    nyílik adatmódosításra a felhasználónév és a születési adatok kivételével – ezek
                                    pontosítására a Ügyfélszolgálat segítségével van lehetőséged emailen keresztül.
                                </p>
                            </details>
                            <details class="level1">
                                <summary>Megváltoztathatom a jelszavamat?</summary>
                                <p>A jelszavad módosítására a bejelentkezést követően, a jobb felső sarokban a profilra
                                    kattintva van lehetőség a „Jelszó módosítása” menüpontban, az alábbi módon:
                                    – a régi jelszavad beírása után van lehetőség megadni az új jelszót.
                                    – a jelszónak tartalmazni kell legalább 1 kis- és nagybetűt, számot, és különleges
                                    karaktert
                                    – amennyiben helyes jelszót adtad meg a régi jelszavad és az új jelszó is megfelel a
                                    követelményeknek, aktív lesz a Mentés gomb
                                    – a gomb megnyomását követően a regisztrált email címedre megküldünk egy hat
                                    számjegyből álló biztonsági kódot
                                    – ezt a kódot az oldalunkon megjelenő felugró ablakban tudod megadni
                                    – amennyiben a kód helyes volt, megtörténik a jelszavad módosítása.
                                    A kapott kód 5 percig érvényes.</p>
                            </details>
                            <details class="level1">
                                <summary>Miről kell nyilatkozni a regisztráció során?</summary>
                                <p>“A regisztráció során nyilatkoznod kell arról, hogy:
                                    – a távszerencsejátékban saját nevedben kívánsz részt venni,
                                    – az adatokat a regisztrációhoz saját nevedben szolgáltatod
                                    – elfogadod a Részvételi Szabályzatot
                                    – elfogadod az adatkezelési tájékoztatót.
                                    A regisztráció során hozzájárulhatsz ahhoz, hogy a Szerencsejáték Zrt. általános és
                                    személyre szabott ajánlatokkal keressen meg az általad választott kommunikációs
                                    csatornán. Ezen hozzájárulásokat később módosíthatod.”</p>
                            </details>
                            <details class="level1">
                                <summary>Regisztrálhatok többször?</summary>
                                <p>Minden játékos csak egy regisztrációt tud létrehozni. Épp ezért ha korábban
                                    rendelkeztél már regisztrációval, de mégsem tudsz bejelentkezni, kérjük, írj
                                    ügyfélszolgálatunknak a ugyfelszolgalat@betmatchbonus.com -on!
                                </p>
                            </details>
                            <details class="level1">
                                <summary>Személyes adataimban változások történtek. Mit tehetek?</summary>
                                <p>“Adataid módosítására a jogszabály szerint a változást követő 5 napon belül sort kell
                                    keríteni. Ezt a belépést követően a Beállítások/Személyes adatok menüpontban teheted
                                    meg.
                                    Adataid módosítására a Beállítások/Személyes adatok oldalon van lehetőséged. A
                                    bejelentkezést követően a jobb felső sarokban található Számlám/Fogadásaim menüpont
                                    belül a Személyes adatok fülre kattintva tudod elérni. Fontos, hogy a változást
                                    követő 5 napon belül módosítanod szükséges a változott adatokat!
                                    A nem változó adatok (születési név, édesanyja leánykori neve, születési hely és
                                    időpont) frissítésében ügyfélszolgálatunk tud segítséget nyújtani, ha a
                                    regisztrációkor elírás történt. Az ügyfélszolgálaton történő személyes adat
                                    módosítása kapcsán fenntartjuk a jogot személyazonosító dokumentumok bekéréséhez.”
                                </p>
                            </details>
                        </details>


                        <details class="level0">
                            <summary class="help-summary">HOGYAN FOGADJAK?</summary>

                            <details class="level1">
                                <summary></summary>
                                <p></p>
                            </details>
                        </details>
                    </div>
                </form>
            </div>
        </main>

        <!-- Jobb oldali sáv -->
        <aside class="right-sidebar">
            <div class="promo-kartya">
                <h3>ODDSŰRHAJÓ!</h3>
                <p>A legjobb szorzók, kizárólag nálunk!</p>
                <button class="tobb-info-gomb">RÉSZLETEK</button>
            </div>

            <div class="promo-kartya">
                <h3>ODDSPIRAMIS</h3>
                <p>Növelnéd a nyereményed? Keress aktuális ajánlatunkat a promóciók között!</p>
                <button class="tobb-info-gomb">RÉSZLETEK</button>
            </div>

            <div class="promo-kartya">
                <h3>BETMATCHBONUS MAGAZIN</h3>
                <p>Esélyek, információk, nyeremények - olvasd el aktuális bejegyzéseinket!</p>
                <button class="tobb-info-gomb">ELOLVASOM</button>
            </div>
        </aside>
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
                        <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank">Tudj megtöbbet!</a>
                    </p>
                </div>
                <p>Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>
            </div>
        </div>
    </footer>
</body>

</html>