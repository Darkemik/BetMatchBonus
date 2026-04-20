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

    function t(key, fallback) {
        return (typeof window.i18n === 'function') ? window.i18n(key, fallback) : (fallback || key);
    }

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
    function getContextTip(context) {
        var tips = {
            live: t('chatbot.context.live', '🔴 You are currently on the <b>Live</b> page. Click a match to view markets, or ask for <b>#live</b>'),
            bonus: t('chatbot.context.bonus', '🎁 You are on the <b>Bonuses</b> page. Type <b>#bonus</b> to see your active bonuses.'),
            esport: t('chatbot.context.esport', '🎮 You are in the <b>eSport</b> section. Check out live e-sport matches.'),
            deposit: t('chatbot.context.deposit', '💳 You are on the <b>Deposit</b> page. Ask anything about payment methods.'),
            withdrawal: t('chatbot.context.withdrawal', '💰 You are on the <b>Withdrawal</b> page. If you have questions, I can help.'),
            profile: t('chatbot.context.profile', '📋 You are on the <b>Personal Data</b> page. Make sure your details are up to date.'),
            transactions: t('chatbot.context.transactions', '🧾 You are on the <b>Transactions</b> page. You can review all money movements here.'),
            main: t('chatbot.context.main', '⚽ You are on the <b>Main page</b>. Choose a sport from the left menu, or ask: <b>#live</b>')
        };
        return tips[context] || '';
    }

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
        '#commands': showCommands,
        '#parancs': showCommands,
        '#parancsok': showCommands,
    };

    // ===== TUDÁSBÁZIS =====
    var knowledgeBase = [
        {
            keywords: ['fogad', 'tippel', 'odds', 'szelvény', 'ticket', 'how to bet', 'how can i bet', 'betting', 'fogadás', 'stake'],
            get answer() {
                var isEn = getUiLocale() === 'en-US';
                if (isEn) {
                    return '🎯 <b>How to place a bet?</b><br><br>' +
                        '<div class="chat-steps">' +
                        '<div class="chat-step"><span class="step-num">1</span> Pick a match on the <a href="../../frontend/MainMenu/MainMenu.php">Main page</a></div>' +
                        '<div class="chat-step"><span class="step-num">2</span> Open the match and choose a market (1X2, Goals...)</div>' +
                        '<div class="chat-step"><span class="step-num">3</span> Click an odd to add it to your Ticket</div>' +
                        '<div class="chat-step"><span class="step-num">4</span> Enter your stake (min. 100 Ft)</div>' +
                        '<div class="chat-step"><span class="step-num">5</span> Click <b>"Place Ticket"</b></div>' +
                        '</div>';
                }
                return '🎯 <b>Hogyan fogadhatsz?</b><br><br>' +
                    '<div class="chat-steps">' +
                    '<div class="chat-step"><span class="step-num">1</span> Válassz meccset a <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a></div>' +
                    '<div class="chat-step"><span class="step-num">2</span> Kattints a meccsre -> válassz piacot (1X2, Gólszám...)</div>' +
                    '<div class="chat-step"><span class="step-num">3</span> Kattints az oddsra -> megjelenik a Ticket sávban</div>' +
                    '<div class="chat-step"><span class="step-num">4</span> Add meg a tétet (min. 100 Ft)</div>' +
                    '<div class="chat-step"><span class="step-num">5</span> Kattints <b>"Szelvény leadása"</b></div>' +
                    '</div>';
            }
        },
        {
            keywords: ['bónusz', 'bonus', 'promo', 'promotion', 'offer', 'code', 'coupon', 'free'],
            get answer() {
                var isEn = getUiLocale() === 'en-US';
                if (isEn) {
                    return '🎁 <b>Bonuses and promotions</b><br><br>' +
                        'You can find current offers on the <a href="../../frontend/Bonus/bonus.php">Bonuses</a> page.<br><br>' +
                        '• <b>Weekday bonus</b> - 100% up to 5,000 Ft<br>' +
                        '• <b>Darts bonus</b> - 10,000 Ft wager -> 5,000 Ft bonus<br>' +
                        '• <b>Cashback</b> - 30% Free Bet after a losing wager<br>' +
                        '• <b>Daily Top Reward</b> - 1,000 Ft Free Bet<br><br>' +
                        '💡 Type <b>#bonus</b> to view your active bonuses.';
                }
                return '🎁 <b>Bónuszok és promóciók</b><br><br>' +
                    'Az aktuális bónuszainkat a <a href="../../frontend/Bonus/bonus.php">Bónuszok</a> oldalon találod.<br><br>' +
                    '• <b>Hétköznapi bónusz</b> - 100% max 5.000 Ft<br>' +
                    '• <b>Darts bónusz</b> - 10.000 Ft fogadás -> 5.000 Ft bónusz<br>' +
                    '• <b>Cashback</b> - 30% Ingyenes fogadás vesztes fogadás után<br>' +
                    '• <b>Napi Top Jutalom</b> - 1.000 Ft Ingyenes fogadás<br><br>' +
                    '💡 Írd be <b>#bónusz</b> az aktív bónuszaid megtekintéséhez.';
            }
        },
        {
            keywords: ['befizet', 'befizetés', 'fizethetek be', 'hogyan fizethetek be', 'hogyan tudok befizetni', 'fizetés', 'deposit', 'top up', 'money', 'card', 'transfer', 'payment'],
            get answer() {
                var minDep = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_deposit) || 3000;
                var locale = (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en-US' : 'hu-HU';
                var isEn = getUiLocale() === 'en-US';
                if (isEn) {
                    return '💳 <b>Deposit</b><br><br>' +
                        '<div class="chat-info-card">' +
                        '<div class="chat-info-row"><span class="chat-info-label">Method</span><span>Bank card (Visa, MC), Bank transfer</span></div>' +
                        '<div class="chat-info-row"><span class="chat-info-label">Min.</span><span><b>' + minDep.toLocaleString(locale) + ' Ft</b></span></div>' +
                        '<div class="chat-info-row"><span class="chat-info-label">Quick</span><span>5,000 / 7,500 / 10,000 / 20,000 Ft</span></div>' +
                        '<div class="chat-info-row"><span class="chat-info-label">Time</span><span>Instant ⚡</span></div>' +
                        '</div>' +
                        '<a href="../../frontend/UserProfile/deposit.php" class="chat-action-link">💳 Open deposit page -></a>';
                }
                return '💳 <b>Befizetés</b><br><br>' +
                    '<div class="chat-info-card">' +
                    '<div class="chat-info-row"><span class="chat-info-label">Mód</span><span>Bankkártya (Visa, MC), Banki átutalás</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Min.</span><span><b>' + minDep.toLocaleString(locale) + ' Ft</b></span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Gyors</span><span>5.000 / 7.500 / 10.000 / 20.000 Ft</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Idő</span><span>Azonnali ⚡</span></div>' +
                    '</div>' +
                    '<a href="../../frontend/UserProfile/deposit.php" class="chat-action-link">💳 Befizetés oldal -></a>';
            }
        },
        {
            keywords: ['kifizet', 'kifizetés', 'hogyan fizethetek ki', 'kiutalás', 'withdrawal', 'cashout', 'withdraw', 'payout'],
            get answer() {
                var minW = (window.SITE_SETTINGS && window.SITE_SETTINGS.min_withdrawal) || 6000;
                var locale = (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en-US' : 'hu-HU';
                return '💰 <b>Withdrawal</b><br><br>' +
                    '<div class="chat-info-card">' +
                    '<div class="chat-info-row"><span class="chat-info-label">Method</span><span>Bank transfer</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Min.</span><span><b>' + minW.toLocaleString(locale) + ' Ft</b></span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Time</span><span>1-3 business days</span></div>' +
                    '<div class="chat-info-row"><span class="chat-info-label">Account</span><span>Must be in your own name</span></div>' +
                    '</div>' +
                    '<a href="../../frontend/UserProfile/withdrawal.php" class="chat-action-link">💰 Open withdrawal page -></a>';
            }
        },
        {
            keywords: ['élő', 'live', 'live match', 'in-play', 'ongoing'],
            answer: '⚽ <b>Live matches</b><br><br>' +
                'You can find ongoing events in the <a href="../../frontend/Live/live.php">Live</a> section.<br><br>' +
                '• Real-time results and live odds<br>' +
                '• Click any match to view markets<br><br>' +
                '💡 Type <b>#live</b> to see how many live matches are available.'
        },
        {
            keywords: ['esport', 'e-sport', 'gaming', 'csgo', 'lol', 'dota', 'valorant'],
            answer: '🎮 <b>eSport betting</b><br><br>' +
                'Find eSport events on the <a href="../../frontend/Esport/esport.php">eSport</a> page.<br><br>' +
                '• e-Football, e-Basketball, e-Ice Hockey<br>' +
                '• CS2, League of Legends, Dota 2, Valorant<br>' +
                '• Works similarly to regular sports betting.'
        },
        {
            keywords: ['register', 'account', 'signup', 'create account', 'regiszt'],
            answer: '👤 <b>Registration</b><br><br>' +
                '<div class="chat-steps">' +
                '<div class="chat-step"><span class="step-num">1</span> Click <b>"Register"</b> in the top-right corner</div>' +
                '<div class="chat-step"><span class="step-num">2</span> Enter username, email, and password</div>' +
                '<div class="chat-step"><span class="step-num">3</span> Fill in your personal details</div>' +
                '<div class="chat-step"><span class="step-num">4</span> Accept terms and policies</div>' +
                '</div>' +
                '⚠️ Registration is available for 18+ users only.'
        },
        {
            keywords: ['password', 'forgot', 'login', 'sign in', 'jelszó'],
            answer: '🔐 <b>Login and password</b><br><br>' +
                '• Login: top-right -> <b>"Login"</b><br>' +
                '• Forgot password: use the <b>"Forgot password"</b> link<br>' +
                '• Change password: <a href="../../frontend/UserProfile/change_password.php">Change Password</a><br><br>' +
                '🛡️ Use a strong password with at least 8 characters.'
        },
        {
            keywords: ['personal data', 'profile', 'phone', 'address', 'személyes'],
            answer: '📋 <b>Personal data</b><br><br>' +
                '• Editable: name, phone, country, city, address<br>' +
                '• Non-editable: username, email, date of birth<br><br>' +
                '<a href="../../frontend/UserProfile/personal_data.php" class="chat-action-link">📋 Open personal data -></a>'
        },
        {
            keywords: ['ticket history', 'history', 'previous bet', 'ticket', 'previous'],
            answer: '📊 <b>Bet history</b><br><br>' +
                'Open the Ticket panel on the right and switch to <b>"History"</b>.<br><br>' +
                '• ✅ WON • ❌ LOST • ⏳ OPEN<br><br>' +
                '💡 Type <b>#ticket</b> for a quick summary of your latest tickets.'
        },
        {
            keywords: ['transaction', 'money movement', 'deposit history', 'payout history'],
            answer: '🧾 <b>Transaction history</b><br><br>' +
                'You can review all money movements here:<br>' +
                '<a href="../../frontend/UserProfile/transaction_history.php" class="chat-action-link">🧾 Open transaction history -></a>'
        },
        {
            keywords: ['help', 'support', 'customer service', 'contact', 'email', 'problem', 'segítség'],
            answer: '📞 <b>Help and contact</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">📖</span><a href="../../frontend/Help/help.php">Help page</a></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">❓</span><a href="../../frontend/Help/GYIK.php">FAQ</a></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">📧</span><span>ugyfelszolgalat@betmatchbonus.com</span></div>' +
                '</div>'
        },
        {
            keywords: ['faq', 'common questions', 'gyik'],
            answer: '❓ You can find frequently asked questions on the <a href="../../frontend/Help/GYIK.php">FAQ</a> page.'
        },
        {
            keywords: ['rules', 'terms', 'policy', 'regulation', 'szabály'],
            answer: '📜 <b>Rules and policies</b><br><br>' +
                '• <a href="../../frontend/Help/reszveteli-szabalyzat.php">Participation rules</a><br>' +
                '• <a href="../../frontend/Help/sportszabalyok.php">Sport rules</a><br>' +
                '• <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php">Privacy policy</a><br>' +
                '• <a href="../../frontend/Help/jatekosvedelem.php">Player protection</a>'
        },
        {
            keywords: ['privacy', 'gdpr', 'cookie', 'data', 'adatkezel'],
            answer: '🔒 <b>Privacy</b><br><br>' +
                'Full details: <a href="../../frontend/Help/adatkezelesi_tajekoztatok.php">Privacy policy</a><br>' +
                'We process your data securely and use cookies to operate the site properly.'
        },
        {
            keywords: ['safety', 'protection', 'responsible', 'limit', 'player protection', 'játékosvéd'],
            answer: '🛡️ <b>Player protection</b><br><br>' +
                'Responsible gambling is important. If needed, please seek support.<br>' +
                '<a href="../../frontend/Help/jatekosvedelem.php" class="chat-action-link">🛡️ Open player protection -></a>'
        },
        {
            keywords: ['milyen sportokra fogadhatok', 'what sports can i bet on', 'sport', 'football', 'basketball', 'handball', 'ice hockey', 'darts', 'table tennis', 'foci', 'sportágak'],
            answer: '🏆 <b>Available sports</b><br><br>' +
                '<div class="chat-sports-grid">' +
                '<span>⚽ Football</span><span>🏀 Basketball</span>' +
                '<span>🏒 Ice Hockey</span><span>🤾 Handball</span>' +
                '<span>🎯 Darts</span><span>🤽 Water Polo</span>' +
                '<span>🏓 Table Tennis</span><span>🎮 eSport</span>' +
                '<span>⚾ Baseball</span><span>🏈 American Football</span>' +
                '<span>🏐 Volleyball</span><span>🥊 MMA</span>' +
                '</div>'
        },
        {
            keywords: ['which sport', 'available sports', 'sport list', 'sports', 'milyen sport', 'elérhető sport', 'sportokra fogadhatok'],
            answer: '🏆 <b>20+ sports are available!</b><br><br>' +
                'Popular options: ⚽ Football, 🏀 Basketball, 🏒 Ice Hockey, 🎯 Darts, 🎮 eSport<br><br>' +
                'Use the left menu to browse events by sport.'
        },
        {
            keywords: ['1x2', 'market', 'over', 'under', 'goals', 'handicap', 'btts', 'double chance'],
            answer: '📈 <b>Betting markets</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">1X2</span><span>Home / Draw / Away</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">O/U</span><span>Over/Under goals</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">BTTS</span><span>Both teams to score</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">DC</span><span>Double chance (1X, X2, 12)</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">HCP</span><span>Handicap betting</span></div>' +
                '</div>'
        },
        {
            keywords: ['combo', 'accumulator', 'acca', 'multi', 'parlay', 'kötés'],
            answer: '🔗 <b>Combo (accumulator) bet</b><br><br>' +
                'You can combine picks from multiple matches on one ticket.<br>' +
                '• Odds multiply -> higher potential payout<br>' +
                '• Every pick must be correct<br><br>' +
                '💡 Tip: keep your combo size realistic.'
        },
        {
            keywords: ['cashout', 'cash out', 'close early'],
            answer: '💸 <b>Cash Out</b><br><br>' +
                'Cash Out lets you settle your ticket before the match ends.<br><br>' +
                '• Available for open tickets in Ticket -> History<br>' +
                '• Offer amount is based on live odds<br>' +
                '• Not available for bonus/freebet stake and Odds Spaceship tickets'
        },
        {
            keywords: ['odds spaceship', 'boosted', 'boosted odds', 'oddsűrhajó'],
            answer: '🚀 <b>Odds Spaceship</b><br><br>' +
                'A highlighted daily match with boosted odds appears on the main page.<br><br>' +
                '⚠️ Cash Out is not available for Odds Spaceship tickets.'
        },
        {
            keywords: ['complaint', 'claim', 'issue report', 'panasz'],
            answer: '📝 You can submit complaints on the <a href="../../frontend/Help/panaszkezeles.php">Complaint Handling</a> page, or write to: <b>ugyfelszolgalat@betmatchbonus.com</b>'
        },
        {
            keywords: ['dictionary', 'term', 'meaning', 'szótár'],
            answer: '📖 Betting terms: <a href="../../frontend/Help/szotar.php">Dictionary</a>'
        },
        {
            keywords: ['payment method', 'visa', 'mastercard', 'transfer', 'fizetés'],
            answer: '💳 Payment methods: Visa, Mastercard, and bank transfer. Details: <a href="../../frontend/Help/fizetesi_lehetosegek.php">Payment options</a>'
        },
        {
            keywords: ['new feature', 'news', 'update', 'frissítés', 'újdonság'],
            answer: '🆕 Latest updates: <a href="../../frontend/Help/uj_funkcio.php">New Features</a> page'
        },
        {
            keywords: ['hello', 'hi', 'hey', 'szia', 'helló', 'üdv'],
            answer: '👋 <b>Welcome!</b> I am your <b>BMB Assistant</b>. Ask anything, or type <b>#commands</b> to view features. 😊'
        },
        {
            keywords: ['thanks', 'thank you', 'thx', 'kösz'],
            answer: '😊 You are welcome! If you need anything else, I am here.'
        },
        {
            keywords: ['log', 'activity', 'napló'],
            answer: '📋 Activity log: <a href="../../frontend/UserProfile/activity_log.php" class="chat-action-link">📋 Open activity log -></a>'
        },
        {
            keywords: ['balance', 'how much money', 'egyenleg'],
            answer: '💰 To check your balance, type: <b>#balance</b>'
        },
        {
            keywords: ['daily tip', 'tip', 'recommendation', 'what to bet', 'napi tipp'],
            answer: '📊 <b>Daily tips</b><br><br>' +
                'You can find recommendations in the <b>"Daily tips"</b> section on the main page.<br>' +
                'These are based on AI-assisted analysis.'
        },
        {
            keywords: ['who are you', 'what are you', 'robot', 'bot', 'ki vagy'],
            answer: '🤖 I am your <b>BMB Assistant</b> - the support chatbot of BetMatchBonus.<br><br>' +
                'I can help with betting, bonuses, deposits, and platform usage.<br>' +
                'Type <b>#commands</b> to see special functions.'
        },
        {
            keywords: ['what can you do', 'features', 'help me', 'mit tudsz'],
            answer: '🚀 <b>What I can do:</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">#balance</span><span>Check your balance</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#bonus</span><span>Your active bonuses</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#ticket</span><span>Your recent tickets</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#live</span><span>Live match count</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">#stat</span><span>Betting statistics</span></div>' +
                '</div>' +
                'Or ask a free-text question anytime. 😊'
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

    function getUiLocale() {
        return (typeof window.i18nLang === 'function' && window.i18nLang() === 'en') ? 'en-US' : 'hu-HU';
    }

    function localizeBotText(text) {
        var out = String(text || '');
        var toEn = [
            [/Üdvözöllek a BetMatchBonus-on!/g, 'Welcome to BetMatchBonus!'],
            [/Én vagyok a <b>BMB Asszisztens<\/b> – segítek eligazodni!/g, 'I am your <b>BMB Assistant<\/b> - here to help you navigate!'],
            [/Kérdezz bátran, vagy írd be <b>#parancsok<\/b> a funkcióimért!/g, 'Ask anything, or type <b>#commands<\/b> to see my features!'],
            [/A <b>Főoldalon<\/b> vagy\. Válassz sportot a bal menüből, vagy kérdezz: <b>#élő<\/b>/g, 'You are on the <b>Main page<\/b>. Choose a sport from the left menu, or ask: <b>#live<\/b>'],
            [/Élő meccsek/g, 'Live matches'],
            [/Befizetés/g, 'Deposit'],
            [/Kifizetés/g, 'Withdrawal'],
            [/Személyes adatok/g, 'Personal Data'],
            [/Tranzakciók/g, 'Transactions'],
            [/Napi tippek/g, 'Daily tips'],
            [/Összegzés/g, 'Summary'],
            [/Parancsok/g, 'Commands'],
            [/Egyenleg/g, 'Balance'],
            [/Szelvények/g, 'Tickets'],
            [/Tét:/g, 'Stake:'],
            [/Nyeremény:/g, 'Winnings:'],
            [/Jelentkezz be/g, 'Please sign in'],
            [/Még nincs fogadásod\./g, 'You have no bets yet.'],
            [/Jelenleg nincs élő meccs\./g, 'There are no live matches right now.'],
            [/Nézz vissza később!/g, 'Please check back later!'],
            [/Utolsó szelvényeid/g, 'Your latest tickets'],
            [/Fogadási statisztikáid/g, 'Your betting statistics'],
            [/nyerési arány/g, 'win rate'],
            [/Elérhető parancsok/g, 'Available commands'],
            [/A főoldalon a <b>"Napi tippek"<\/b> szekcióban találsz ajánlásokat!/g, 'You can find recommendations in the <b>"Daily tips"<\/b> section on the Main page!'],
            [/Ezek mesterséges intelligencia alapú elemzésen alapulnak\./g, 'These are based on AI-assisted analysis.'],
            [/Beszélgetés törölve! Miben segíthetek\?/g, 'Chat cleared! How can I help?']
        ];

        var toHu = [
            [/How to place a bet\?/g, 'Hogyan fogadhatsz?'],
            [/How can I bet\?/g, 'Hogyan fogadhatok?'],
            [/Pick a match on the\s*<a href="\.\.\/\.\.\/frontend\/MainMenu\/MainMenu\.php">Main page<\/a>/g, 'Válassz meccset a <a href="../../frontend/MainMenu/MainMenu.php">Főoldalon</a>'],
            [/Open the match and choose a market \(1X2, Goals\.\.\.\)/g, 'Kattints a meccsre -> válassz piacot (1X2, Gólszám...)'],
            [/Click an odd to add it to your Ticket/g, 'Kattints az oddsra -> megjelenik a Ticket sávban'],
            [/Enter your stake \(min\. 100 Ft\)/g, 'Add meg a tétet (min. 100 Ft)'],
            [/Click\s*(<b>)?"Place Ticket"(<\/b>)?/g, 'Kattints "Szelvény leadása"'],
            [/Bonuses and promotions/g, 'Bónuszok és promóciók'],
            [/Available sports/g, 'Elérhető sportágak'],
            [/20\+ sports are available!/g, '20+ sportág érhető el!'],
            [/You can find current offers on the\s*<a href="\.\.\/\.\.\/frontend\/Bonus\/bonus\.php">Bonuses<\/a>\s*page\./g, 'Az aktuális bónuszainkat a <a href="../../frontend/Bonus/bonus.php">Bónuszok</a> oldalon találod.'],
            [/Weekday bonus\s*-\s*100% up to 5,000 Ft/g, 'Hétköznapi bónusz - 100% max 5.000 Ft'],
            [/Darts bonus\s*-\s*10,000 Ft wager\s*->\s*5,000 Ft bonus/g, 'Darts bónusz - 10.000 Ft fogadás -> 5.000 Ft bónusz'],
            [/Cashback\s*-\s*30% Free Bet after a losing wager/g, 'Cashback - 30% Ingyenes fogadás vesztes fogadás után'],
            [/Daily Top Reward\s*-\s*1,000 Ft Free Bet/g, 'Napi Top Jutalom - 1.000 Ft Ingyenes fogadás'],
            [/Type\s*#bonus\s*to view your active bonuses\./g, 'Írd be <b>#bónusz</b> az aktív bónuszaid megtekintéséhez.'],
            [/Deposit/g, 'Befizetés'],
            [/Withdrawal/g, 'Kifizetés'],
            [/Method/g, 'Mód'],
            [/Quick/g, 'Gyors'],
            [/Time/g, 'Idő'],
            [/Instant/g, 'Azonnali'],
            [/Open deposit page\s*->/g, 'Befizetés oldal ->'],
            [/Live matches now:/g, 'Élő meccsek most:'],
            [/Live matches/g, 'Élő meccsek'],
            [/View live matches ->/g, 'Élő meccsek megtekintése ->'],
            [/Your balance/g, 'Egyenleged'],
            [/\bBalance\b/g, 'Egyenleg'],
            [/bonus balance/g, 'bónusz egyenleg'],
            [/Your active bonuses/g, 'Aktív bónuszaid'],
            [/Wagering:/g, 'Forgatás:'],
            [/Your latest tickets/g, 'Utolsó szelvényeid'],
            [/Stake:/g, 'Tét:'],
            [/Winnings:/g, 'Nyeremény:'],
            [/Potential:/g, 'Pot.:'],
            [/Your betting statistics/g, 'Fogadási statisztikáid'],
            [/Win rate/g, 'Nyerési arány'],
            [/Summary/g, 'Összegzés'],
            [/Tickets/g, 'Szelvények'],
            [/Active bonuses/g, 'Aktív bónusz'],
            [/Available commands/g, 'Elérhető parancsok'],
            [/Your balance/g, 'Egyenleged'],
            [/Latest bets/g, 'Utolsó fogadások'],
            [/Statistics/g, 'Statisztikák'],
            [/Full summary/g, 'Teljes összegzés'],
            [/#balance/g, '#egyenleg'],
            [/#bonus/g, '#bónusz'],
            [/#ticket/g, '#szelvény'],
            [/#live/g, '#élő'],
            [/#summary/g, '#összegzés'],
            [/I do not have an exact answer for that yet\. Try rephrasing, or type <b>#commands<\/b>\./g, 'Erre nem tudok pontos választ adni. Próbáld meg másképp, vagy írd be <b>#parancsok</b>!'],
            [/I did not fully understand that\. Ask about betting, bonuses, or try <b>#balance<\/b>\./g, 'Ezt nem teljesen értem. Kérdezz a fogadásról, bónuszokról, vagy írd be pl.: <b>#egyenleg</b>'],
            [/I do not have details on that\. Check the <a href="\.\.\/\.\.\/frontend\/Help\/GYIK\.php">FAQ<\/a> page, or type <b>#help<\/b>\./g, 'Erről nincs információm. Nézd meg a <a href="../../frontend/Help/GYIK.php">GYIK</a>-et, vagy írd be <b>#help</b>!'],
            [/No direct match found\. Try <b>#commands<\/b> to see what I can do\./g, 'Nem találtam választ. Próbáld: <b>#parancsok</b> a funkciók listájáért!'],
            [/Welcome to BetMatchBonus!/g, 'Üdvözöllek a BetMatchBonus-on!'],
            [/I am your <b>BMB Assistant<\/b> - here to help you navigate\./g, 'Én vagyok a <b>BMB Asszisztens</b> - segítek eligazodni.'],
            [/Ask anything, or type <b>#commands<\/b> to view my features\./g, 'Kérdezz bátran, vagy írd be <b>#parancsok</b> a funkcióimért.'],
            [/Chat cleared! How can I help\?/g, 'Beszélgetés törölve! Miben segíthetek?'],
            [/Please sign in/g, 'Jelentkezz be']
        ];

        // Sports labels in chatbot cards
        toHu.push([/Football/g, 'Labdarúgás']);
        toHu.push([/Basketball/g, 'Kosárlabda']);
        toHu.push([/Ice Hockey/g, 'Jégkorong']);
        toHu.push([/Handball/g, 'Kézilabda']);
        toHu.push([/Darts/g, 'Darts']);
        toHu.push([/Water Polo/g, 'Vízilabda']);
        toHu.push([/Table Tennis/g, 'Asztalitenisz']);
        toHu.push([/eSport/g, 'eSport']);
        toHu.push([/Baseball/g, 'Baseball']);
        toHu.push([/American Football/g, 'Amerikai Futball']);
        toHu.push([/Volleyball/g, 'Röplabda']);
        toHu.push([/MMA/g, 'MMA']);

        var map = getUiLocale() === 'en-US' ? toEn : toHu;
        for (var i = 0; i < map.length; i++) {
            out = out.replace(map[i][0], map[i][1]);
        }
        return out;
    }

    // ===== FORMÁZÁS =====
    function fmtNum(n) { return n.toLocaleString(getUiLocale()); }
    function fmtDate(d) {
        try { return new Date(d).toLocaleString(getUiLocale(), { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }); }
        catch (e) { return d; }
    }

    // ===== ÜZENET LÉTREHOZÁSA =====
    function createMessageHTML(text, sender) {
        var iconClass = sender === 'bot' ? 'fa-robot' : 'fa-user';
        var time = new Date().toLocaleTimeString(getUiLocale(), { hour: '2-digit', minute: '2-digit' });
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
        var rawText = String(text || '');
        var renderText = sender === 'bot' ? localizeBotText(rawText) : rawText;
        messagesEl.insertAdjacentHTML('beforeend', createMessageHTML(renderText, sender));
        messagesEl.scrollTop = messagesEl.scrollHeight;
        chatHistory.push({ text: rawText, sender: sender });
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
        if (localStorage.getItem('bmb_popup_shown')) return;

        var popup = document.createElement('button');
        popup.type = 'button';
        popup.id = 'chatbotStartupPopup';
        popup.className = 'chatbot-startup-popup';
        popup.innerHTML = '<i class="fas fa-robot" style="margin-right:6px;"></i> ' +
            t('chatbot.startupPopup', 'Szia! A BMB Asszisztens vagyok. Segíthetek valamiben?');
        popup.setAttribute('aria-label', t('chatbot.startupPopupAria', 'BMB Asszisztens üzenet'));

        popup.addEventListener('click', function () {
            if (startupPopupHideTimer) { clearTimeout(startupPopupHideTimer); startupPopupHideTimer = null; }
            hideStartupPopup();
            openChat();
        });

        document.body.appendChild(popup);
        applyMobileFloatingOffsets();
        requestAnimationFrame(function () { popup.classList.add('show'); });
        localStorage.setItem('bmb_popup_shown', '1');
        startupPopupHideTimer = setTimeout(hideStartupPopup, 7000);
    }

    // ===== INTERAKTÍV PARANCSOK — BACKEND FETCH =====
    function fetchFromBackend(action, callback, onError) {
        fetch('../../backend/ApiRequest/chatbot_data.php?action=' + action)
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function (err) {
                if (typeof onError === 'function') {
                    onError(err);
                    return;
                }
                hideTyping();
                addBotMessage(getUiLocale() === 'en-US'
                    ? '⚠️ Something went wrong while loading data. Please try again.'
                    : '⚠️ Hiba történt az adatok lekérésekor. Próbáld újra!');
            });
    }

    function fetchBalance() {
        showTyping();
        fetchFromBackend('balance', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Please sign in to view your balance.');
                return;
            }
            addBotMessage(
                '💰 <b>Your balance</b><br><br>' +
                '<div class="chat-info-card chat-balance-card">' +
                '<div class="chat-balance-main">' + fmtNum(data.balance) + ' <small>Ft</small></div>' +
                (data.bonusBalance > 0 ? '<div class="chat-balance-bonus">+ ' + fmtNum(data.bonusBalance) + ' Ft bonus balance</div>' : '') +
                '</div>'
            );
        });
    }

    function fetchBonuses() {
        showTyping();
        fetchFromBackend('bonuses', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Please sign in to view your bonuses.');
                return;
            }
            if (!data.activeBonuses || data.activeBonuses.length === 0) {
                addBotMessage('🎁 You currently have no active bonus. Check available offers: <a href="../../frontend/Bonus/bonus.php">Bonuses</a>');
                return;
            }
            var html = '🎁 <b>Your active bonuses</b><br><br>';
            data.activeBonuses.forEach(function (b) {
                var pct = b.wageringRequired > 0 ? Math.min(100, Math.round(b.wageringProgress / b.wageringRequired * 100)) : 100;
                html += '<div class="chat-bonus-item">' +
                    '<div class="chat-bonus-name">' + b.name + ' <span class="chat-badge chat-badge-' + b.status.toLowerCase() + '">' + b.status + '</span></div>' +
                    (b.balance > 0 ? '<div class="chat-bonus-balance">' + fmtNum(b.balance) + ' Ft</div>' : '') +
                    (b.wageringRequired > 0 ?
                        '<div class="chat-progress-wrap"><div class="chat-progress-bar" style="width:' + pct + '%"></div></div>' +
                        '<div class="chat-progress-text">Wagering: ' + fmtNum(b.wageringProgress) + ' / ' + fmtNum(b.wageringRequired) + ' Ft (' + pct + '%)</div>' : '') +
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
                addBotMessage('🔒 Please sign in to view your tickets.');
                return;
            }
            if (!data.recentTickets || data.recentTickets.length === 0) {
                addBotMessage('📊 You have no bets yet. Try a match on the <a href="../../frontend/MainMenu/MainMenu.php">Main page</a>.');
                return;
            }
            var icons = { WON: '✅', LOST: '❌', OPEN: '⏳', CASHOUT: '💰' };
            var html = '📊 <b>Your latest tickets</b><br><br>';
            data.recentTickets.forEach(function (t) {
                html += '<div class="chat-ticket-item">' +
                    '<div class="chat-ticket-header">' +
                    '<span>' + (icons[t.status] || '❓') + ' #' + t.id + '</span>' +
                    '<span class="chat-ticket-date">' + fmtDate(t.date) + '</span>' +
                    '</div>' +
                    '<div class="chat-ticket-details">' +
                    '<span>Stake: <b>' + fmtNum(t.stake) + ' Ft</b></span>' +
                    '<span>Odds: ' + t.odds.toFixed(2) + '</span>' +
                    '<span>' + (t.status === 'CASHOUT' ? 'Cash Out: <b>' + fmtNum(t.cashout) + ' Ft</b>' :
                        (t.status === 'WON' ? 'Winnings: <b>' + fmtNum(t.potentialWin) + ' Ft</b>' :
                        'Potential: ' + fmtNum(t.potentialWin) + ' Ft')) + '</span>' +
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
                addBotMessage('🔒 Please sign in to view your statistics.');
                return;
            }
            var s = data.ticketStats || {};
            var winRate = s.total > 0 ? Math.round(s.won / s.total * 100) : 0;
            addBotMessage(
                '📊 <b>Your betting statistics</b><br><br>' +
                '<div class="chat-info-card">' +
                '<div class="chat-info-row"><span class="chat-info-label">Total</span><span><b>' + s.total + '</b> tickets</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">✅ Won</span><span>' + s.won + '</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">❌ Lost</span><span>' + s.lost + '</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">⏳ Open</span><span>' + s.open + '</span></div>' +
                '<div class="chat-info-row"><span class="chat-info-label">🎯 Win rate</span><span><b>' + winRate + '%</b></span></div>' +
                '</div>'
            );
        });
    }

    function fetchLiveFromSidebarFallback() {
        fetch('../../backend/ApiRequest/get_sidebar_sports.php?mode=live')
            .then(function (r) { return r.json(); })
            .then(function (sports) {
                if (!Array.isArray(sports)) {
                    addBotMessage(getUiLocale() === 'en-US'
                        ? '⚽ Live data is temporarily unavailable. You can still browse matches on the <a href="../../frontend/Live/live.php">Live</a> page.'
                        : '⚽ Az élő adatok átmenetileg nem elérhetők. A meccseket ettől még megnézheted az <a href="../../frontend/Live/live.php">Élő</a> oldalon.');
                    return;
                }

                var liveItems = [];
                var total = 0;
                for (var i = 0; i < sports.length; i++) {
                    var item = sports[i] || {};
                    var count = Number(item.match_count || 0);
                    if (count > 0) {
                        liveItems.push({ sport: item.sport_name || '', count: count });
                        total += count;
                    }
                }

                if (liveItems.length === 0) {
                    addBotMessage(getUiLocale() === 'en-US'
                        ? '⚽ There are no live matches right now. Please check back later.'
                        : '⚽ Jelenleg nincs élő meccs. Nézz vissza később!');
                    return;
                }

                var title = getUiLocale() === 'en-US' ? 'Live matches now: ' : 'Élő meccsek most: ';
                var suffix = getUiLocale() === 'en-US' ? 'matches' : 'meccs';
                var linkLabel = getUiLocale() === 'en-US' ? '🔴 View live matches ->' : '🔴 Élő meccsek megtekintése ->';

                var html = '🔴 <b>' + title + total + '</b><br><br><div class="chat-info-card">';
                liveItems.forEach(function (m) {
                    html += '<div class="chat-info-row"><span class="chat-info-label">' + td(m.sport || '') + '</span><span><b>' + m.count + '</b> ' + suffix + '</span></div>';
                });
                html += '</div><a href="../../frontend/Live/live.php" class="chat-action-link">' + linkLabel + '</a>';
                addBotMessage(html);
            })
            .catch(function () {
                addBotMessage(getUiLocale() === 'en-US'
                    ? '⚽ Live data is temporarily unavailable. You can still browse matches on the <a href="../../frontend/Live/live.php">Live</a> page.'
                    : '⚽ Az élő adatok átmenetileg nem elérhetők. A meccseket ettől még megnézheted az <a href="../../frontend/Live/live.php">Élő</a> oldalon.');
            });
    }

    function fetchLive() {
        showTyping();
        fetchFromBackend('live', function (data) {
            hideTyping();
            if (!data || !Array.isArray(data.liveMatches)) {
                fetchLiveFromSidebarFallback();
                return;
            }

            if (!data.liveMatches || data.liveMatches.length === 0) {
                addBotMessage(getUiLocale() === 'en-US'
                    ? '⚽ There are no live matches right now. Please check back later.'
                    : '⚽ Jelenleg nincs élő meccs. Nézz vissza később!');
                return;
            }
            var title = getUiLocale() === 'en-US' ? 'Live matches now: ' : 'Élő meccsek most: ';
            var suffix = getUiLocale() === 'en-US' ? 'matches' : 'meccs';
            var linkLabel = getUiLocale() === 'en-US' ? '🔴 View live matches ->' : '🔴 Élő meccsek megtekintése ->';
            var html = '🔴 <b>' + title + data.totalLive + '</b><br><br>' +
                '<div class="chat-info-card">';
            data.liveMatches.forEach(function (m) {
                html += '<div class="chat-info-row"><span class="chat-info-label">' + td(m.sport || '') + '</span><span><b>' + m.count + '</b> ' + suffix + '</span></div>';
            });
            html += '</div>' +
                '<a href="../../frontend/Live/live.php" class="chat-action-link">' + linkLabel + '</a>';
            addBotMessage(html);
        }, function () {
            hideTyping();
            fetchLiveFromSidebarFallback();
        });
    }

    function fetchSummary() {
        showTyping();
        fetchFromBackend('summary', function (data) {
            hideTyping();
            if (!data.loggedIn) {
                addBotMessage('🔒 Please sign in to view your summary.');
                return;
            }
            var html = '📋 <b>Summary</b><br><br>';
            html += '<div class="chat-info-card">';
            html += '<div class="chat-info-row"><span class="chat-info-label">💰 Balance</span><span><b>' + fmtNum(data.balance) + ' Ft</b></span></div>';
            if (data.bonusBalance > 0)
                html += '<div class="chat-info-row"><span class="chat-info-label">🎁 Bonus</span><span>' + fmtNum(data.bonusBalance) + ' Ft</span></div>';
            var s = data.ticketStats || {};
            html += '<div class="chat-info-row"><span class="chat-info-label">📊 Tickets</span><span>' + s.total + ' (✅' + s.won + ' ❌' + s.lost + ' ⏳' + s.open + ')</span></div>';
            if (data.activeBonuses && data.activeBonuses.length > 0)
                html += '<div class="chat-info-row"><span class="chat-info-label">🎁 Active bonuses</span><span>' + data.activeBonuses.length + '</span></div>';
            html += '<div class="chat-info-row"><span class="chat-info-label">🔴 Live matches</span><span>' + (data.totalLive || 0) + '</span></div>';
            html += '</div>';
            addBotMessage(html);
        });
    }

    function showCommands() {
        var isEn = getUiLocale() === 'en-US';
        if (isEn) {
            addBotMessage(
                '⌨️ <b>Available commands</b><br><br>' +
                '<div class="chat-commands-grid">' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#balance\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#balance</span><span>Your balance</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#bonus\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#bonus</span><span>Active bonuses</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#ticket\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#ticket</span><span>Latest bets</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#live\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#live</span><span>Live matches</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#stat\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#stat</span><span>Statistics</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#summary\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#summary</span><span>Full summary</span></div>' +
                '</div>'
            );
        } else {
            addBotMessage(
                '⌨️ <b>Elérhető parancsok</b><br><br>' +
                '<div class="chat-commands-grid">' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#egyenleg\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#egyenleg</span><span>Egyenleged</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#bónusz\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#bónusz</span><span>Aktív bónusz</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#szelvény\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#szelvény</span><span>Utolsó fogadások</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#élő\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#élő</span><span>Élő meccsek</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#stat\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#stat</span><span>Statisztikák</span></div>' +
                '<div class="chat-cmd" onclick="document.getElementById(\'chatbotInput\').value=\'#összegzés\';document.getElementById(\'chatbotSend\').click();"><span class="chat-cmd-code">#összegzés</span><span>Teljes összegzés</span></div>' +
                '</div>'
            );
        }
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
            '🤔 I do not have an exact answer for that yet. Try rephrasing, or type <b>#commands</b>.',
            '😅 I did not fully understand that. Ask about betting, bonuses, or try <b>#balance</b>.',
            '🤷 I do not have details on that. Check the <a href="../../frontend/Help/GYIK.php">FAQ</a> page, or type <b>#help</b>.',
            '💡 No direct match found. Try <b>#commands</b> to see what I can do.'
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
                addBotMessage(t('chatbot.welcomeHtml',
                    '👋 <b>Welcome to BetMatchBonus!</b><br><br>' +
                    'I am your <b>BMB Assistant</b> - here to help you navigate.<br><br>' +
                    'Ask anything, or type <b>#commands</b> to view my features. 👇'
                ));
                // Kontextus-specifikus tipp
                var tip = getContextTip(pageContext);
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
            addBotMessage(t('chatbot.chatCleared', '🔄 Beszélgetés törölve! Miben segíthetek? 😊'));
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
            var restoredText = saved[i].sender === 'bot' ? localizeBotText(saved[i].text) : saved[i].text;
            messagesEl.insertAdjacentHTML('beforeend', createMessageHTML(restoredText, saved[i].sender));
        }
        chatHistory = saved;
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return true;
    }

    function rerenderChatHistoryForCurrentLanguage() {
        var messagesEl = getEl('chatbotMessages');
        if (!messagesEl || !chatHistory || chatHistory.length === 0) return;
        messagesEl.innerHTML = '';
        for (var i = 0; i < chatHistory.length; i++) {
            var item = chatHistory[i];
            var renderText = item.sender === 'bot' ? localizeBotText(item.text) : item.text;
            messagesEl.insertAdjacentHTML('beforeend', createMessageHTML(renderText, item.sender));
        }
        messagesEl.scrollTop = messagesEl.scrollHeight;
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
                var isEnglish = getUiLocale() === 'en-US';
                var question = this.getAttribute(isEnglish ? 'data-question-en' : 'data-question-hu') || this.getAttribute('data-question');
                if (question) {
                    if (!isOpen) openChat();
                    setTimeout(function () { handleUserMessage(question); }, 350);
                }
            });
        }

        setTimeout(showStartupPopup, 900);

        window.addEventListener('languageChanged', function () {
            rerenderChatHistoryForCurrentLanguage();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
