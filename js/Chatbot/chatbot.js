/**
 * BetMatchBonus - AI Chatbot (BMB Asszisztens)
 * 
 * Előre definiált tudásbázisból válaszol a felhasználók kérdéseire.
 * Kulcsszó-alapú keresés, magyar nyelven.
 */

(function () {
    'use strict';

    // ===== TUDÁSBÁZIS =====
    var knowledgeBase = [
        {
            keywords: ['fogad', 'tippel', 'odds', 'szelvény', 'ticket', 'hogyan fogad', 'fogadás', 'tét'],
            answer: '🎯 <b>Hogyan fogadhatsz?</b><br><br>' +
                '1. Válassz egy meccsre a <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a> vagy az <a href="../../frontend/Live/live.php">Élő</a> menüben.<br>' +
                '2. Kattints a meccsre, és válassz egy piacot (pl. 1X2, Gólszám).<br>' +
                '3. Kattints az oddsra – a tételek a jobb oldali <b>Ticket</b> sávban jelennek meg.<br>' +
                '4. Add meg a téted összegét (min. 100 Ft).<br>' +
                '5. Kattints a <b>"Ticket leadása"</b> gombra!<br><br>' +
                'Egyes és kötés (kombó) fogadásra is van lehetőséged. 🍀'
        },
        {
            keywords: ['bónusz', 'bonus', 'promó', 'promo', 'akció', 'ajánlat', 'kód', 'kupón', 'ingyenes'],
            answer: '🎁 <b>Bónuszok és promóciók</b><br><br>' +
                'Az aktuális bónuszainkat a <a href="../../frontend/Bonus/bonus.php">Bónuszok</a> oldalon találod!<br><br>' +
                '• <b>Üdvözlő bónusz</b> – regisztrációkor automatikusan jóváírjuk.<br>' +
                '• <b>Befizetési bónusz</b> – az első befizetésed után plusz egyenleget kapsz.<br>' +
                '• <b>Promóciós kódok</b> – a Profilod → <a href="../../frontend/UserProfile/my_bonuses.php">Bónuszaim</a> menüben válthatod be.<br><br>' +
                'Nézz vissza rendszeresen, mert folyamatosan újulnak az ajánlataink! 🚀'
        },
        {
            keywords: ['befizet', 'deposit', 'feltölt', 'egyenleg', 'pénz', 'bankkártya', 'átutalás', 'hogyan fizet', 'fizethetek be', 'fizetek be', 'befizetés'],
            get answer() {
                var minDep = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_deposit) || 3000;
                return '💳 <b>Befizetés</b><br><br>' +
                'A befizetéshez navigálj a <a href="../../frontend/UserProfile/deposit.php">Befizetés</a> oldalra (Profil → Befizetés).<br><br>' +
                '• <b>Bankkártya</b> (Visa, Mastercard) – azonnal jóváírásra kerül.<br>' +
                '• <b>Banki átutalás</b> – szintén azonnal feldolgozzuk.<br>' +
                '• Minimális befizetés: <b>' + minDep.toLocaleString('hu-HU') + ' Ft</b>.<br>' +
                '• Gyors összeg gombok: 5 000 / 7 500 / 10 000 / 20 000 Ft.<br><br>' +
                'A befizetés azonnali – azonnal fogadhatsz utána! ⚡';
            }
        },
        {
            keywords: ['kifizet', 'withdrawal', 'kivét', 'nyeremény', 'pénzfelvét', 'kifizetés', 'kiutal', 'kérhetek kifizet', 'pénzt felvenni'],
            get answer() {
                var minW = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_withdrawal) || 6000;
                return '💰 <b>Kifizetés</b><br><br>' +
                'A kifizetés a <a href="../../frontend/UserProfile/withdrawal.php">Kifizetés</a> oldalon (Profil → Kifizetés) kérhető.<br><br>' +
                '• Kifizetési mód: <b>banki átutalás</b>.<br>' +
                '• Minimális összeg: <b>' + minW.toLocaleString('hu-HU') + ' Ft</b>.<br>' +
                '• Szükséges megadni a számlán szereplő nevet és bankszámlaszámot.<br>' +
                '• Feldolgozási idő: <b>1–3 munkanap</b>.<br><br>' +
                'A kifizetés a saját nevedre szóló bankszámlára történik. 🏦';
            }
        },
        {
            keywords: ['élő', 'live', 'élo meccs', 'élő meccs', 'folyamatban', 'most játszik'],
            answer: '⚽ <b>Élő meccsek</b><br><br>' +
                'Az éppen zajló meccseket az <a href="../../frontend/Live/live.php">Élő</a> menüben találod!<br><br>' +
                '• Sportágak: ⚽ Labdarúgás, 🏀 Kosárlabda, 🎯 Darts, 🤽 Vízilabda, 🤾 Kézilabda, 🏒 Jégkorong, 🏓 Pingpong<br>' +
                '• Valós idejű eredmények és élő oddsok.<br>' +
                '• Kattints egy meccsre a részletes piacok megtekintéséhez!<br><br>' +
                'Az élő meccsek adatai automatikusan frissülnek. 🔴'
        },
        {
            keywords: ['esport', 'e-sport', 'gaming', 'játék', 'csgo', 'lol', 'dota', 'valorant'],
            answer: '🎮 <b>eSport fogadás</b><br><br>' +
                'Az eSport meccseket az <a href="../../frontend/Esport/esport.php">eSport</a> oldalon találod!<br><br>' +
                '• Mai eSport meccsek és élő eSport események.<br>' +
                '• Ugyanúgy fogadhatsz rájuk, mint a hagyományos sportokra.<br>' +
                '• Népszerű játékok: CS2, League of Legends, Dota 2, Valorant stb.<br><br>' +
                'Az eSport világa folyamatosan bővül! 🕹️'
        },
        {
            keywords: ['regiszt', 'fiók', 'feliratkoz', 'account', 'profil', 'létrehoz'],
            answer: '👤 <b>Regisztráció</b><br><br>' +
                '1. Kattints a jobb felső sarokban lévő <b>"Regisztráció"</b> gombra.<br>' +
                '2. Add meg a felhasználóneved, email címed és jelszavad.<br>' +
                '3. A második lépésben add meg a személyes adataidat és a születési dátumod.<br>' +
                '4. Fogadd el a feltételeket, és kész!<br><br>' +
                '⚠️ 18 éven aluliak nem regisztrálhatnak. A regisztráció után egyenleged automatikusan 0 Ft-ról indul.'
        },
        {
            keywords: ['jelszó', 'password', 'elfelejtett', 'bejelentkezés', 'login', 'belépés', 'nem tudok belépni'],
            answer: '🔐 <b>Bejelentkezés és jelszó</b><br><br>' +
                '• A bejelentkezés a jobb felső sarokban található gombbal lehetséges.<br>' +
                '• Ha elfelejtetted a jelszavad, kattints az <b>"Elfelejtett jelszó"</b> linkre a bejelentkezési ablakon belül.<br>' +
                '• Ha be vagy jelentkezve, a jelszavadat megváltoztathatod: <a href="../../frontend/UserProfile/change_password.php">Jelszó módosítás</a>.<br><br>' +
                'Biztonsági tipp: használj legalább 8 karakter hosszú, erős jelszót! 🛡️'
        },
        {
            keywords: ['adat', 'személyes', 'profil', 'telefonszám', 'cím', 'módosít'],
            answer: '📋 <b>Személyes adatok</b><br><br>' +
                'Az adataidat a <a href="../../frontend/UserProfile/personal_data.php">Személyes Adatok</a> oldalon tekintheted meg és módosíthatod.<br><br>' +
                '• Módosítható: teljes név, telefon, ország, város, irányítószám, cím.<br>' +
                '• <b>Nem módosítható:</b> felhasználónév, email, születési dátum.<br><br>' +
                'Kérjük, mindig valós adatokat adj meg! 📝'
        },
        {
            keywords: ['szelvény', 'előzmény', 'fogadás', 'ticket', 'korábbi', 'történet', 'nyertem', 'vesztettem'],
            answer: '📊 <b>Fogadási előzmények</b><br><br>' +
                'A Ticket panelen (jobb oldali sáv) az <b>"Előzmények"</b> fülön láthatod a korábbi szelvényeidet.<br><br>' +
                '• 🟢 <b>WON</b> – Nyertes szelvény (a nyeremény automatikusan jóváíródik).<br>' +
                '• 🔴 <b>LOST</b> – Vesztes szelvény.<br>' +
                '• 🟡 <b>OPEN</b> – Még nem értékelt szelvény (meccs folyamatban).<br><br>' +
                'A rendszer automatikusan kiértékeli a szelvényeket, amint a meccsek véget érnek. ⏱️'
        },
        {
            keywords: ['tranzakció', 'átutalás', 'pénzmozgás', 'befizetés előzmény'],
            answer: '🧾 <b>Tranzakciótörténet</b><br><br>' +
                'Az összes pénzmozgásodat (befizetések, kifizetések) a <a href="../../frontend/UserProfile/transaction_history.php">Tranzakciótörténet</a> oldalon láthatod.<br><br>' +
                'Minden tranzakciónak egyedi azonosítója van.'
        },
        {
            keywords: ['segítség', 'help', 'támogatás', 'ügyfélszolgálat', 'kapcsolat', 'email', 'probléma'],
            answer: '📞 <b>Segítség és kapcsolat</b><br><br>' +
                'Ha további segítségre van szükséged:<br><br>' +
                '• <a href="../../frontend/Help/help.php">Segítség</a> oldal – átfogó információk.<br>' +
                '• <a href="../../frontend/Help/GYIK.php">GYIK</a> – gyakran ismételt kérdések.<br>' +
                '• <a href="../../frontend/Help/kapcsolat.php">Kapcsolat</a> – elérhetőségeink.<br>' +
                '• Email: <b>ugyfelszolgalat@betmatchbonus.com</b><br><br>' +
                'Csapatunk készségesen áll rendelkezésedre! 💬'
        },
        {
            keywords: ['gyik', 'kérdés', 'faq', 'gyakran'],
            answer: '❓ <b>Gyakran Ismételt Kérdések</b><br><br>' +
                'A leggyakrabban feltett kérdéseket és válaszokat a <a href="../../frontend/Help/GYIK.php">GYIK</a> oldalon találod!<br><br>' +
                'Ha nem találod a választ, írj nekem bátran! 😊'
        },
        {
            keywords: ['szabály', 'feltétel', 'részvétel', 'szabályzat'],
            answer: '📜 <b>Szabályzatok</b><br><br>' +
                '• <a href="../../frontend/Help/reszveteli-szabalyzat.php">Részvételi szabályzat</a><br>' +
                '• <a href="../../frontend/Help/sportszabalyok.php">Sportszabályok</a><br>' +
                '• <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php">Adatkezelési tájékoztató</a><br>' +
                '• <a href="../../frontend/Help/jatekosvedelem.php">Játékosvédelem</a><br><br>' +
                'Kérjük, a fogadás előtt ismerkedj meg a szabályzatainkkal!'
        },
        {
            keywords: ['adatkezel', 'adatvéd', 'gdpr', 'privacy', 'süti', 'cookie'],
            answer: '🔒 <b>Adatkezelés és sütik</b><br><br>' +
                'Részletes tájékoztatónkat itt találod: <a href="../../frontend/Help/adatkezelesi_tajekoztatók.php">Adatkezelési tájékoztatók</a>.<br><br>' +
                '• Személyes adataidat biztonságosan kezeljük.<br>' +
                '• A sütiket a weboldal működéséhez használjuk.<br>' +
                '• Az oldal első látogatásakor a süti-hozzájárulási sávban választhatsz.'
        },
        {
            keywords: ['biztonság', 'védelem', 'játékosvéd', 'felelős', 'függőség', 'limit'],
            answer: '🛡️ <b>Játékosvédelem</b><br><br>' +
                'A felelős játék számunkra kiemelt fontosságú!<br><br>' +
                '• <a href="../../frontend/Help/jatekosvedelem.php">Játékosvédelmi</a> információk.<br>' +
                '• Ha úgy érzed, a fogadás problémát okoz, kérj segítséget!<br>' +
                '• 18 éven aluliak számára a fogadás tilos.<br><br>' +
                'Fogadj felelősen! ⚠️'
        },
        {
            keywords: ['sport', 'labdarúgás', 'foci', 'kosárlabda', 'kézilabda', 'jégkorong', 'darts', 'pingpong', 'vízilabda'],
            answer: '🏆 <b>Elérhető sportágak</b><br><br>' +
                '• ⚽ Labdarúgás<br>' +
                '• 🏀 Kosárlabda<br>' +
                '• 🎯 Darts<br>' +
                '• 🤽 Vízilabda<br>' +
                '• 🤾 Kézilabda<br>' +
                '• 🏒 Jégkorong<br>' +
                '• 🏓 Pingpong<br>' +
                '• 🎮 eSport<br><br>' +
                'A bal oldali menüben sportáganként böngészheted a meccseket! A <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a> a mai nap meccseit láthatod.'
        },
        {
            keywords: ['milyen sport', 'mire fogad', 'sportok', 'sportágak', 'milyen sportág', 'elérhető sport', 'sport kínálat', 'hány sport', 'mit fogad', 'fogadhatok sport'],
            answer: '🏆 <b>Milyen sportokra fogadhatsz?</b><br><br>' +
                'Az oldalunkon az alábbi sportágakra tudsz fogadni:<br><br>' +
                '⚽ <b>Labdarúgás</b> – a legnépszerűbb sportág, rengeteg bajnoksággal<br>' +
                '🏀 <b>Kosárlabda</b> – NBA, Euroliga és még sok más<br>' +
                '🏒 <b>Jégkorong</b> – NHL, KHL, hazai és nemzetközi ligák<br>' +
                '🤾 <b>Kézilabda</b> – BL, EHF és magyar bajnokság<br>' +
                '🎯 <b>Darts</b> – PDC, WDF versenyek<br>' +
                '🤽 <b>Vízilabda</b> – OB, BL, nemzetközi tornák<br>' +
                '🏓 <b>Pingpong</b> – WTT, nemzetközi versenyek<br>' +
                '⚾ <b>Baseball</b> – MLB és más ligák<br>' +
                '🏈 <b>Amerikai foci</b> – NFL<br>' +
                '🏐 <b>Röplabda</b> – hazai és nemzetközi<br>' +
                '⛳ <b>Golf</b> – PGA, European Tour<br>' +
                '🥊 <b>MMA</b> – UFC, Bellator<br>' +
                '🚴 <b>Kerékpár</b> – Tour de France és más versenyek<br>' +
                '⛷️ <b>Síelés</b> – alpesi és északi számok<br>' +
                '🏸 <b>Badminton</b> – BWF versenyek<br>' +
                '♟️ <b>Sakk</b> – nemzetközi tornák<br>' +
                '🏖️ <b>Strandröplabda</b> – FIVB<br>' +
                '🏏 <b>Krikett</b> – nemzetközi mérkőzések<br>' +
                '🎱 <b>Snooker</b> – World Snooker Tour<br>' +
                '🎮 <b>eSport</b> – e-Labdarúgás, e-Kosárlabda, e-Jégkorong<br><br>' +
                'A sportágakat a <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a> a bal oldali menüben találod, az élő meccseket pedig az <a href="../../frontend/Live/live.php">Élő</a> menüben! 🔥'
        },
        {
            keywords: ['1x2', 'piac', 'market', 'over', 'under', 'gólszám', 'hendikep', 'handicap', 'mindkét', 'btts', 'dupla esély'],
            answer: '📈 <b>Fogadási piacok</b><br><br>' +
                '• <b>1X2</b> – Hazai győzelem / Döntetlen / Vendég győzelem.<br>' +
                '• <b>Több/Kevesebb (Over/Under)</b> – Gólszám felett vagy alatt.<br>' +
                '• <b>Mindkét csapat szerez gólt (BTTS)</b> – Igen/Nem.<br>' +
                '• <b>Dupla esély</b> – 1X, X2, 12.<br>' +
                '• <b>Hendikep</b> – Előny/hátrány fogadás.<br>' +
                '• <b>Páros/Páratlan</b> – Össz gólszám típusa.<br><br>' +
                'A piacok a meccs részleteinél jelennek meg, ha rákattintasz egy meccsre. ⚡'
        },
        {
            keywords: ['kötés', 'kombó', 'kombi', 'accumulator', 'acca', 'több meccs'],
            answer: '🔗 <b>Kötés (kombó) fogadás</b><br><br>' +
                'A Ticket panelen a <b>"Kötés"</b> fülre váltva kombinálhatod több meccs tippjeit egyetlen szelvényen!<br><br>' +
                '• Több meccset válassz ki – az oddsok összeszorzódnak.<br>' +
                '• Csak akkor nyersz, ha <b>minden</b> tipped helyes.<br>' +
                '• A magasabb odds nagyobb nyereményt jelent, de nehezebb eltalálni.<br><br>' +
                'Tipp: ne adj túl sok meccset egy szelvényre! 🍀'
        },
        {
            keywords: ['panasz', 'reklamáció', 'reklamál'],
            answer: '📝 <b>Panaszkezelés</b><br><br>' +
                'Panaszodat a <a href="../../frontend/Help/panaszkezeles.php">Panaszkezelés</a> oldalon találod a részletes eljárásrendet.<br><br>' +
                'Írhatsz nekünk: <b>ugyfelszolgalat@betmatchbonus.com</b>'
        },
        {
            keywords: ['szótár', 'fogalom', 'kifejezés', 'mit jelent'],
            answer: '📖 <b>Fogadási szótár</b><br><br>' +
                'Ha egy fogadási kifejezés nem ismert, nézd meg a <a href="../../frontend/Help/szotar.php">Szótár</a> oldalunkat!<br><br>' +
                'Ott megtalálod az összes fontos fogadási kifejezés magyarázatát.'
        },
        {
            keywords: ['fizetés', 'fizetési mód', 'milyen módon', 'visa', 'mastercard'],
            answer: '💳 <b>Fizetési lehetőségek</b><br><br>' +
                'Részletes tájékoztatót a <a href="../../frontend/Help/fizetesi_lehetosegek.php">Fizetési lehetőségek</a> oldalon találsz.<br><br>' +
                '• Bankkártya (Visa, Mastercard)<br>' +
                '• Banki átutalás<br><br>' +
                'Minden befizetés azonnali!'
        },
        {
            keywords: ['új funkció', 'újdonság', 'update', 'frissítés'],
            answer: '🆕 <b>Újdonságok</b><br><br>' +
                'A legfrissebb funkciókról és fejlesztésekről az <a href="../../frontend/Help/uj_funkcio.php">Új funkciók</a> oldalon olvashatsz!'
        },
        {
            keywords: ['szia', 'hello', 'helló', 'hé', 'szervusz', 'üdv', 'hey', 'hi'],
            answer: '👋 <b>Üdvözöllek!</b><br><br>' +
                'Szia! Én vagyok a <b>BMB Asszisztens</b>, a BetMatchBonus segítő chatbotja.<br><br>' +
                'Kérdezz bátran bármit a fogadásról, bónuszokról, befizetésről, vagy a weboldal használatáról! 😊'
        },
        {
            keywords: ['kösz', 'köszön', 'thx', 'thanks', 'hálás'],
            answer: '😊 Szívesen! Ha bármi más kérdésed van, nyugodtan kérdezz! Mindig itt vagyok. 💜'
        },
        {
            keywords: ['napló', 'log', 'tevékenység', 'aktivitás'],
            answer: '📋 <b>Tevékenységi napló</b><br><br>' +
                'A <a href="../../frontend/UserProfile/activity_log.php">Napló</a> oldalon (Profil → Napló) megtekintheted a fiókod teljes tevékenységi történetét: bejelentkezések, fogadások, befizetések stb.'
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
            localStorage.setItem(STORAGE_KEY, JSON.stringify(chatHistory));
        } catch (e) { /* quota exceeded or private mode */ }
    }

    function loadChat() {
        try {
            var saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                return JSON.parse(saved);
            }
        } catch (e) { /* parse error */ }
        return null;
    }

    function deleteSavedChat() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) { /* ignore */ }
    }

    // ===== DOM ELEMEK =====
    function getEl(id) { return document.getElementById(id); }

    // ===== ÜZENET LÉTREHOZÁSA =====
    function createMessageHTML(text, sender) {
        var iconClass = sender === 'bot' ? 'fa-robot' : 'fa-user';
        return '<div class="chat-message ' + sender + '">' +
            '<div class="chat-msg-avatar"><i class="fas ' + iconClass + '"></i></div>' +
            '<div class="chat-msg-bubble">' + text + '</div>' +
            '</div>';
    }

    function createTypingHTML() {
        return '<div class="chat-message bot" id="typingMsg">' +
            '<div class="chat-msg-avatar"><i class="fas fa-robot"></i></div>' +
            '<div class="chat-msg-bubble">' +
            '<div class="typing-indicator"><span></span><span></span><span></span></div>' +
            '</div></div>';
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

    // ===== INDULÓ POPUP ÜZENET =====
    function hideStartupPopup() {
        var popup = getEl('chatbotStartupPopup');
        if (!popup) return;
        popup.classList.remove('show');
        setTimeout(function () {
            if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
        }, 180);
    }

    function showStartupPopup() {
        if (isOpen || getEl('chatbotStartupPopup')) return;

        var popup = document.createElement('button');
        popup.type = 'button';
        popup.id = 'chatbotStartupPopup';
        popup.className = 'chatbot-startup-popup';
        popup.innerHTML = 'Szia! BMB Asszisztens vagyok, valamiben segíthetek?';
        popup.setAttribute('aria-label', 'BMB Asszisztens üzenet');

        popup.addEventListener('click', function () {
            if (startupPopupHideTimer) {
                clearTimeout(startupPopupHideTimer);
                startupPopupHideTimer = null;
            }
            hideStartupPopup();
            openChat();
        });

        document.body.appendChild(popup);
        requestAnimationFrame(function () {
            popup.classList.add('show');
        });

        startupPopupHideTimer = setTimeout(function () {
            hideStartupPopup();
        }, 7000);
    }

    // ===== VÁLASZ KERESÉSE =====
    function findAnswer(question) {
        var q = question.toLowerCase()
            .replace(/[?!.,;:'"]/g, '')
            .replace(/\s+/g, ' ')
            .trim();

        var bestMatch = null;
        var bestScore = 0;

        for (var i = 0; i < knowledgeBase.length; i++) {
            var entry = knowledgeBase[i];
            var score = 0;

            for (var j = 0; j < entry.keywords.length; j++) {
                var keyword = entry.keywords[j].toLowerCase();

                // Teljes egyezés (pl. "szia" === "szia")
                if (q === keyword) {
                    score += 10;
                }
                // Kérdés tartalmazza a kulcsszót (erős egyezés)
                else if (q.indexOf(keyword) !== -1) {
                    score += keyword.length >= 4 ? 5 : 3;
                }
                // Kulcsszó tartalmazza a kérdés egy szavát (gyenge, csak hosszabb szavakra)
                else {
                    var words = q.split(' ');
                    for (var w = 0; w < words.length; w++) {
                        // Csak 4+ karakter hosszú szavakat veszünk figyelembe a fordított keresésnél
                        // hogy a rövid szavak (pl. "be", "le", "ki") ne adjanak hamis egyezést
                        if (words[w].length >= 4 && keyword.indexOf(words[w]) !== -1) {
                            score += 2;
                        }
                    }
                }
            }

            if (score > bestScore) {
                bestScore = score;
                bestMatch = entry;
            }
        }

        // Minimum score küszöb
        if (bestScore >= 2 && bestMatch) {
            return bestMatch.answer;
        }

        return null;
    }

    // ===== FALLBACK VÁLASZ =====
    function getFallbackAnswer() {
        var fallbacks = [
            '🤔 Sajnos erre nem tudok pontos választ adni. Próbáld meg másképp megfogalmazni, vagy nézd meg a <a href="../../frontend/Help/help.php">Segítség</a> oldalt!',
            '😅 Ezt a kérdést nem teljesen értem. Kérdezz a fogadásról, bónuszokról, befizetésről, vagy írd be pl.: <b>"Hogyan fogadhatok?"</b>',
            '🤷 Hmm, erről nincs információm. Próbáld meg a <a href="../../frontend/Help/GYIK.php">GYIK</a> oldalt, vagy írj az ügyfélszolgálatnak: <b>ugyfelszolgalat@betmatchbonus.com</b>',
            '💡 Nem találtam pontos választ. Tippek: kérdezz rá a <b>fogadásra</b>, <b>bónuszokra</b>, <b>befizetésre</b>, <b>kifizetésre</b>, <b>élő meccsekre</b> vagy <b>regisztrációra</b>!'
        ];
        return fallbacks[Math.floor(Math.random() * fallbacks.length)];
    }

    // ===== FELHASZNÁLÓI ÜZENET KEZELÉSE =====
    function handleUserMessage(text) {
        if (!text || text.trim() === '' || isTyping) return;

        text = text.trim();
        addMessage(text, 'user');

        // Input mező ürítése
        var input = getEl('chatbotInput');
        if (input) input.value = '';

        // Gépelés animáció
        showTyping();

        // Válasz keresése kis késleltetéssel (természetesebb hatás)
        var delay = 600 + Math.random() * 800;
        setTimeout(function () {
            hideTyping();
            var answer = findAnswer(text);
            if (!answer) {
                answer = getFallbackAnswer();
            }
            addMessage(answer, 'bot');
        }, delay);
    }

    // ===== ABLAK NYITÁS / ZÁRÁS =====
    function openChat() {
        var win = getEl('chatbotWindow');
        var toggle = getEl('chatbotToggle');
        var notif = getEl('chatbotNotification');
        if (!win || !toggle) return;

        if (startupPopupHideTimer) {
            clearTimeout(startupPopupHideTimer);
            startupPopupHideTimer = null;
        }
        hideStartupPopup();

        win.classList.remove('closing');
        win.classList.add('open');
        toggle.classList.add('active');
        isOpen = true;

        // Notification badge elrejtése
        if (notif) notif.classList.add('hidden');

        // Első megnyitáskor üdvözlő üzenet (ha nincs mentett előzmény)
        if (chatHistory.length === 0) {
            setTimeout(function () {
                addMessage(
                    '👋 <b>Üdvözöllek a BetMatchBonus-on!</b><br><br>' +
                    'Én vagyok a <b>BMB Asszisztens</b> – segítek eligazodni az oldalon.<br><br>' +
                    'Kérdezz bátran, vagy válassz az alábbi témák közül! 👇',
                    'bot'
                );
            }, 300);
        }

        // Focus az input mezőre
        setTimeout(function () {
            var input = getEl('chatbotInput');
            if (input) input.focus();
        }, 400);
    }

    function closeChat() {
        var win = getEl('chatbotWindow');
        var toggle = getEl('chatbotToggle');
        if (!win || !toggle) return;

        win.classList.add('closing');
        setTimeout(function () {
            win.classList.remove('open', 'closing');
        }, 250);
        toggle.classList.remove('active');
        isOpen = false;
    }

    function toggleChat() {
        if (isOpen) {
            closeChat();
        } else {
            openChat();
        }
    }

    // ===== BESZÉLGETÉS TÖRLÉSE =====
    function clearChat() {
        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl) return;
        messagesEl.innerHTML = '';
        chatHistory = [];
        deleteSavedChat();

        // Suggestions újra mutatása
        var suggestions = getEl('chatbotSuggestions');
        if (suggestions) suggestions.style.display = 'flex';

        // Újra üdvözlés
        setTimeout(function () {
            addMessage(
                '🔄 Beszélgetés törölve! Miben segíthetek? 😊',
                'bot'
            );
        }, 300);
    }

    // ===== MENTETT CHAT VISSZAÁLLÍTÁSA =====
    function restoreChat() {
        var saved = loadChat();
        if (!saved || saved.length === 0) return false;

        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl) return false;

        // Notification badge elrejtése, mert már volt beszélgetés
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
        // Mentett chat visszaállítása
        restoreChat();

        // Toggle gomb
        var toggleBtn = getEl('chatbotToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleChat);
        }

        // Bezárás gomb
        var closeBtn = getEl('chatbotClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeChat);
        }

        // Törlés gomb
        var clearBtn = getEl('chatbotClear');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearChat);
        }

        // Küldés gomb
        var sendBtn = getEl('chatbotSend');
        if (sendBtn) {
            sendBtn.addEventListener('click', function () {
                var input = getEl('chatbotInput');
                if (input) handleUserMessage(input.value);
            });
        }

        // Enter billentyű
        var inputEl = getEl('chatbotInput');
        if (inputEl) {
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    handleUserMessage(this.value);
                }
            });
        }

        // Gyors kérdés gombok
        var suggestionBtns = document.querySelectorAll('.chatbot-suggestion');
        for (var i = 0; i < suggestionBtns.length; i++) {
            suggestionBtns[i].addEventListener('click', function () {
                var question = this.getAttribute('data-question');
                if (question) {
                    if (!isOpen) openChat();
                    setTimeout(function () {
                        handleUserMessage(question);
                    }, 350);
                }
            });
        }

        setTimeout(showStartupPopup, 900);
    }

    // DOM betöltés után indítás
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();