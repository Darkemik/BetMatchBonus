const fs = require('fs');
const f = 'c:/xampp/htdocs/BetMatchBonus/frontend/Components/header.php';
let c = fs.readFileSync(f, 'utf8');

c = c.replace(/>Főoldal<\/a>/g, '><span data-i18n="nav.home">Főoldal</span></a>');
c = c.replace(/>Élő<\/a>/g, '><span data-i18n="nav.live">Élő</span></a>');
c = c.replace(/>eSport<\/a>/g, '><span data-i18n="nav.esport">eSport</span></a>');
c = c.replace(/>Bónuszok<\/a>/g, '><span data-i18n="nav.bonuses">Bónuszok</span></a>');
c = c.replace(/>Segítség<\/a>/g, '><span data-i18n="nav.help">Segítség</span></a>');

fs.writeFileSync(f, c, 'utf8');
console.log('header.php updated');
