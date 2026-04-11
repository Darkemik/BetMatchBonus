<?php
/**
 * Email konfiguráció – Gmail SMTP (PHPMailer)
 *
 * FONTOS: A Gmail fiókban be kell kapcsolni a 2-lépéses azonosítást,
 * majd létre kell hozni egy „Alkalmazásjelszót":
 *   Google Fiók → Biztonság → Kétlépcsős azonosítás → Alkalmazásjelszavak
 *   Alkalmazás: „Egyéb (egyéni név)" → generálás → a 16 karakteres jelszó ide kell.
 */

require_once __DIR__ . '/env_loader.php';

define('MAIL_SMTP_HOST',     getenv('MAIL_SMTP_HOST')     ?: 'smtp.gmail.com');
define('MAIL_SMTP_PORT',     (int)(getenv('MAIL_SMTP_PORT') ?: 587));
define('MAIL_SMTP_USERNAME', getenv('MAIL_SMTP_USERNAME') ?: '');
define('MAIL_SMTP_PASSWORD', getenv('MAIL_SMTP_PASSWORD') ?: '');
define('MAIL_FROM_EMAIL',    getenv('MAIL_FROM_EMAIL')    ?: '');
define('MAIL_FROM_NAME',     getenv('MAIL_FROM_NAME')     ?: 'BetMatchBonus');

// Az oldal alap URL-je (jóváhagyó link generáláshoz)
define('SITE_BASE_URL',      getenv('SITE_BASE_URL')      ?: 'http://localhost/BetMatchBonus');
