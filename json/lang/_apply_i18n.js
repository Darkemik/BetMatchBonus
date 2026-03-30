const fs = require('fs');
const path = require('path');
const root = 'c:/xampp/htdocs/BetMatchBonus';

function r(file) { return fs.readFileSync(path.join(root, file), 'utf8'); }
function w(file, data) { fs.writeFileSync(path.join(root, file), data, 'utf8'); console.log('  ✔ ' + file); }

// ============================================================
// 1. registermodal.php
// ============================================================
let reg = r('frontend/Components/registermodal.php');
reg = reg.replace(
    /<h5 class="modal-title d-flex align-items-center gap-2">\s*<a href="[^"]*">\s*<img src="[^"]*" alt="logo">\s*<\/a>\s*Regisztráció\s*<\/h5>/s,
    `<h5 class="modal-title d-flex align-items-center gap-2">
                    <a href="../../frontend/MainMenu/MainMenu.php">
                        <img src="../../img/logo.png" alt="logo">
                    </a>
                    <span data-i18n="registerModal.title">Regisztráció</span>
                </h5>`
);
// Labels
reg = reg.replace('<label class="form-label">Felhasználónév</label>', '<label class="form-label" data-i18n="registerModal.username">Felhasználónév</label>');
reg = reg.replace('placeholder="Felhasználónév" required>', 'placeholder="Felhasználónév" data-i18n-placeholder="registerModal.usernamePlaceholder" required>');
reg = reg.replace('<label class="form-label">Email</label>', '<label class="form-label" data-i18n="registerModal.email">Email</label>');
reg = reg.replace('placeholder="Email" required>', 'placeholder="Email" data-i18n-placeholder="registerModal.emailPlaceholder" required>');
reg = reg.replace('<label class="form-label">Email újra</label>', '<label class="form-label" data-i18n="registerModal.emailAgain">Email újra</label>');
reg = reg.replace('placeholder="Email újra" required>', 'placeholder="Email újra" data-i18n-placeholder="registerModal.emailAgainPlaceholder" required>');
reg = reg.replace('<label class="form-label">Telefonszám</label>', '<label class="form-label" data-i18n="registerModal.phone">Telefonszám</label>');
reg = reg.replace('placeholder="Pl.:06308469165"', 'placeholder="Pl.:06308469165" data-i18n-placeholder="registerModal.phonePlaceholder"');
reg = reg.replace('<label class="form-label">Jelszó</label>', '<label class="form-label" data-i18n="registerModal.password">Jelszó</label>');
reg = reg.replace('id="modal-password" name="password" class="form-control" placeholder="Jelszó"', 'id="modal-password" name="password" class="form-control" placeholder="Jelszó" data-i18n-placeholder="registerModal.passwordPlaceholder"');
reg = reg.replace('<label class="form-label">Jelszó újra</label>', '<label class="form-label" data-i18n="registerModal.passwordAgain">Jelszó újra</label>');
reg = reg.replace('id="modal-password2" class="form-control" placeholder="Jelszó újra"', 'id="modal-password2" class="form-control" placeholder="Jelszó újra" data-i18n-placeholder="registerModal.passwordAgainPlaceholder"');
// Checkboxes
reg = reg.replace(
    'Elolvastam és elfogadom a\n                                <a href="../../frontend/Help/reszveteli-szabalyzat.php" target="_blank" class="modal-link">Részvételi szabályzatot</a>',
    '<span data-i18n="registerModal.acceptRules">Elolvastam és elfogadom a</span>\n                                <a href="../../frontend/Help/reszveteli-szabalyzat.php" target="_blank" class="modal-link" data-i18n="registerModal.participationRules">Részvételi szabályzatot</a>'
);
reg = reg.replace(
    'Elolvastam és elfogadom az\n                                <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php" target="_blank" class="modal-link">Adatkezelési tájékoztatót</a>',
    '<span data-i18n="registerModal.acceptPrivacy">Elolvastam és elfogadom az</span>\n                                <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php" target="_blank" class="modal-link" data-i18n="registerModal.privacyPolicy">Adatkezelési tájékoztatót</a>'
);
reg = reg.replace('<button type="submit" class="btn btn-success w-100 mb-2">Folytatás</button>', '<button type="submit" class="btn btn-success w-100 mb-2" data-i18n="registerModal.continueBtn">Folytatás</button>');
reg = reg.replace(
    'Már van fiókod?\n                    <a href="#" class="modal-link" id="switchToLogin">Jelentkezz be!</a>',
    '<span data-i18n="registerModal.hasAccount">Már van fiókod?</span>\n                    <a href="#" class="modal-link" id="switchToLogin" data-i18n="registerModal.loginLink">Jelentkezz be!</a>'
);
w('frontend/Components/registermodal.php', reg);

// ============================================================
// 2. registermodal2.php
// ============================================================
let reg2 = r('frontend/Components/registermodal2.php');
reg2 = reg2.replace(
    /Regisztráció - 2\. lépés\s*<\/h5>/,
    '<span data-i18n="registerModal2.title">Regisztráció - 2. lépés</span>\n                </h5>'
);
reg2 = reg2.replace('<label class="form-label">Előnév (ha van)</label>', '<label class="form-label" data-i18n="registerModal2.preName">Előnév (ha van)</label>');
reg2 = reg2.replace('placeholder="Előnév">', 'placeholder="Előnév" data-i18n-placeholder="registerModal2.preNamePlaceholder">');
reg2 = reg2.replace('<label class="form-label">Vezetéknév</label>', '<label class="form-label" data-i18n="registerModal2.familyName">Vezetéknév</label>');
reg2 = reg2.replace('placeholder="Vezetéknév"', 'placeholder="Vezetéknév" data-i18n-placeholder="registerModal2.familyNamePlaceholder"');
reg2 = reg2.replace('<label class="form-label">Keresztnév</label>', '<label class="form-label" data-i18n="registerModal2.sureName">Keresztnév</label>');
reg2 = reg2.replace('placeholder="Keresztnév"', 'placeholder="Keresztnév" data-i18n-placeholder="registerModal2.sureNamePlaceholder"');
reg2 = reg2.replace('<label class="form-label">Anyja leánykori neve</label>', '<label class="form-label" data-i18n="registerModal2.motherName">Anyja leánykori neve</label>');
reg2 = reg2.replace('placeholder="Anyja leánykori neve"', 'placeholder="Anyja leánykori neve" data-i18n-placeholder="registerModal2.motherNamePlaceholder"');
reg2 = reg2.replace('<label class="form-label">Születési hely</label>', '<label class="form-label" data-i18n="registerModal2.birthPlace">Születési hely</label>');
reg2 = reg2.replace('placeholder="pl. Budapest"', 'placeholder="pl. Budapest" data-i18n-placeholder="registerModal2.birthPlacePlaceholder"');
reg2 = reg2.replace('<label class="form-label">Születési dátum</label>', '<label class="form-label" data-i18n="registerModal2.birthDate">Születési dátum</label>');
reg2 = reg2.replace('<label class="form-label">Személyi igazolvány (eleje)</label>', '<label class="form-label" data-i18n="registerModal2.idFront">Személyi igazolvány (eleje)</label>');
reg2 = reg2.replace('<label class="form-label">Személyi igazolvány (hátulja)</label>', '<label class="form-label" data-i18n="registerModal2.idBack">Személyi igazolvány (hátulja)</label>');
reg2 = reg2.replace('<label class="form-label">Lakcímkártya</label>', '<label class="form-label" data-i18n="registerModal2.addressCard">Lakcímkártya</label>');
reg2 = reg2.replace('>Regisztráció befejezése</button>', ' data-i18n="registerModal2.finishBtn">Regisztráció befejezése</button>');
reg2 = reg2.replace('>← Vissza az előző lépéshez</a>', ' data-i18n="registerModal2.backLink">← Vissza az előző lépéshez</a>');
w('frontend/Components/registermodal2.php', reg2);

// ============================================================
// 3. forgotmypassword.php
// ============================================================
let forgot = r('frontend/Components/forgotmypassword.php');
// First modal title
forgot = forgot.replace(
    /Elfelejtettem a jelszavam\s*<\/h5>/,
    '<span data-i18n="forgotPassword.title">Elfelejtettem a jelszavam</span>\n                </h5>'
);
forgot = forgot.replace(
    'Semmi probléma! Csak add meg az e-mail címedet a felhasználóneved-del vagy a születési dátumoddal,\n                    és kövesd az e-mailben kapott utasításokat!',
    '<span data-i18n="forgotPassword.description">Semmi probléma! Csak add meg az e-mail címedet a felhasználóneved-del vagy a születési dátumoddal, és kövesd az e-mailben kapott utasításokat!</span>'
);
forgot = forgot.replace('<label class="form-label">E-mail cím</label>\n                    <input type="email" name="email" id="forgot-email"', '<label class="form-label" data-i18n="forgotPassword.email">E-mail cím</label>\n                    <input type="email" name="email" id="forgot-email"');
forgot = forgot.replace('id="forgot-email" class="form-control mb-3"\n                        placeholder="E-mail cím"', 'id="forgot-email" class="form-control mb-3"\n                        placeholder="E-mail cím" data-i18n-placeholder="forgotPassword.emailPlaceholder"');
forgot = forgot.replace('<label class="form-label">Felhasználónév</label>\n                    <input type="text" name="username" id="forgot-username"', '<label class="form-label" data-i18n="forgotPassword.username">Felhasználónév</label>\n                    <input type="text" name="username" id="forgot-username"');
forgot = forgot.replace('id="forgot-username" class="form-control mb-3"\n                        placeholder="Felhasználónév"', 'id="forgot-username" class="form-control mb-3"\n                        placeholder="Felhasználónév" data-i18n-placeholder="forgotPassword.usernamePlaceholder"');
forgot = forgot.replace('id="switchToUsernameHelp"\n                        style="display: block; margin-bottom: 15px;">Nem tudom a felhasználó nevem</a>', 'id="switchToUsernameHelp"\n                        style="display: block; margin-bottom: 15px;" data-i18n="forgotPassword.dontKnowUsername">Nem tudom a felhasználó nevem</a>');
forgot = forgot.replace('>Új jelszó beállítása</button>', ' data-i18n="forgotPassword.submitBtn">Új jelszó beállítása</button>');

// forgotPassword modal footer
forgot = forgot.replace(
    'Még nincs fiókod?\n                            <a href="#" class="modal-link" id="switchToRegisterFromForgot">Regisztrálj!</a>',
    '<span data-i18n="forgotPassword.noAccount">Még nincs fiókod?</span>\n                            <a href="#" class="modal-link" id="switchToRegisterFromForgot" data-i18n="forgotPassword.registerLink">Regisztrálj!</a>'
);

// Second modal - username recovery
forgot = forgot.replace(
    /Felhasználónév helyreállítása\s*<\/h5>/,
    '<span data-i18n="forgotUsername.title">Felhasználónév helyreállítása</span>\n                </h5>'
);
forgot = forgot.replace(
    'Semmi probléma! Csak add meg az e-mail címedet a születési dátumoddal, és el fogjuk küldeni a\n                    felhasználónevedet!',
    '<span data-i18n="forgotUsername.description">Semmi probléma! Csak add meg az e-mail címedet a születési dátumoddal, és el fogjuk küldeni a felhasználónevedet!</span>'
);
forgot = forgot.replace('<label class="form-label">E-mail cím</label>\n                    <input type="email" name="email" id="username-help-email"', '<label class="form-label" data-i18n="forgotUsername.email">E-mail cím</label>\n                    <input type="email" name="email" id="username-help-email"');
forgot = forgot.replace('id="username-help-email" class="form-control mb-3"\n                        placeholder="E-mail cím"', 'id="username-help-email" class="form-control mb-3"\n                        placeholder="E-mail cím" data-i18n-placeholder="forgotUsername.emailPlaceholder"');
forgot = forgot.replace('<label class="form-label">Születési idő</label>', '<label class="form-label" data-i18n="forgotUsername.birthDate">Születési idő</label>');
forgot = forgot.replace('>Felhasználónév megküldése</button>', ' data-i18n="forgotUsername.submitBtn">Felhasználónév megküldése</button>');
forgot = forgot.replace('>← Vissza a jelszó\n                                helyreállításához</a>', ' data-i18n="forgotUsername.backLink">← Vissza a jelszó helyreállításához</a>');
w('frontend/Components/forgotmypassword.php', forgot);

// ============================================================
// 4. footer.php
// ============================================================
let footer = r('frontend/Components/footer.php');
footer = footer.replace('>ADATKEZELÉSI TÁJÉKOZTATÓ</a>', ' data-i18n="footer.privacyPolicy">ADATKEZELÉSI TÁJÉKOZTATÓ</a>');
footer = footer.replace('>RÉSZVÉTELI SZABÁLYZAT</a>', ' data-i18n="footer.participationRules">RÉSZVÉTELI SZABÁLYZAT</a>');
footer = footer.replace('>UGYFELSZOLGALAT@BETMATCHBONUS.COM</a>', ' data-i18n="footer.customerService">UGYFELSZOLGALAT@BETMATCHBONUS.COM</a>');
footer = footer.replace('>GYIK</a>', ' data-i18n="footer.faq">GYIK</a>');
footer = footer.replace('<h2>Ajánlott felelős szervező!</h2>', '<h2 data-i18n="footer.responsibleTitle">Ajánlott felelős szervező!</h2>');
footer = footer.replace(
    'Maradjon játék! 18+. A túlzásba vitt szerencsejáték ártalmas, függőséget okozhat! \n                   Kérje bejegyzését a játékosvédelmi nyilvántartásba!\n                   <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank">Tudj meg többet!</a>',
    '<span data-i18n="footer.responsibleText">Maradjon játék! 18+. A túlzásba vitt szerencsejáték ártalmas, függőséget okozhat! Kérje bejegyzését a játékosvédelmi nyilvántartásba!</span>\n                   <a href="../Help/jatekosvedelem.php" class="tudjmegtobbeta" target="_blank" data-i18n="footer.learnMore">Tudj meg többet!</a>'
);
footer = footer.replace(
    '<p>Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>',
    '<p data-i18n="footer.copyright">Élményt nyújtunk, értéket teremtünk © BetMatchBonus – 2026. Minden jog fenntartva.</p>'
);
w('frontend/Components/footer.php', footer);

// ============================================================
// 5. cookie_consent.php
// ============================================================
let cookie = r('frontend/Components/cookie_consent.php');
cookie = cookie.replace('<h3>🍪 Süti (Cookie) beállítások</h3>', '<h3 data-i18n="cookie.title">🍪 Süti (Cookie) beállítások</h3>');
cookie = cookie.replace(
    /A BetMatchBonus weboldalon sütiket használunk[^<]+/,
    '<span data-i18n="cookie.description">A BetMatchBonus weboldalon sütiket használunk a felhasználói élmény javítása, a munkamenet kezelése és statisztikai célok érdekében. A sütik segítenek abban, hogy az oldal megfelelően működjön, megjegyezze a bejelentkezési állapotot és személyre szabott tartalmat nyújtson.</span>'
);
cookie = cookie.replace(
    /További információkért olvasd el az\s*\n\s*<a/,
    '<span data-i18n="cookie.moreInfo">További információkért olvasd el az</span>\n                <a'
);
cookie = cookie.replace('" class="cookie-link">Adatkezelési Tájékoztatót</a>', '" class="cookie-link" data-i18n="cookie.privacyLink">Adatkezelési Tájékoztatót</a>');
cookie = cookie.replace('<i class="fas fa-check"></i> Összes elfogadása', '<i class="fas fa-check"></i> <span data-i18n="cookie.acceptAll">Összes elfogadása</span>');
cookie = cookie.replace('<i class="fas fa-shield-alt"></i> Csak szükségesek', '<i class="fas fa-shield-alt"></i> <span data-i18n="cookie.acceptNecessary">Csak szükségesek</span>');
cookie = cookie.replace('<i class="fas fa-times"></i> Elutasítás', '<i class="fas fa-times"></i> <span data-i18n="cookie.decline">Elutasítás</span>');
w('frontend/Components/cookie_consent.php', cookie);

// ============================================================
// 6. betslip.php
// ============================================================
let betslip = r('frontend/Components/betslip.php');
betslip = betslip.replace('<span id="betslip-label">Ticket</span>', '<span id="betslip-label" data-i18n="betslip.ticket">Ticket</span>');
betslip = betslip.replace('data-tab="ticket">🎫 Ticket</button>', 'data-tab="ticket">🎫 <span data-i18n="betslip.ticket">Ticket</span></button>');
betslip = betslip.replace('data-tab="elozmeny">📊 Előzmények</button>', 'data-tab="elozmeny">📊 <span data-i18n="betslip.history">Előzmények</span></button>');
betslip = betslip.replace('data-type="egyes">Egyes</button>', 'data-type="egyes" data-i18n="betslip.single">Egyes</button>');
betslip = betslip.replace('data-type="kotes">Kötés</button>', 'data-type="kotes" data-i18n="betslip.combo">Kötés</button>');
betslip = betslip.replace('<p id="empty-message">Nincs aktív fogadás</p>', '<p id="empty-message" data-i18n="betslip.emptyTitle">Nincs aktív fogadás</p>');
betslip = betslip.replace('>Válassz meccseket és odds-okat!</span>', ' data-i18n="betslip.emptySubtitle">Válassz meccseket és odds-okat!</span>');
betslip = betslip.replace('<span id="summary-label">Összesített odds:</span>', '<span id="summary-label" data-i18n="betslip.totalOdds">Összesített odds:</span>');
betslip = betslip.replace('<span id="stake-label">Tét (Ft):</span>', '<span id="stake-label" data-i18n="betslip.stake">Tét (Ft):</span>');
betslip = betslip.replace('<span id="payout-label">Lehetséges nyeremény:</span>', '<span id="payout-label" data-i18n="betslip.potentialPayout">Lehetséges nyeremény:</span>');
betslip = betslip.replace('<i class="fas fa-check"></i> Ticket leadása', '<i class="fas fa-check"></i> <span data-i18n="betslip.placeBet">Ticket leadása</span>');
betslip = betslip.replace('<i class="fas fa-trash"></i> Összes törlése', '<i class="fas fa-trash"></i> <span data-i18n="betslip.clearAll">Összes törlése</span>');
betslip = betslip.replace('<p>Még nincs korábbi fogadás</p>', '<p data-i18n="betslip.noHistory">Még nincs korábbi fogadás</p>');
betslip = betslip.replace('>Az első ticket itt jelenik meg</span>', ' data-i18n="betslip.noHistorySubtitle">Az első ticket itt jelenik meg</span>');
w('frontend/Components/betslip.php', betslip);

// ============================================================
// 7. promokartya.php
// ============================================================
let promo = r('frontend/Components/promokartya.php');
promo = promo.replace('<h3>ODDSŰRHAJÓ!</h3>', '<h3 data-i18n="promo.oddsShipTitle">ODDSŰRHAJÓ!</h3>');
promo = promo.replace('<p>A legjobb szorzók, kizárólag nálunk!</p>', '<p data-i18n="promo.oddsShipDesc">A legjobb szorzók, kizárólag nálunk!</p>');
promo = promo.replace('<h3>CASH OUT - AZONNALI KIFIZETÉS</h3>', '<h3 data-i18n="promo.cashoutTitle">CASH OUT - AZONNALI KIFIZETÉS</h3>');
promo = promo.replace(/A Cash Out használatával[^<]+/, '<span data-i18n="promo.cashoutDesc">A Cash Out használatával megjátszott fogadásaidat még az esemény vége előtt, saját döntésedre lezárhatod, ezzel pedig biztosíthatod a nyereményedet</span>');
promo = promo.replace('<h3>ODDSPIRAMIS</h3>', '<h3 data-i18n="promo.oddsPyramidTitle">ODDSPIRAMIS</h3>');
promo = promo.replace('<p>Növelnéd a nyereményed? Keress aktuális ajánlatunkat a promóciók között!</p>', '<p data-i18n="promo.oddsPyramidDesc">Növelnéd a nyereményed? Keress aktuális ajánlatunkat a promóciók között!</p>');
promo = promo.replace(/RÉSZLETEK\s*\n\s*<\/button>/g, '<span data-i18n="promo.details">RÉSZLETEK</span>\n                </button>');
w('frontend/Components/promokartya.php', promo);

// ============================================================
// 8. chatbot.php
// ============================================================
let chat = r('frontend/Components/chatbot.php');
chat = chat.replace('<span class="chatbot-name">BMB Asszisztens</span>', '<span class="chatbot-name" data-i18n="chatbot.name">BMB Asszisztens</span>');
chat = chat.replace(/<span class="chatbot-status"><span class="chatbot-status-dot"><\/span> Online<\/span>/, '<span class="chatbot-status"><span class="chatbot-status-dot"></span> <span data-i18n="chatbot.online">Online</span></span>');
chat = chat.replace('id="chatbotClear" title="Beszélgetés törlése"', 'id="chatbotClear" title="Beszélgetés törlése" data-i18n-title="chatbot.clearChat"');
chat = chat.replace('id="chatbotClose" title="Bezárás"', 'id="chatbotClose" title="Bezárás" data-i18n-title="chatbot.close"');
chat = chat.replace('placeholder="Írj egy kérdést..."', 'placeholder="Írj egy kérdést..." data-i18n-placeholder="chatbot.inputPlaceholder"');
chat = chat.replace('id="chatbotSend" title="Küldés"', 'id="chatbotSend" title="Küldés" data-i18n-title="chatbot.send"');
chat = chat.replace('data-question="Hogyan fogadhatok?">🎯 Hogyan fogadhatok?</button>', 'data-question="Hogyan fogadhatok?" data-i18n="chatbot.howToBet">🎯 Hogyan fogadhatok?</button>');
chat = chat.replace('data-question="Milyen sportokra fogadhatok?">🏆 Sportágak</button>', 'data-question="Milyen sportokra fogadhatok?" data-i18n="chatbot.sports">🏆 Sportágak</button>');
chat = chat.replace('data-question="Milyen bónuszok vannak?">🎁 Bónuszok</button>', 'data-question="Milyen bónuszok vannak?" data-i18n="chatbot.bonuses">🎁 Bónuszok</button>');
chat = chat.replace('data-question="Hogyan fizethetek be?">💳 Befizetés</button>', 'data-question="Hogyan fizethetek be?" data-i18n="chatbot.depositQ">💳 Befizetés</button>');
chat = chat.replace('data-question="Hogyan kérhetek kifizetést?">💰 Kifizetés</button>', 'data-question="Hogyan kérhetek kifizetést?" data-i18n="chatbot.withdrawalQ">💰 Kifizetés</button>');
chat = chat.replace('data-question="Hol találom az élő meccseket?">⚽ Élő meccsek</button>', 'data-question="Hol találom az élő meccseket?" data-i18n="chatbot.liveMatchesQ">⚽ Élő meccsek</button>');
chat = chat.replace('data-question="Mi az az eSport?">🎮 eSport</button>', 'data-question="Mi az az eSport?" data-i18n="chatbot.esportQ">🎮 eSport</button>');
w('frontend/Components/chatbot.php', chat);

// ============================================================
// 9. MainMenu.php — nav + special sections + language.js script
// ============================================================
let mm = r('frontend/MainMenu/MainMenu.php');
// Nav links
mm = mm.replace('>Főoldal</a>\n        <a href="../../frontend/Live/live.php">Élő</a>\n        <a href="../../frontend/Esport/esport.php">eSport</a>\n        <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>\n        <a href="../../frontend/Help/help.php">Segítség</a>',
    '><span data-i18n="nav.home">Főoldal</span></a>\n        <a href="../../frontend/Live/live.php"><span data-i18n="nav.live">Élő</span></a>\n        <a href="../../frontend/Esport/esport.php"><span data-i18n="nav.esport">eSport</span></a>\n        <a href="../../frontend/Bonus/bonus.php"><span data-i18n="nav.bonuses">Bónuszok</span></a>\n        <a href="../../frontend/Help/help.php"><span data-i18n="nav.help">Segítség</span></a>');
// Speciális menu
mm = mm.replace('<i class="fas fa-star"></i> Speciális', '<i class="fas fa-star"></i> <span data-i18n="mainMenu.special">Speciális</span>');
mm = mm.replace('<span>Oddsűrhajó</span>', '<span data-i18n="mainMenu.oddsShip">Oddsűrhajó</span>');
// Sidebar
mm = mm.replace('<i class="fas fa-spinner fa-spin"></i> Betöltés...</div>', '<i class="fas fa-spinner fa-spin"></i> <span data-i18n="mainMenu.loading">Betöltés...</span></div>');
mm = mm.replace('<i class="fas fa-arrow-left"></i> Összes sport</button>', '<i class="fas fa-arrow-left"></i> <span data-i18n="mainMenu.allSports">Összes sport</span></button>');
// Center
mm = mm.replace('<i class="fas fa-calendar-day"></i> Mai meccsek</h2>', '<i class="fas fa-calendar-day"></i> <span data-i18n="mainMenu.todayMatches">Mai meccsek</span></h2>');
mm = mm.replace('placeholder="Meccs keresése..."', 'placeholder="Meccs keresése..." data-i18n-placeholder="mainMenu.searchPlaceholder"');
mm = mm.replace('<i class="fas fa-spinner fa-spin"></i> Meccsek betöltése...</div>', '<i class="fas fa-spinner fa-spin"></i> <span data-i18n="mainMenu.loadingMatches">Meccsek betöltése...</span></div>');
// Add language.js before layout.js
mm = mm.replace('<script src="../../js/Main/layout.js"></script>', '<script src="../../js/Main/language.js"></script>\n  <script src="../../js/Main/layout.js"></script>');
w('frontend/MainMenu/MainMenu.php', mm);

// ============================================================
// 10. live.php
// ============================================================
let live = r('frontend/Live/live.php');
live = live.replace('>Főoldal</a>\n            <a href="../../frontend/Live/live.php" class="active">Élő</a>\n            <a href="../../frontend/Esport/esport.php">eSport</a>\n            <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>\n            <a href="../../frontend/Help/help.php">Segítség</a>',
    '><span data-i18n="nav.home">Főoldal</span></a>\n            <a href="../../frontend/Live/live.php" class="active"><span data-i18n="nav.live">Élő</span></a>\n            <a href="../../frontend/Esport/esport.php"><span data-i18n="nav.esport">eSport</span></a>\n            <a href="../../frontend/Bonus/bonus.php"><span data-i18n="nav.bonuses">Bónuszok</span></a>\n            <a href="../../frontend/Help/help.php"><span data-i18n="nav.help">Segítség</span></a>');
live = live.replace('<h1 class="elo-title" id="elo-title">Élő meccsek</h1>', '<h1 class="elo-title" id="elo-title" data-i18n="live.title">Élő meccsek</h1>');
live = live.replace('<i class="fas fa-spinner fa-spin"></i> Sportok betöltése...</div>', '<i class="fas fa-spinner fa-spin"></i> <span data-i18n="live.loadingSports">Sportok betöltése...</span></div>');
live = live.replace('<button class="tab-button active">Élő meccsek</button>', '<button class="tab-button active" data-i18n="live.liveMatches">Élő meccsek</button>');
live = live.replace('<script src="../../js/Main/layout.js"></script>', '<script src="../../js/Main/language.js"></script>\n    <script src="../../js/Main/layout.js"></script>');
w('frontend/Live/live.php', live);

// ============================================================
// 11. esport.php
// ============================================================
let esport = r('frontend/Esport/esport.php');
esport = esport.replace('>Főoldal</a>\n            <a href="../../frontend/Live/live.php">Élő</a>\n            <a href="../../frontend/Esport/esport.php" class="active">eSport</a>\n            <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>\n            <a href="../../frontend/Help/help.php">Segítség</a>',
    '><span data-i18n="nav.home">Főoldal</span></a>\n            <a href="../../frontend/Live/live.php"><span data-i18n="nav.live">Élő</span></a>\n            <a href="../../frontend/Esport/esport.php" class="active"><span data-i18n="nav.esport">eSport</span></a>\n            <a href="../../frontend/Bonus/bonus.php"><span data-i18n="nav.bonuses">Bónuszok</span></a>\n            <a href="../../frontend/Help/help.php"><span data-i18n="nav.help">Segítség</span></a>');
esport = esport.replace('<h1 class="elo-title"><i class="fas fa-gamepad"></i> eSport</h1>', '<h1 class="elo-title"><i class="fas fa-gamepad"></i> <span data-i18n="esport.title">eSport</span></h1>');
esport = esport.replace('<i class="fas fa-calendar-day"></i> Összes mai meccs', '<i class="fas fa-calendar-day"></i> <span data-i18n="esport.allTodayMatches">Összes mai meccs</span>');
esport = esport.replace('<i class="fas fa-broadcast-tower"></i> Élő meccsek', '<i class="fas fa-broadcast-tower"></i> <span data-i18n="esport.liveMatches">Élő meccsek</span>');
esport = esport.replace('<i class="fas fa-spinner fa-spin"></i> Mai meccsek betöltése...', '<i class="fas fa-spinner fa-spin"></i> <span data-i18n="esport.loadingToday">Mai meccsek betöltése...</span>');
esport = esport.replace('<i class="fas fa-spinner fa-spin"></i> Élő meccsek betöltése...</div>', '<i class="fas fa-spinner fa-spin"></i> <span data-i18n="esport.loadingLive">Élő meccsek betöltése...</span></div>');
esport = esport.replace('<script src="../../js/Main/layout.js"></script>', '<script src="../../js/Main/language.js"></script>\n    <script src="../../js/Main/layout.js"></script>');
w('frontend/Esport/esport.php', esport);

// ============================================================
// 12. bonus.php - add language.js
// ============================================================
let bonus = r('frontend/Bonus/bonus.php');
bonus = bonus.replace('<script src="../../js/Main/layout.js"></script>', '<script src="../../js/Main/language.js"></script>\n    <script src="../../js/Main/layout.js"></script>');
w('frontend/Bonus/bonus.php', bonus);

// ============================================================
// 13. Help pages — add language.js to all
// ============================================================
const helpFiles = [
    'frontend/Help/help.php',
    'frontend/Help/GYIK.php',
    'frontend/Help/adatkezelesi_tajekoztatok.php',
    'frontend/Help/fizetesi_lehetosegek.php',
    'frontend/Help/informaciobiztonsag.php',
    'frontend/Help/jatekosvedelem.php',
    'frontend/Help/kapcsolat.php',
    'frontend/Help/panaszkezeles.php',
    'frontend/Help/reszveteli-szabalyzat.php',
    'frontend/Help/sportszabalyok.php',
    'frontend/Help/szotar.php',
    'frontend/Help/uj_funkcio.php',
];

helpFiles.forEach(f => {
    try {
        let content = r(f);
        if (!content.includes('language.js')) {
            content = content.replace('<script src="../../js/Main/layout.js"></script>', '<script src="../../js/Main/language.js"></script>\n    <script src="../../js/Main/layout.js"></script>');
            w(f, content);
        } else {
            console.log('  (skip) ' + f + ' - already has language.js');
        }
    } catch (e) {
        console.log('  ⚠ ' + f + ' - ' + e.message);
    }
});

console.log('\n✅ All i18n attributes applied successfully!');
