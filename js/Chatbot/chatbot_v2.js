/**
 * BetMatchBonus - BMB Asszisztens v2.0
 * 
 * Fejlesztések:
 *  - Kontextus-érzékeny (tudja, melyik oldalon vagy)
 *  - Interaktív parancsok (#egyenleg, #bónusz, #szelvény, #élő)
 *  - Bővített tudásbázis (~35 téma, fuzzy matching)
 *  - Vizuális upgrade (kártya válaszok, progress bar, animált avatar)
 */

(function () {
    'use strict';

    // ===== KONTEXTUS FELISMERÉS =====
    var pageContext = (function () {
        var path = window.location.pathname.toLowerCase();
        if (path.indexOf('/live/') !== -1) return 'live';
        if (path.indexOf('/bonus/') !== -1 || path.indexOf('/my_bonuses') !== -1) return 'bonus';
        if (path.indexOf('/esport/') !== -1) return 'esport';
        if (path.indexOf('/deposit') !== -1) return 'deposit';
        if (path.indexOf('/withdrawal') !== -1) return 'withdrawal';
        if (path.indexOf('/personal_data') !== -1) return 'profile';
        if (path.indexOf('/transaction_history') !== -1) return 'transactions';
        if (path.indexOf('/change_password') !== -1) return 'password';
        if (path.indexOf('/help/') !== -1 || path.indexOf('/gyik') !== -1) return 'help';
        if (path.indexOf('/mainmenu/') !== -1) return 'main';
        return 'other';
    })();

    // ===== KONTEXTUS TIPPEK =====
    var contextTips = {
        live: '🔴 Jelenleg az <b>Élő</b> oldalon vagy. Kattints egy meccsre a piacok megtekintéséhez, vagy kérdezz rá: <b>#élő</b>',
        bonus: '🎁 A <b>Bónuszok</b> oldalon jársz. Írd be <b>#bónusz</b> az aktív bónuszaid megtekintéséhez!',
        esport: '🎮 Az <b>eSport</b> rovatban vagy. Nézd meg az élő e-sport meccseket!',
        deposit: '💳 A <b>Befizetés</b> oldalon vagy. Kérdezz bátran a fizetési módokról!',
        withdrawal: '💰 A <b>Kifizetés</b> oldalon vagy. Ha kérdésed van, segítek!',
        profile: '📋 A <b>Személyes adatok</b> oldalon jársz. Ellenőrizd, hogy naprakészek-e az adataid!',
        transactions: '🧾 A <b>Tranzakciók</b> oldalon vagy. Itt minden pénzmozgásodat láthatod.',
        main: '⚽ A <b>Főoldalon</b> vagy. Válassz sportot a bal menüből, vagy kérdezz: <b>#élő</b>'
    };

    // ===== INTERAKTÍV PARANCSOK =====
    var COMMANDS = {
        '#egyenleg': fetchBalance,
        '#balance': fetchBalance,
        '#pénz': fetchBalance,
        '#bónusz': fetchBonuses,
        '#bonus': fetchBonuses,
        '#szelvény': fetchHistory,
        '#ticket': fetchHistory,
        '#előzmény': fetchHistory,
        '#élő': fetchLive,
        '#live': fetchLive,
        '#statisztika': fetchStats,
        '#stat': fetchStats,
        '#összegzés': fetchSummary,
        '#summary': fetchSummary,
        '#help': showCommands,
        '#parancs': showCommands,
        '#parancsok': showCommands,
    };

    // ===== TUDÁSBÁZIS =====
    var knowledgeBase = [
        {
            keywords: ['fogad', 'tippel', 'odds', 'szelvény', 'ticket', 'hogyan fogad', 'fogadás', 'tét'],
            answer: '🎯 <b>Hogyan fogadhatsz?</b><br><br>' +
                '<div class="chat-steps">' +
                '<div class="chat-step"><span class="step-num">1</span> Válassz meccset a <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a></div>' +
                '<div class="chat-step"><span class="step-num">2</span> Kattints a meccsre → válassz piacot (1X2, Gólszám…)</div>' +
                '<div class="chat-step"><span class="step-num">3</span> Kattints az oddsra → megjelenik a Ticket sávban</div>' +
                '<div class="chat-step"><span class="step-num">4</span> Add meg a tétet (min. 100 Ft)</div>' +
                '<div class="chat-step"><span class="step-num">5</span> Kattints a <b>"Ticket leadása"</b> gombra!</div>' +
                '</div>'
        },
        {
            keywords: ['bónusz', 'bonus', 'promó', 'promo', 'akció', 'ajánlat', 'kód', 'kupón', 'ingyenes'],
            answer: '🎁 <b>Bónuszok és promóciók</b><br><br>' +
                'Az aktuális bónuszainkat a <a href="../../frontend/Bonus/bonus.php">Bónuszok</a> oldalon találod!<br><br>' +
                '• <b>Hétköznapi bónusz</b> – 100% max 5.000 Ft<br>' +
                '• <b>Darts bónusz</b> – 10.000 Ft fogadás → 5.000 Ft bónusz<br>' +
                '• <b>Cashback</b> – 30% Free Bet vesztes fogadás után<br>' +
                '• <b>Napi Top Jutalom</b> – 1.000 Ft Free Bet<br><br>' +
                '💡 Írd be <b>#bónusz</b> az aktív bónuszaid megtekintéséhez!'
        },
        {
            keywords: ['befizet', 'deposit', 'feltölt', 'pénz', 'bankkártya', 'átutalás', 'hogyan fizet', 'fizethetek be', 'fizetek be', 'befizetés'],
            get answer() {
                var minDep = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_deposit) || 3000;
                return '💳 <b>Befizetés</b><br><br>' +
                    '<div class="chat-info-card">' +
                    '<div class="chat-info-row"><span class="chat-info-label">Mód</span><span>Bankkártya (Visa, MC), Átutalás</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Min.</span><span><b>' + minDep.toLocaleString('hu-HU') + ' Ft</b></span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Gyors</span><span>5.000 / 7.500 / 10.000 / 20.000 Ft</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Idő</span><span>Azonnali ⚡</span></div>' +
                    '</div>' +
                    '<a href="../../frontend/UserProfile/deposit.php" class="chat-action-link">💳 Befizetés oldal →</a>';
            }
        },
        {
            keywords: ['kifizet', 'withdrawal', 'kivét', 'nyeremény', 'pénzfelvét', 'kifizetés', 'kiutal', 'kérhetek kifizet', 'pénzt felvenni'],
            get answer() {
                var minW = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_withdrawal) || 6000;
                return '💰 <b>Kifizetés</b><br><br>' +
                    '<div class="chat-info-card">' +
                    '<div class="chat-info-row"><span class="chat-info-label">Mód</span><span>Banki átutalás</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Min.</span><span><b>' + minW.toLocaleString('hu-HU') + ' Ft</b></span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Idő</span><span>1–3 munkanap</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Név</span><span>Saját névre szóló bankszámla</span></div>' +
                    '</div>' +
                    '<a href="../../frontend/UserProfile/withdrawal.php" class="chat-action-link">💰 Kifizetés oldal →</a>';
            }
        },
        {
            keywords: ['élő', 'live', 'élo meccs', 'élő meccs', 'folyamatban', 'most játszik'],
            answer: '⚽ <b>Élő meccsek</b><br><br>' +
                'Az éppen zajló meccseket az <a href="../../frontend/Live/live.php">Élő</a> menüben találod!<br><br>' +
                '• Valós idejű eredmények és élő oddsok<br>' +
                '• Kattints egy meccsre a piacok megtekintéséhez<br><br>' +
                '💡 Írd be <b>#élő</b> a jelenlegi élő meccsek számáért!'
        },
        {
            keywords: ['esport', 'e-sport', 'gaming', 'játék', 'csgo', 'lol', 'dota', 'valorant'],
            answer: '🎮 <b>eSport fogadás</b><br><br>' +
                'Az eSport meccseket az <a href="../../frontend/Esport/esport.php">eSport</a> oldalon találod!<br><br>' +
                '• e-Labdarúgás, e-Kosárlabda, e-Jégkorong<br>' +
                '• CS2, League of Legends, Dota 2, Valorant<br>' +
                '• Ugyanúgy fogadhatsz, mint a hagyományos sportokra!'
        },
        {
            keywords: ['regiszt', 'fiók', 'feliratkoz', 'account', 'létrehoz'],
            answer: '👤 <b>Regisztráció</b><br><br>' +
                '<div class="chat-steps">' +
                '<div class="chat-step"><span class="step-num">1</span> Kattints a <b>"Regisztráció"</b> gombra (jobb felső sarok)</div>' +
                '<div class="chat-step"><span class="step-num">2</span> Add meg a felhasználóneved, email, jelszó</div>' +
                '<div class="chat-step"><span class="step-num">3</span> Add meg a személyes adataidat</div>' +
                '<div class="chat-step"><span class="step-num">4</span> Fogadd el a feltételeket</div>' +
                '</div>' +
                '⚠️ 18 éven aluliak nem regisztrálhatnak.'
        },
        {
            keywords: ['jelszó', 'password', 'elfelejtett', 'bejelentkezés', 'login', 'belépés', 'nem tudok belépni'],
            answer: '🔐 <b>Bejelentkezés és jelszó</b><br><br>' +
                '• Belépés: jobb felső sarok → <b>"Bejelentkezés"</b><br>' +
                '• Elfelejtett jelszó? → <b>"Elfelejtett jelszó"</b> link<br>' +
                '• Jelszó módosítás: <a href="../../frontend/UserProfile/change_password.php">Jelszó módosítás</a><br><br>' +
                '🛡️ Használj legalább 8 karakter hosszú, erős jelszót!'
        },
        {
            keywords: ['adat', 'személyes', 'profil', 'telefonszám', 'cím', 'módosít'],
            answer: '📋 <b>Személyes adatok</b><br><br>' +
                '• Módosítható: név, telefon, ország, város, cím<br>' +
                '• Nem módosítható: felhasználónév, email, születési dátum<br><br>' +
                '<a href="../../frontend/UserProfile/personal_data.php" class="chat-action-link">📋 Személyes adatok →</a>'
        },
        {
            keywords: ['szelvény előzmény', 'előzmény', 'korábbi fogadás', 'ticket', 'korábbi', 'történet', 'nyertem', 'vesztettem'],
            answer: '📊 <b>Fogadási előzmények</b><br><br>' +
                'A Ticket panelen (jobb oldal) az <b>"Előzmények"</b> fülön láthatod a szelvényeidet.<br><br>' +
                '• ✅ WON – Nyertes • ❌ LOST – Vesztes • ⏳ OPEN – Függőben<br><br>' +
                '💡 Írd be <b>#szelvény</b> az utolsó 5 szelvényed összefoglalójáért!'
        },
        {
            keywords: ['tranzakció', 'pénzmozgás', 'befizetés előzmény'],
            answer: '🧾 <b>Tranzakciótörténet</b><br><br>' +
                'Minden pénzmozgásodat itt találod:<br>' +
                '<a href="../../frontend/UserProfile/transaction_history.php" class="chat-action-link">🧾 Tranzakciótörténet →</a>'
        },
        {
            keywords: ['segítség', 'help', 'támogatás', 'ügyfélszolgálat', 'kapcsolat', 'email', 'probléma'],
            answer: '📞 <b>Segítség és kapcsolat</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">📖</span><a href="../../frontend/Help/help.php">Segítség oldal</a></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">❓</span><a href="../../frontend/Help/GYIK.php">GYIK</a></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">📧</span><span>ugyfelszolgalat@betmatchbonus.com</span></div>' +
                '</div>'
        },
        {
            keywords: ['gyik', 'kérdés', 'faq', 'gyakran'],
            answer: '❓ A leggyakrabban feltett kérdéseket a <a href="../../frontend/Help/GYIK.php">GYIK</a> oldalon találod!'
        },
        {
            keywords: ['szabály', 'feltétel', 'részvétel', 'szabályzat'],
            answer: '📜 <b>Szabályzatok</b><br><br>' +
                '• <a href="../../frontend/Help/reszveteli-szabalyzat.php">Részvételi szabályzat</a><br>' +
                '• <a href="../../frontend/Help/sportszabalyok.php">Sportszabályok</a><br>' +
                '• <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php">Adatkezelési tájékoztató</a><br>' +
                '• <a href="../../frontend/Help/jatekosvedelem.php">Játékosvédelem</a>'
        },
        {
            keywords: ['adatkezel', 'adatvéd', 'gdpr', 'privacy', 'süti', 'cookie'],
            answer: '🔒 <b>Adatkezelés</b><br><br>' +
                'Részletes tájékoztató: <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php">Adatkezelési tájékoztató</a><br>' +
                'Az adataidat biztonságosan kezeljük, a sütiket az oldal működéséhez használjuk.'
        },
        {
            keywords: ['biztonság', 'védelem', 'játékosvéd', 'felelős', 'függőség', 'limit'],
            answer: '🛡️ <b>Játékosvédelem</b><br><br>' +
                'A felelős játék kiemelt fontosságú! Ha problémát tapasztalsz, kérj segítséget.<br>' +
                '<a href="../../frontend/Help/jatekosvedelem.php" class="chat-action-link">🛡️ Játékosvédelem →</a>'
        },
        {
            keywords: ['sport', 'labdarúgás', 'foci', 'kosárlabda', 'kézilabda', 'jégkorong', 'darts', 'pingpong', 'vízilabda'],
            answer: '🏆 <b>Elérhető sportágak</b><br><br>' +
                '<div class="chat-sports-grid">' +
                '<span>⚽ Labdarúgás</span><span>🏀 Kosárlabda</span>' +
                '<span>🏒 Jégkorong</span><span>🤾 Kézilabda</span>' +
                '<span>🎯 Darts</span><span>🤽 Vízilabda</span>' +
                '<span>🏓 Pingpong</span><span>🎮 eSport</span>' +
                '<span>⚾ Baseball</span><span>🏈 Amerikai foci</span>' +
                '<span>🏐 Röplabda</span><span>🥊 MMA</span>' +
                '</div>'
        },
        {
            keywords: ['milyen sport', 'mire fogad', 'sportágak', 'elérhető sport', 'fogadhatok sport'],
            answer: '🏆 <b>20+ sportág érhető el!</b><br><br>' +
                'A legnépszerűbbek: ⚽ Labdarúgás, 🏀 Kosárlabda, 🏒 Jégkorong, 🎯 Darts, 🎮 eSport<br><br>' +
                'A bal oldali menüben sportáganként böngészheted a meccseket.'
        },
        {
            keywords: ['1x2', 'piac', 'market', 'over', 'under', 'gólszám', 'hendikep', 'handicap', 'mindkét', 'btts', 'dupla esély'],
            answer: '📈 <b>Fogadási piacok</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">1X2</span><span>Hazai / Döntetlen / Vendég</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">O/U</span><span>Több/Kevesebb gól</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">BTTS</span><span>Mindkét csapat szerez gólt</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">DC</span><span>Dupla esély (1X, X2, 12)</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">HCP</span><span>Hendikep fogadás</span></div>' +
                '</div>'
        },
        {
            keywords: ['kötés', 'kombó', 'kombi', 'accumulator', 'acca', 'több meccs'],
            answer: '🔗 <b>Kötés (kombó) fogadás</b><br><br>' +
                'Több meccs tippjeit kombinálhatod egyetlen szelvényen!<br>' +
                '• Az oddsok összeszorzódnak → nagyobb nyeremény<br>' +
                '• Minden tippnek helyesnek kell lennie<br><br>' +
                '💡 Tipp: ne adj túl sok meccset egy szelvényre!'
        },
        {
            keywords: ['cashout', 'cash out', 'kiszáll', 'korán'],
            answer: '💸 <b>Cash Out</b><br><br>' +
                'A Cash Out funkcióval a szelvényedet a meccs vége előtt lezárhatod!<br><br>' +
                '• A Ticket → Előzmények panelen nyitott szelvényeknél elérhető<br>' +
                '• Az összeg az aktuális élő oddsok alapján számolódik<br>' +
                '• Nem elérhető: bónusz/freebet tét, Oddsűrhajó szelvény'
        },
        {
            keywords: ['oddsűrhajó', 'boosted', 'kiemelt odds', 'emelt odds'],
            answer: '🚀 <b>Oddsűrhajó</b><br><br>' +
                'Naponta egy kiemelt meccs emelt oddsokkal! A főoldalon megtalálod a kiemelt ajánlatot.<br><br>' +
                '⚠️ Oddsűrhajó szelvényre Cash Out nem érhető el.'
        },
        {
            keywords: ['panasz', 'reklamáció', 'reklamál'],
            answer: '📝 Panaszodat a <a href="../../frontend/Help/panaszkezeles.php">Panaszkezelés</a> oldalon nyújthatod be, vagy írj: <b>ugyfelszolgalat@betmatchbonus.com</b>'
        },
        {
            keywords: ['szótár', 'fogalom', 'kifejezés', 'mit jelent'],
            answer: '📖 Fogadási kifejezések: <a href="../../frontend/Help/szotar.php">Szótár</a>'
        },
        {
            keywords: ['fizetés', 'fizetési mód', 'visa', 'mastercard'],
            answer: '💳 Fizetési módok: Visa, Mastercard bankkártya + banki átutalás. Részletek: <a href="../../frontend/Help/fizetesi_lehetosegek.php">Fizetési lehetőségek</a>'
        },
        {
            keywords: ['új funkció', 'újdonság', 'update', 'frissítés'],
            answer: '🆕 Újdonságok: <a href="../../frontend/Help/uj_funkcio.php">Új funkciók</a> oldal'
        },
        {
            keywords: ['szia', 'hello', 'helló', 'hé', 'szervusz', 'üdv', 'hey', 'hi'],
            answer: '👋 <b>Üdvözöllek!</b> Én a <b>BMB Asszisztens</b> vagyok. Kérdezz bátran, vagy írd be <b>#parancsok</b> a funkciókért! 😊'
        },
        {
            keywords: ['kösz', 'köszön', 'thx', 'thanks', 'hálás'],
            answer: '😊 Szívesen! Ha bármi más kérdésed van, itt vagyok! 💜'
        },
        {
            keywords: ['napló', 'log', 'tevékenység', 'aktivitás'],
            answer: '📋 Tevékenységi napló: <a href="../../frontend/UserProfile/activity_log.php" class="chat-action-link">📋 Napló megtekintése →</a>'
        },
        {
            keywords: ['egyenleg', 'mennyi pénzem', 'balance', 'mennyim van'],
            answer: '💰 Az egyenleged lekérdezéséhez írd be: <b>#egyenleg</b>'
        },
        {
            keywords: ['napi tipp', 'tipp', 'ajánlás', 'mit fogadjak'],
            answer: '📊 <b>Napi tippek</b><br><br>' +
                'A főoldalon a <b>"Napi tippek"</b> szekcióban találsz ajánlásokat!<br>' +
                'Ezek mesterséges intelligencia alapú elemzésen alapulnak.'
        },
        {
            keywords: ['ki vagy', 'mi vagy', 'micsoda', 'robot', 'bot'],
            answer: '🤖 Én a <b>BMB Asszisztens</b> vagyok – a BetMatchBonus segítő chatbotja!<br><br>' +
                'Segítek eligazodni a fogadás, bónuszok, befizetés, és az oldal használatában.<br>' +
                'Írd be <b>#parancsok</b> a speciális funkciókért!'
        },
        {
            keywords: ['mit tudsz', 'mit csinál', 'mit tud', 'mit segít', 'milyen funkci'],
            answer: '🚀 <b>Amit tudok:</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">#egyenleg</span><span>Egyenleg lekérdezés</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#bónusz</span><span>Aktív bónuszaid</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#szelvény</span><span>Utolsó fogadásaid</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#élő</span><span>Élő meccsek száma</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#stat</span><span>Fogadási statisztikák</span></div>' +
                '</div>' +
                'Vagy kérdezz bármit szabad szöveggel! 😊'
        }
    ];

    // ===== ÁLLAPOT =====
    var isOpen = false;
    var chatHistory = [];
    var isTyping = false;
    var STORAGE_KEY = 'bmb_chat_history';
    var startupPopupHideTimer = null;

    // ===== LOCAL STORAGE =====
    function saveChat() {
        try {
            // Max 50 üzenet mentése
            var toSave = chatHistory.length > 50 ? chatHistory.slice(-50) : chatHistory;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(toSave));
        } catch (e) {}
    }

    function loadChat() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved) return JSON.parse(saved);
        } catch (e) {}
        return null;
    }

    function deleteSavedChat() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
    }

    // ===== DOM ELEMEK =====
    function getEl(id) { return document.getElementById(id); }

    // ===== FORMÁZÁS =====
    function fmtNum(n) { return n.toLocaleString('hu-HU'); }
    function fmtDate(d) {
        try { return new Date(d).toLocaleString('hu-HU', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }
        catch (e) { return d; }
    }

    // ===== ÜZENET LÉTREHOZÁSA =====
    function createMessageHTML(text, sender) {
        var iconClass = sender === 'bot' ? 'fa-robot' : 'fa-user';
        var time = new Date().toLocaleTimeString('hu-HU', { hour: '2-digit', minute: '2-digit' });
        return '<div class="chat-message ' + sender + '">' +
            '<div class="chat-msg-avatar"><i class="fas ' + iconClass + '"></i></div>' +
            '<div class="chat-msg-content">' +
            '<div class="chat-msg-bubble">' + text + '</div>' +
            '<span class="chat-msg-time">' + time + '</span>' +
            '</div></div>';
    }

    function createTypingHTML() {
        return '<div class="chat-message bot" id="typingMsg">' +
            '<div class="chat-msg-avatar"><i class="fas fa-robot"></i></div>' +
            '<div class="chat-msg-content"><div class="chat-msg-bubble">' +
            '<div class="typing-indicator"><span></span><span></span><span></span></div>' +
            '</div></div></div>';
    }

    // ===== ÜZENET HOZZÁADÁSA =====
    function addMessage(text, sender) {
        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl) return;
        messagesEl.insertAdjacentHTML('beforeend', createMessageHTML(text, sender));
        messagesEl.scrollTop = messagesEl.scrollHeight;
        chatHistory.push({ text: text, sender: sender });
        saveChat();
    }

    function addBotMessage(text) { addMessage(text, 'bot'); }

    // ===== GÉPELÉS ANIMÁCIÓ =====
    function showTyping() {
        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl) return;
        isTyping = true;
        messagesEl.insertAdjacentHTML('beforeend', createTypingHTML());
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function hideTyping() {
        var typingEl = getEl('typingMsg');
        if (typingEl) typingEl.remove();
        isTyping = false;
    }

    // ===== BOT VÁLASZ KÉSLELTETÉSSEL =====
    function botReply(text, delay) {
        showTyping();
        setTimeout(function () {
            hideTyping();
            addBotMessage(text);
        }, delay || (500 + Math.random() * 600));
    }

    // ===== INDULÓ POPUP =====
    function hideStartupPopup() {
        var popup = getEl('chatbotStartupPopup');
        if (!popup) return;
        popup.classList.remove('show');
        setTimeout(function () {
            if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
        }, 180);
    }

    function applyMobileFloatingOffsets() {
        var isMobileLayout = window.matchMedia('(max-width: 900px)').matches;
        var chatbotWindow = getEl('chatbotWindow');
        var chatbotToggle = getEl('chatbotToggle');
        var startupPopup = getEl('chatbotStartupPopup');
        var mobileBetslipFab = getEl('mobile-betslip-fab');
        var alignedBottom = '64px';

        if (mobileBetslipFab) {
            var fabBottom = window.getComputedStyle(mobileBetslipFab).bottom;
            if (fabBottom && fabBottom !== 'auto') alignedBottom = fabBottom;
        }

        if (isMobileLayout) {
            if (chatbotToggle) chatbotToggle.style.setProperty('bottom', 'calc(' + alignedBottom + ' + 54px)', 'important');
            if (startupPopup) startupPopup.style.setProperty('bottom', 'calc(' + alignedBottom + ' + 60px)', 'important');
            if (chatbotWindow) chatbotWindow.style.setProperty('bottom', 'calc(' + alignedBottom + ' + 136px)', 'important');
            return;
        }

        if (chatbotWindow) chatbotWindow.style.removeProperty('bottom');
        if (chatbotToggle) chatbotToggle.style.removeProperty('bottom');
        if (startupPopup) startupPopup.style.removeProperty('bottom');
    }

    function showStartupPopup() {
        if (isOpen || getEl('chatbotStartupPopup')) return;
        if (sessionStorage.getItem('bmb_popup_shown')) return;

        var popup = document.createElement('button');
        popup.type = 'button';
        popup.id = 'chatbotStartupPopup';
        popup.className = 'chatbot-startup-popup';
        popup.innerHTML = '<i class="fas fa-robot" style="margin-right:6px;"></i> Szia! BMB Asszisztens vagyok, valamiben segíthetek?';
        popup.setAttribute('aria-label', 'BMB Asszisztens üzenet');

        popup.addEventListener('click', function () {
            if (startupPopupHideTimer) { clearTimeout(startupPopupHideTimer); startupPopupHideTimer = null; }
            hideStartupPopup();
            openChat();
        });

        document.body.appendChild(popup);
        applyMobileFloatingOffsets();
        requestAnimationFrame(function () { popup.classList.add('show'); });
        sessionStorage.setItem('bmb_popup_shown', '1');
        startupPopupHideTimer = setTimeout(hideStartupPopup, 7000);
    }

    // ===== INTERAKTÍV PARANCSOK — BACKEND FETCH =====
    function fetchFromBackend(action, callback) {
        fetch('../../backend/ApiRequest/chatbot_data.php?action=' + action)
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function () {
                hideTyping();
                addBotMessage('⚠️ Hiba történt az adatok lekérésekor. Próbáld újra!');
            });
    }

    function fetchBalance() {
        showTyping();
        fetchFromBackend('balance', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Az egyenleg megtekintéséhez be kell jelentkezned!');
                return;
            }
            addBotMessage(
                '💰 <b>Egyenleged</b><br><br>' +
                '<div class="chat-info-card chat-balance-card">' +
                '<div class="chat-balance-main">' + fmtNum(data.balance) + ' <small>Ft</small></div>' +
                (data.bonusBalance > 0 ? '<div class="chat-balance-bonus">+ ' + fmtNum(data.bonusBalance) + ' Ft bónusz egyenleg</div>' : '') +
                '</div>'
            );
        });
    }

    function fetchBonuses() {
        showTyping();
        fetchFromBackend('bonuses', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Jelentkezz be a bónuszaid megtekintéséhez!');
                return;
            }
            if (!data.activeBonuses || data.activeBonuses.length === 0) {
                addBotMessage('🎁 Jelenleg nincs aktív bónuszod. Nézd meg az ajánlatokat: <a href="../../frontend/Bonus/bonus.php">Bónuszok</a>');
                return;
            }
            var html = '🎁 <b>Aktív bónuszaid</b><br><br>';
            data.activeBonuses.forEach(function (b) {
                var pct = b.wageringRequired > 0 ? Math.min(100, Math.round(b.wageringProgress / b.wageringRequired * 100)) : 100;
                html += '<div class="chat-bonus-item">' +
                    '<div class="chat-bonus-name">' + b.name + ' <span class="chat-badge chat-badge-' + b.status.toLowerCase() + '">' + b.status + '</span></div>' +
                    (b.balance > 0 ? '<div class="chat-bonus-balance">' + fmtNum(b.balance) + ' Ft</div>' : '') +
                    (b.wageringRequired > 0 ?
                        '<div class="chat-progress-wrap"><div class="chat-progress-bar" style="width:' + pct + '%"></div></div>' +
                        '<div class="chat-progress-text">Forgatás: ' + fmtNum(b.wageringProgress) + ' / ' + fmtNum(b.wageringRequired) + ' Ft (' + pct + '%)</div>' : '') +
                    '</div>';
            });
            addBotMessage(html);
        });
    }

    function fetchHistory() {
        showTyping();
        fetchFromBackend('history', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Jelentkezz be a szelvényeid megtekintéséhez!');
                return;
            }
            if (!data.recentTickets || data.recentTickets.length === 0) {
                addBotMessage('📊 Még nincs fogadásod. Próbálj ki egy meccset a <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a>!');
                return;
            }
            var icons = { WON: '✅', LOST: '❌', OPEN: '⏳', CASHOUT: '💰' };
            var html = '📊 <b>Utolsó szelvényeid</b><br><br>';
            data.recentTickets.forEach(function (t) {
                html += '<div class="chat-ticket-item">' +
                    '<div class="chat-ticket-header">' +
                    '<span>' + (icons[t.status] || '❓') + ' #' + t.id + '</span>' +
                    '<span class="chat-ticket-date">' + fmtDate(t.date) + '</span>' +
                    '</div>' +
                    '<div class="chat-ticket-details">' +
                    '<span>Tét: <b>' + fmtNum(t.stake) + ' Ft</b></span>' +
                    '<span>Odds: ' + t.odds.toFixed(2) + '</span>' +
                    '<span>' + (t.status === 'CASHOUT' ? 'Cash Out: <b>' + fmtNum(t.cashout) + ' Ft</b>' :
                        (t.status === 'WON' ? 'Nyeremény: <b>' + fmtNum(t.potentialWin) + ' Ft</b>' :
                        'Pot.: ' + fmtNum(t.potentialWin) + ' Ft')) + '</span>' +
                    '</div></div>';
            });
            addBotMessage(html);
        });
    }

    function fetchStats() {
        showTyping();
        fetchFromBackend('history', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Jelentkezz be a statisztikák megtekintéséhez!');
                return;
            }
            var s = data.ticketStats || {};
            var winRate = s.total > 0 ? Math.round(s.won / s.total * 100) : 0;
            addBotMessage(
                '📊 <b>Fogadási statisztikáid</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">Összes</span><span><b>' + s.total + '</b> szelvény</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">✅ Nyertes</span><span>' + s.won + '</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">❌ Vesztes</span><span>' + s.lost + '</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">⏳ Nyitott</span><span>' + s.open + '</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">🎯 Arány</span><span><b>' + winRate + '%</b> nyerési arány</span></div>' +
                '</div>'
            );
        });
    }

    function fetchLive() {
        showTyping();
        fetchFromBackend('live', function (data) {
            hideTyping();
            if (!data.liveMatches || data.liveMatches.length === 0) {
                addBotMessage('⚽ Jelenleg nincs élő meccs. Nézz vissza később!');
                return;
            }
            var html = '🔴 <b>Élő meccsek most: ' + data.totalLive + '</b><br><br>' +
                '<div class="chat-info-card">';
            data.liveMatches.forEach(function (m) {
                html += '<div class="chat-info-row"><span class="chat-info-label">' + m.sport + '</span><span><b>' + m.count + '</b> meccs</span></div>';
            });
            html += '</div>' +
                '<a href="../../frontend/Live/live.php" class="chat-action-link">🔴 Élő meccsek megtekintése →</a>';
            addBotMessage(html);
        });
    }

    function fetchSummary() {
        showTyping();
        fetchFromBackend('summary', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Jelentkezz be az összegzéshez!');
                return;
            }
            var html = '📋 <b>Összegzés</b><br><br>';
            html += '<div class="chat-info-card">';
            html += '<div class="chat-info-row"><span class="chat-info-label">💰 Egyenleg</span><span><b>' + fmtNum(data.balance) + ' Ft</b></span></div>';
            if (data.bonusBalance > 0)
                html += '<div class="chat-info-row"><span class="chat-info-label">🎁 Bónusz</span><span>' + fmtNum(data.bonusBalance) + ' Ft</span></div>';
            var s = data.ticketStats || {};
            html += '<div class="chat-info-row"><span class="chat-info-label">📊 Szelvények</span><span>' + s.total + ' (✅' + s.won + ' ❌' + s.lost + ' ⏳' + s.open + ')</span></div>';
            if (data.activeBonuses && data.activeBonuses.length > 0)
                html += '<div class="chat-info-row"><span class="chat-info-label">🎁 Aktív bónusz</span><span>' + data.activeBonuses.length + ' db</span></div>';
            html += '<div class="chat-info-row"><span class="chat-info-label">🔴 Élő meccs</span><span>' + (data.totalLive || 0) + '</span></div>';
            html += '</div>';
            addBotMessage(html);
        });
    }

    function showCommands() {
        addBotMessage(
            '⌨️ <b>Elérhető parancsok</b><br><br>' +
            '<div class="chat-commands-grid">' +
            '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#egyenleg\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#egyenleg</span><span>Egyenleged</span></div>' +
            '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#bónusz\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#bónusz</span><span>Aktív bónuszok</span></div>' +
            '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#szelvény\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#szelvény</span><span>Utolsó fogadások</span></div>' +
            '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#élő\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#élő</span><span>Élő meccsek</span></div>' +
            '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#stat\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#stat</span><span>Statisztikák</span></div>' +
            '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#összegzés\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#összegzés</span><span>Teljes összegzés</span></div>' +
            '</div>'
        );
    }

    // ===== VÁLASZ KERESÉSE =====
    function findAnswer(question) {
        var q = question.toLowerCase().replace(/[?!.,;:'"]/g, '').replace(/\s+/g, ' ').trim();
        var bestMatch = null;
        var bestScore = 0;

        for (var i = 0; i < knowledgeBase.length; i++) {
            var entry = knowledgeBase[i];
            var score = 0;
            for (var j = 0; j < entry.keywords.length; j++) {
                var keyword = entry.keywords[j].toLowerCase();
                if (q === keyword) { score += 10; }
                else if (q.indexOf(keyword) !== -1) { score += keyword.length >= 4 ? 5 : 3; }
                else {
                    var words = q.split(' ');
                    for (var w = 0; w < words.length; w++) {
                        if (words[w].length >= 4 && keyword.indexOf(words[w]) !== -1) { score += 2; break; }
                    }
                }
            }
            if (score > bestScore) { bestScore = score; bestMatch = entry; }
        }
        if (bestScore >= 2 && bestMatch) return bestMatch.answer;
        return null;
    }

    // ===== FALLBACK =====
    function getFallbackAnswer() {
        var fallbacks = [
            '🤔 Erre nem tudok pontos választ adni. Próbáld meg másképp, vagy írd be <b>#parancsok</b>!',
            '😅 Ezt nem teljesen értem. Kérdezz a fogadásról, bónuszokról, vagy írd be pl.: <b>#egyenleg</b>',
            '🤷 Erről nincs információm. Nézd meg a <a href="../../frontend/Help/GYIK.php">GYIK</a>-et, vagy írd be <b>#help</b>!',
            '💡 Nem találtam választ. Próbáld: <b>#parancsok</b> a funkciók listájáért!'
        ];
        return fallbacks[Math.floor(Math.random() * fallbacks.length)];
    }

    // ===== FELHASZNÁLÓI ÜZENET KEZELÉSE =====
    function handleUserMessage(text) {
        if (!text || text.trim() === '' || isTyping) return;
        text = text.trim();
        addMessage(text, 'user');

        var input = getEl('chatbotInput');
        if (input) input.value = '';

        // Parancs ellenőrzés
        var cmd = text.toLowerCase().replace(/\s+/g, '');
        if (COMMANDS[cmd]) {
            COMMANDS[cmd]();
            return;
        }

        // Tudásbázis keresés
        showTyping();
        var delay = 500 + Math.random() * 600;
        setTimeout(function () {
            hideTyping();
            var answer = findAnswer(text);
            if (!answer) answer = getFallbackAnswer();
            addBotMessage(answer);
        }, delay);
    }

    // ===== ABLAK NYITÁS / ZÁRÁS =====
    function openChat() {
        var win = getEl('chatbotWindow');
        var toggle = getEl('chatbotToggle');
        var notif = getEl('chatbotNotification');
        if (!win || !toggle) return;

        if (startupPopupHideTimer) { clearTimeout(startupPopupHideTimer); startupPopupHideTimer = null; }
        hideStartupPopup();

        win.classList.remove('closing');
        win.classList.add('open');
        toggle.classList.add('active');
        isOpen = true;

        if (notif) notif.classList.add('hidden');

        // Első megnyitás: üdvözlő üzenet + kontextus tipp
        if (chatHistory.length === 0) {
            setTimeout(function () {
                addBotMessage(
                    '👋 <b>Üdvözöllek a BetMatchBonus-on!</b><br><br>' +
                    'Én vagyok a <b>BMB Asszisztens</b> – segítek eligazodni!<br><br>' +
                    'Kérdezz bátran, vagy írd be <b>#parancsok</b> a funkcióimért! 👇'
                );
                // Kontextus-specifikus tipp
                var tip = contextTips[pageContext];
                if (tip) {
                    setTimeout(function () { botReply(tip, 800); }, 1200);
                }
            }, 300);
        }

        setTimeout(function () {
            var inp = getEl('chatbotInput');
            if (inp) inp.focus();
        }, 400);
    }

    function closeChat() {
        var win = getEl('chatbotWindow');
        var toggle = getEl('chatbotToggle');
        if (!win || !toggle) return;
        win.classList.add('closing');
        setTimeout(function () { win.classList.remove('open', 'closing'); }, 250);
        toggle.classList.remove('active');
        isOpen = false;
    }

    function toggleChat() { isOpen ? closeChat() : openChat(); }

    // ===== BESZÉLGETÉS TÖRLÉSE =====
    function clearChat() {
        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl) return;
        messagesEl.innerHTML = '';
        chatHistory = [];
        deleteSavedChat();
        var suggestions = getEl('chatbotSuggestions');
        if (suggestions) suggestions.style.display = 'flex';
        setTimeout(function () {
            addBotMessage('🔄 Beszélgetés törölve! Miben segíthetek? 😊');
        }, 300);
    }

    // ===== CHAT VISSZAÁLLÍTÁS =====
    function restoreChat() {
        var saved = loadChat();
        if (!saved || saved.length === 0) return false;
        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl) return false;
        var notif = getEl('chatbotNotification');
        if (notif) notif.classList.add('hidden');
        for (var i = 0; i < saved.length; i++) {
            messagesEl.insertAdjacentHTML('beforeend', createMessageHTML(saved[i].text, saved[i].sender));
        }
        chatHistory = saved;
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return true;
    }

    // ===== INICIALIZÁLÁS =====
    function init() {
        restoreChat();

        applyMobileFloatingOffsets();
        window.addEventListener('resize', applyMobileFloatingOffsets);
        window.addEventListener('orientationchange', applyMobileFloatingOffsets);

        var toggleBtn = getEl('chatbotToggle');
        if (toggleBtn) toggleBtn.addEventListener('click', toggleChat);

        var closeBtn = getEl('chatbotClose');
        if (closeBtn) closeBtn.addEventListener('click', closeChat);

        var clearBtn = getEl('chatbotClear');
        if (clearBtn) clearBtn.addEventListener('click', clearChat);

        var sendBtn = getEl('chatbotSend');
        if (sendBtn) {
            sendBtn.addEventListener('click', function () {
                var input = getEl('chatbotInput');
                if (input) handleUserMessage(input.value);
            });
        }

        var inputEl = getEl('chatbotInput');
        if (inputEl) {
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleUserMessage(this.value);
                }
            });
        }

        var suggestionBtns = document.querySelectorAll('.chatbot-suggestion');
        for (var i = 0; i < suggestionBtns.length; i++) {
            suggestionBtns[i].addEventListener('click', function () {
                var question = this.getAttribute('data-question');
                if (question) {
                    if (!isOpen) openChat();
                    setTimeout(function () { handleUserMessage(question); }, 350);
                }
            });
        }

        setTimeout(showStartupPopup, 900);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
