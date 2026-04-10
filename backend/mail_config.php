<?php
/**
 * Email konfiguráció – Gmail SMTP (PHPMailer)
 *
 * FONTOS: A Gmail fiókban be kell kapcsolni a 2-lépéses azonosítást,
 * majd létre kell hozni egy „Alkalmazásjelszót":
 *   Google Fiók → Biztonság → Kétlépcsős azonosítás → Alkalmazásjelszavak
 *   Alkalmazás: „Egyéb (egyéni név)" → generálás → a 16 karakteres jelszó ide kell.
 */

define('MAIL_SMTP_HOST',     'smtp.gmail.com');
define('MAIL_SMTP_PORT',     587);
define('MAIL_SMTP_USERNAME', 'bmbugyfelszolgalat@gmail.com');
define('MAIL_SMTP_PASSWORD', 'ocjm nxmo rsyr bkqk');  // 16 karakteres Google alkalmazásjelszó
define('MAIL_FROM_EMAIL',    'bmbugyfelszolgalat@gmail.com');
define('MAIL_FROM_NAME',     'BetMatchBonus');

// Az oldal alap URL-je (jóváhagyó link generáláshoz)
define('SITE_BASE_URL',      'http://localhost/BetMatchBonus');
