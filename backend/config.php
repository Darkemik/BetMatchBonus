<?php
/**
 * CONFIG.PHP - Közös konfiguráció az egész backend számára
 * 
 * Minden konstans EGY HELYEN van definiálva.
 * Használat: require_once __DIR__ . '/config.php';
 */

require_once __DIR__ . '/env_loader.php';

// ── API ──────────────────────────────────────────
define('API_BASE_URL', getenv('API_BASE_URL') ?: 'http://localhost:5000');

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

// ── eSport játék tag-ek (game_tag) ───────────────
// Kulcsszó-térkép: bajnokságnév alapján azonosítja az esport játékot.
// A kulcsszavakat kisbetűvel kell megadni; a keresés case-insensitive.
const ESPORT_GAME_TAGS = [
    'lol' => [
        'name' => 'League of Legends',
        'icon' => 'fa-hat-wizard',
        'keywords' => ['league of legends', 'lol ', ' lol', 'lck', 'lec ', 'lcs ', 'lpl ', 'lcp ',
                        'cblol', 'lck cl', 'garena challenger', 'lms ', 'ljl ', 'pcs ', 'nacl',
                        'worlds ', ' worlds', 'msi '],
    ],
    'cs' => [
        'name' => 'Counter-Strike',
        'icon' => 'fa-crosshairs',
        'keywords' => ['counter-strike', 'counter strike', 'cs2', 'csgo', 'cs:go', ' cct ',
                        'intel extreme masters', 'iem ', 'esl pro', 'blast ', 'elisa ',
                        'european pro league', 'dust2', 'nodwin clutch', 'parken challenger',
                        'mad dogs', 'conquest of'],
    ],
    'valorant' => [
        'name' => 'Valorant',
        'icon' => 'fa-shield-alt',
        'keywords' => ['valorant', 'vct ', 'challengers 2026'],
    ],
    'dota2' => [
        'name' => 'Dota 2',
        'icon' => 'fa-dragon',
        'keywords' => ['dota', 'dpc ', 'the international'],
    ],
    'ow' => [
        'name' => 'Overwatch',
        'icon' => 'fa-robot',
        'keywords' => ['overwatch'],
    ],
    'fortnite' => [
        'name' => 'Fortnite',
        'icon' => 'fa-campground',
        'keywords' => ['fortnite'],
    ],
    'apex' => [
        'name' => 'Apex Legends',
        'icon' => 'fa-skull-crossbones',
        'keywords' => ['apex legends'],
    ],
    'ml' => [
        'name' => 'Mobile Legends',
        'icon' => 'fa-mobile-alt',
        'keywords' => ['mpl ', 'mobile legends'],
    ],
];

/**
 * Bajnokságnév alapján game_tag meghatározása.
 * Ha nincs találat, null-t ad vissza (→ "Egyéb" kategória).
 */
function resolveGameTag(string $competitionName): ?string {
    $lower = ' ' . mb_strtolower($competitionName) . ' ';
    foreach (ESPORT_GAME_TAGS as $tag => $config) {
        foreach ($config['keywords'] as $kw) {
            if (strpos($lower, $kw) !== false) {
                return $tag;
            }
        }
    }
    return null;
}

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
                                -- Csak a sima Bundesliga legyen elöl, a többi Bundesliga variáns hátra.
                                WHEN LOWER(TRIM(comp.name)) LIKE '%bundesliga 2%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%2. bundesliga%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%bundesliga ii%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%bundesliga 3%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%3. bundesliga%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%bundesliga iii%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%regionalliga%'
                                    OR LOWER(TRIM(comp.name)) LIKE '%northern premier league%'                 THEN 97

                -- Kiemelt TOP3 sorrend:
                -- 1) Premier League  2) LaLiga  3) Serie A
                -- (mindig ez legyen elöl a főoldalon)
                WHEN LOWER(TRIM(comp.name)) = 'premier league'
                    AND (
                                c.name IS NULL
                                OR LOWER(TRIM(c.name)) IN ('england', 'anglia', 'eng', 'united kingdom', 'great britain', 'uk')
                            )                                                              THEN 1
                WHEN LOWER(TRIM(comp.name)) IN ('la liga', 'laliga')                        THEN 2
                WHEN LOWER(TRIM(comp.name)) LIKE 'serie a%'                                 THEN 3
                                WHEN LOWER(TRIM(comp.name)) = 'bundesliga'                                  THEN 4
                WHEN LOWER(TRIM(comp.name)) = 'ligue 1'                                      THEN 5
                WHEN LOWER(TRIM(comp.name)) IN ('fizz liga', 'fizz league')                  THEN 6
                WHEN LOWER(TRIM(comp.name)) IN ('nb i', 'nb1', 'otp bank liga')             THEN 7

                -- Kifejezett tiltólista: ezek ne jöjjenek a Premier League elé.
                WHEN comp.name LIKE '%Northern Premier League%'                      THEN 96

                -- Női sorozatok ne kerüljenek a fő élmezőnybe kulcsszavas egyezés miatt.
                WHEN comp.name LIKE '%Női%'
                    OR comp.name LIKE '%Women%'
                    OR comp.name LIKE '%(N)%'                                         THEN 90

                -- Nemzetközi top sorrend (a megadott lista szerint)
                WHEN comp.name LIKE '%COSAFA Cup%'                                  THEN 7
                WHEN comp.name LIKE '%Copa America%'                                THEN 8
                WHEN comp.name LIKE '%Európa-bajnokság%'
                    OR comp.name LIKE '%UEFA EURO%'
                    OR comp.name LIKE '%Euro 20%'
                    OR comp.name LIKE '%Euro %'                                       THEN 9
                WHEN comp.name LIKE '%Women%Champions League%'
                    OR comp.name LIKE '%Women''s Champions League%'
                    OR comp.name LIKE '%Női%Bajnokok Ligája%'
                    OR comp.name LIKE '%Női%Champions League%'                        THEN 70
                WHEN comp.name LIKE '%Bajnokok Ligája%'
                    OR comp.name LIKE '%Champions League%'                            THEN 10
                WHEN comp.name LIKE '%Európa-liga%'
                    OR comp.name LIKE '%Európa Liga%'
                    OR comp.name LIKE '%Europa League%'                               THEN 11
                WHEN comp.name LIKE '%Konferencia Liga%'
                    OR comp.name LIKE '%Conference League%'                           THEN 12
                WHEN comp.name LIKE '%Nemzetek Ligája%'
                    OR comp.name LIKE '%Nations League%'                              THEN 13
                WHEN comp.name LIKE '%UEFA Szuperkupa%'
                    OR comp.name LIKE '%UEFA Super Cup%'                              THEN 14
                WHEN comp.name LIKE '%Olimpiai Játékok%'
                    OR comp.name LIKE '%Olympic Games%'                               THEN 15
                WHEN comp.name LIKE '%FIFA Klub-vb%'
                    OR comp.name LIKE '%FIFA Club World Cup%'                         THEN 16
                WHEN comp.name LIKE '%Finalissima%'                                 THEN 17
                WHEN comp.name LIKE '%Világbajnokság%'
                    OR comp.name LIKE '%World Cup%'
                    OR comp.name LIKE '% VB%'
                    OR comp.name LIKE 'VB%'
                    OR comp.name LIKE '%VB %'                                         THEN 18

                -- Anglia
                WHEN comp.name LIKE '%Championship%'                                THEN 20
                WHEN comp.name LIKE '%FA Cup%'                                      THEN 21
                WHEN comp.name LIKE '%EFL Cup%'
                    OR comp.name LIKE '%Carabao Cup%'                                 THEN 22

                -- Magyarország
                WHEN comp.name LIKE '%NB I%'
                    OR comp.name LIKE '%NB1%'
                    OR comp.name LIKE '%Nemzeti Bajnokság I%'
                    OR comp.name LIKE '%OTP Bank Liga%'                               THEN 24
                WHEN comp.name LIKE '%NB II%'
                    OR comp.name LIKE '%NB2%'
                    OR comp.name LIKE '%Nemzeti Bajnokság II%'
                    OR comp.name LIKE '%Második osztály%'                             THEN 25
                WHEN comp.name LIKE '%MOL Magyar Kupa%'
                    OR comp.name LIKE '%Magyar Kupa%'                                 THEN 26

                -- Spanyolország
                WHEN comp.name LIKE '%La Liga%'
                    OR comp.name LIKE '%LaLiga%'                                      THEN 27
                WHEN comp.name LIKE '%Copa del Rey%'                                THEN 28
                WHEN comp.name LIKE '%Spanyol Szuperkupa%'
                    OR comp.name LIKE '%Supercopa%'                                   THEN 29

                -- Olaszország
                WHEN comp.name LIKE '%Serie A%'                                     THEN 30
                WHEN comp.name LIKE '%Coppa Italia%'                                THEN 31
                WHEN comp.name LIKE '%Olasz Szuperkupa%'
                    OR comp.name LIKE '%Supercoppa%'                                  THEN 32

                -- Németország
                WHEN LOWER(TRIM(comp.name)) = 'bundesliga'                          THEN 33
                WHEN comp.name LIKE '%Német Kupa%'
                    OR comp.name LIKE '%DFB-Pokal%'                                   THEN 34

                -- Franciaország
                WHEN comp.name LIKE '%Ligue 1%'                                     THEN 35
                WHEN comp.name LIKE '%Francia Kupa%'
                    OR comp.name LIKE '%Coupe de France%'                             THEN 36

                -- Portugália
                WHEN comp.name LIKE '%Liga Portugal%'
                    OR comp.name LIKE '%Primeira Liga%'                               THEN 37

                -- Törökország
                WHEN comp.name LIKE '%Super Lig%'
                    OR comp.name LIKE '%Süper Lig%'                                   THEN 38

                -- Görögország
                WHEN comp.name LIKE '%Szuperliga%'
                    OR comp.name LIKE '%Super League Greece%'                         THEN 39

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
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
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
define('RECAPTCHA_SITE_KEY',   getenv('RECAPTCHA_SITE_KEY')   ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');
define('RECAPTCHA_THRESHOLD',  (float)(getenv('RECAPTCHA_THRESHOLD') ?: 0.5));
