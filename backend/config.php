<?php
/**
 * CONFIG.PHP - Közös konfiguráció az egész backend számára
 * 
 * Minden konstans EGY HELYEN van definiálva.
 * Használat: require_once __DIR__ . '/config.php';
 */

// ── API ──────────────────────────────────────────
const API_BASE_URL = 'http://localhost:5000';

// API végpontok
const EP_SPORTS_LIST        = '/api/sports';
const EP_CHAMPIONSHIPS      = '/api/sports/championships';
const EP_MATCHES_LIVE       = '/api/matches/live';
const EP_MATCHES_DATE       = '/api/matches/date';
const EP_MATCH_DETAILS      = '/api/matches'; // + /{eventId}

// ── Sport ikonok (FontAwesome) ───────────────────
const SPORT_ICONS = [
    66  => 'fa-futbol',
    67  => 'fa-basketball-ball',
    68  => 'fa-baseball-ball',
    69  => 'fa-football-ball',
    70  => 'fa-hockey-puck',
    71  => 'fa-volleyball-ball',
    72  => 'fa-golf-ball',
    73  => 'fa-hand-rock',
    74  => 'fa-fist-raised',
    75  => 'fa-biking',
    76  => 'fa-running',
    77  => 'fa-table-tennis',
    78  => 'fa-bullseye',
    79  => 'fa-skiing',
    80  => 'fa-snowflake',
    83  => 'fa-swimmer',
    84  => 'fa-table-tennis',
    85  => 'fa-chess',
    90  => 'fa-hockey-puck',
    109 => 'fa-volleyball-ball',
    110 => 'fa-futbol',
    138 => 'fa-running',
    145 => 'fa-gamepad',
    146 => 'fa-futbol',
    147 => 'fa-basketball-ball',
    148 => 'fa-hockey-puck',
    151 => 'fa-trophy',
];

// ── Sport nevek (magyar) ─────────────────────────
const SPORT_NAMES = [
    66  => 'Labdarúgás',
    67  => 'Kosárlabda',
    68  => 'Baseball',
    69  => 'Amerikai foci',
    70  => 'Jégkorong',
    71  => 'Röplabda',
    72  => 'Golf',
    73  => 'Kézilabda',
    74  => 'MMA',
    75  => 'Kerékpár',
    76  => 'Futsal',
    77  => 'Pingpong',
    78  => 'Darts',
    79  => 'Síelés',
    80  => 'Téli sport',
    83  => 'Vízilabda',
    84  => 'Badminton',
    85  => 'Sakk',
    90  => 'Floorball',
    109 => 'Strandröplabda',
    110 => 'Futsal',
    138 => 'Krikett',
    145 => 'E-sportok',
    146 => 'e-Labdarúgás',
    147 => 'e-Kosárlabda',
    148 => 'e-Jégkorong',
    151 => 'Snooker',
];

// ── Ország kódok → magyar nevek ──────────────────
const COUNTRY_MAP = [
    'INT' => 'Nemzetközi',
    'HUN' => 'Magyarország',
    'GBR' => 'Egyesült Királyság',
    'DEU' => 'Németország',
    'FRA' => 'Franciaország',
    'ESP' => 'Spanyolország',
    'ITA' => 'Olaszország',
    'PRT' => 'Portugália',
    'NLD' => 'Hollandia',
    'BEL' => 'Belgium',
    'AUT' => 'Ausztria',
    'CHE' => 'Svájc',
    'POL' => 'Lengyelország',
    'CZE' => 'Csehország',
    'SVK' => 'Szlovákia',
    'HRV' => 'Horvátország',
    'SRB' => 'Szerbia',
    'ROU' => 'Románia',
    'BGR' => 'Bulgária',
    'GRC' => 'Görögország',
    'TUR' => 'Törökország',
    'RUS' => 'Oroszország',
    'UKR' => 'Ukrajna',
    'SWE' => 'Svédország',
    'NOR' => 'Norvégia',
    'DNK' => 'Dánia',
    'FIN' => 'Finnország',
    'ISL' => 'Izland',
    'IRL' => 'Írország',
    'USA' => 'Egyesült Államok',
    'CAN' => 'Kanada',
    'MEX' => 'Mexikó',
    'BRA' => 'Brazília',
    'ARG' => 'Argentína',
    'JPN' => 'Japán',
    'KOR' => 'Dél-Korea',
    'CHN' => 'Kína',
    'AUS' => 'Ausztrália',
    'NZL' => 'Új-Zéland',
    'ZAF' => 'Dél-Afrika',
    'EGY' => 'Egyiptom',
    'MAR' => 'Marokkó',
    'IND' => 'India',
    'ARE' => 'Egyesült Arab Emírségek',
    'QAT' => 'Katar',
    'SAU' => 'Szaúd-Arábia',
    'ISR' => 'Izrael',
    'ALB' => 'Albánia',
    'SVN' => 'Szlovénia',
    'BIH' => 'Bosznia-Hercegovina',
    'MNE' => 'Montenegró',
    'MKD' => 'Észak-Macedónia',
    'LTU' => 'Litvánia',
    'LVA' => 'Lettország',
    'EST' => 'Észtország',
    'ABW' => 'Aruba',
];

// ── Bajnokság prioritás (mainmenu rendezéshez) ───
const LEAGUE_PRIORITY_SQL = "
    CASE
        WHEN comp.name LIKE '%Világbajnokság%'
          OR comp.name LIKE '%World Cup%'
          OR comp.name LIKE '%VB%'                  THEN 1
        WHEN comp.name LIKE '%Nemzetek Ligája%'
          OR comp.name LIKE '%Nations League%'      THEN 2
        WHEN comp.name LIKE '%Európa-bajnokság%'
          OR comp.name LIKE '%Euro 20%'
          OR comp.name LIKE '%UEFA EURO%'            THEN 3
        WHEN comp.name LIKE '%Champions League%'
          OR comp.name LIKE '%Bajnokok Ligája%'     THEN 4
        WHEN comp.name LIKE '%Europa League%'
          OR comp.name LIKE '%Európa Liga%'         THEN 5
        WHEN comp.name LIKE '%Conference League%'
          OR comp.name LIKE '%Konferencia Liga%'    THEN 6
        WHEN comp.name LIKE '%NB I%'
          OR comp.name LIKE '%NB1%'
          OR comp.name LIKE '%Nemzeti Bajnokság%'
          OR comp.name LIKE '%OTP Bank Liga%'       THEN 7
        WHEN comp.name LIKE '%Premier League%'      THEN 8
        WHEN comp.name LIKE '%La Liga%'
          OR comp.name LIKE '%LaLiga%'              THEN 9
        WHEN comp.name LIKE '%Bundesliga%'          THEN 10
        WHEN comp.name LIKE '%Serie A%'             THEN 11
        WHEN comp.name LIKE '%Ligue 1%'             THEN 12
        WHEN comp.name LIKE '%NB II%'
          OR comp.name LIKE '%NB2%'
          OR comp.name LIKE '%Második osztály%'     THEN 13
        WHEN comp.name LIKE '%Eredivisie%'          THEN 14
        WHEN comp.name LIKE '%Primeira Liga%'       THEN 15
        ELSE 99
    END
";

// ── Befejezettség jelző kulcsszavak ──────────────
const FINISHED_KEYWORDS = [
    'ended', 'finished', 'final', 'ft', 'aet', 'ap', 'closed',
    'retired', 'walkover', 'cancelled', 'abandoned',
    'after penalties', 'after extra time', 'full-time', 'result'
];

// ── Segédfüggvények ──────────────────────────────

/**
 * API GET kérés egyszerűsítve
 */
function apiGet(string $path, array $query = []): array {
    $url = rtrim(API_BASE_URL, '/') . '/' . ltrim($path, '/');
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException("API hiba (cURL): {$err}");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("API HTTP {$code} | {$url}");
    }

    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        throw new RuntimeException("API JSON parse hiba | {$url}");
    }
    return $json;
}

/**
 * Országkód → magyar név
 */
function countryNameFromCode(string $code): string {
    $code = strtoupper(trim($code));
    if ($code === '') return 'Nemzetközi';
    return COUNTRY_MAP[$code] ?? $code;
}

/**
 * Sport ikon lekérése api_id alapján
 */
function getSportIcon(int $sportApiId): string {
    return SPORT_ICONS[$sportApiId] ?? 'fa-trophy';
}

// ── reCAPTCHA v3 ─────────────────────────────────
// Regisztrálj: https://www.google.com/recaptcha/admin
// Válaszd a "reCAPTCHA v3" típust, add meg a domained (pl. localhost fejlesztéshez).
const RECAPTCHA_SITE_KEY   = '6LfORq0sAAAAAH0CyNKWMZjml_GOXh6svvG-pEUR';
const RECAPTCHA_SECRET_KEY = '6LfORq0sAAAAAJXQGlXBwOHQ9QILiZ-7hDLnI5zO';
const RECAPTCHA_THRESHOLD  = 0.5; // 0.0–1.0, alatta bot-nak minősül
