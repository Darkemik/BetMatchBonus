const fs = require('fs');

// 1. helpheader.php - nav links
const hf = 'c:/xampp/htdocs/BetMatchBonus/frontend/Components/helpheader.php';
let h = fs.readFileSync(hf, 'utf8');
h = h.replace('>Főoldal</a>', '><span data-i18n="nav.home">Főoldal</span></a>');
h = h.replace('>Élő</a>', '><span data-i18n="nav.live">Élő</span></a>');
h = h.replace('>eSport</a>', '><span data-i18n="nav.esport">eSport</span></a>');
h = h.replace('>Bónuszok</a>', '><span data-i18n="nav.bonuses">Bónuszok</span></a>');
h = h.replace('>Segítség</a>', '><span data-i18n="nav.help">Segítség</span></a>');
fs.writeFileSync(hf, h, 'utf8');
console.log('✔ helpheader.php updated');

// 2. login.php - auth buttons and menu items
const lf = 'c:/xampp/htdocs/BetMatchBonus/frontend/Components/login.php';
let l = fs.readFileSync(lf, 'utf8');
// Only add if not already there
if (!l.includes('data-i18n="auth.login"')) {
    l = l.replace('class="loginbtn"', 'class="loginbtn" data-i18n="auth.login"');
    l = l.replace('class="registrationbtn"', 'class="registrationbtn" data-i18n="auth.register"');
    // Menu items with icons - wrap text in spans
    l = l.replace(/<i class="fas fa-user"><\/i> Személyes Adatok/g, '<i class="fas fa-user"></i> <span data-i18n="auth.personalData">Személyes Adatok</span>');
    l = l.replace(/<i class="fas fa-key"><\/i> Jelszó Módosítás/g, '<i class="fas fa-key"></i> <span data-i18n="auth.changePassword">Jelszó Módosítás</span>');
    l = l.replace(/<i class="fas fa-plus-circle"><\/i> Befizetés/g, '<i class="fas fa-plus-circle"></i> <span data-i18n="auth.deposit">Befizetés</span>');
    l = l.replace(/<i class="fas fa-minus-circle"><\/i> Kifizetés/g, '<i class="fas fa-minus-circle"></i> <span data-i18n="auth.withdrawal">Kifizetés</span>');
    l = l.replace(/<i class="fas fa-history"><\/i> Tranzakciótörténet/g, '<i class="fas fa-history"></i> <span data-i18n="auth.transactionHistory">Tranzakciótörténet</span>');
    l = l.replace(/<i class="fas fa-gift"><\/i> Bónuszaim/g, '<i class="fas fa-gift"></i> <span data-i18n="auth.myBonuses">Bónuszaim</span>');
    l = l.replace(/<i class="fas fa-list"><\/i> Napló/g, '<i class="fas fa-list"></i> <span data-i18n="auth.activityLog">Napló</span>');
    l = l.replace(/<i class="fas fa-sign-out-alt"><\/i> Kijelentkezés/g, '<i class="fas fa-sign-out-alt"></i> <span data-i18n="auth.logout">Kijelentkezés</span>');
    fs.writeFileSync(lf, l, 'utf8');
    console.log('✔ login.php updated');
} else {
    console.log('(skip) login.php - already has data-i18n');
}

console.log('\n✅ Done!');
