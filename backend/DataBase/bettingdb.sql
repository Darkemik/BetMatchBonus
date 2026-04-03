CREATE DATABASE IF NOT EXISTS betmatchbonusbeta
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_hungarian_ci;

USE betmatchbonusbeta;

-- ============================================================
-- 1) ROLES
-- ============================================================
CREATE TABLE IF NOT EXISTS Roles (
  id          INT           AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(30)   NOT NULL UNIQUE,
  description VARCHAR(150)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

INSERT INTO Roles (id, name, description) VALUES
(1, 'MOD',        'Moderátor – felhasználók áttekintése'),
(2, 'ADMIN',      'Admin – szerkesztés, bónuszok, wallet kezelés'),
(3, 'SUPERADMIN', 'Szuperadmin – teljes hozzáférés, admin kezelés');

-- ============================================================
-- 2) COUNTRIES
-- ============================================================
CREATE TABLE IF NOT EXISTS Countries (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(10)   NOT NULL UNIQUE,
  name       VARCHAR(100)  NOT NULL,
  flag_url   VARCHAR(255)  DEFAULT NULL,
  sort_order INT           DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 3) CITIES
-- ============================================================
CREATE TABLE IF NOT EXISTS Cities (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  country_id INT           NOT NULL,
  name       VARCHAR(100)  NOT NULL,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  UNIQUE KEY unique_city_country (country_id, name),
  CONSTRAINT fk_city_country FOREIGN KEY (country_id) REFERENCES Countries(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 4) SPORTS
-- ============================================================
CREATE TABLE IF NOT EXISTS Sports (
  id              INT           AUTO_INCREMENT PRIMARY KEY,
  api_id          INT           NOT NULL UNIQUE,
  name            VARCHAR(150)  NOT NULL,
  icon_url        VARCHAR(255)  DEFAULT NULL,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  sort_order      INT           DEFAULT NULL,
  has_live_events TINYINT(1)    NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 5) STATUS
-- ============================================================
CREATE TABLE IF NOT EXISTS Status (
  id   INT           AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50)   NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

INSERT INTO Status (id, name) VALUES
(1, 'NOT_STARTED'),
(2, 'LIVE'),
(3, 'FINISHED'),
(4, 'POSTPONED'),
(5, 'CANCELLED');

-- ============================================================
-- 6) WALLET TRANSACTION TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS WalletTransactionsType (
  id   INT           AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50)   NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

INSERT INTO WalletTransactionsType (id, name) VALUES
(1, 'DEPOSIT'),
(2, 'WITHDRAWAL'),
(3, 'BET'),
(4, 'WIN'),
(5, 'CASHOUT'),
(6, 'BONUS'),
(7, 'MANUAL_ADJUST');

-- ============================================================
-- 7) BONUS TYPES
-- ============================================================
CREATE TABLE IF NOT EXISTS BonusTypes (
  id   INT           AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50)   NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

INSERT INTO BonusTypes (id, name) VALUES
(1, 'WELCOME'),
(2, 'WEEKDAYS'),
(3, 'SEASONAL'),
(4, 'EVENT_SPECIFIC'),
(5, 'DATE_SPECIFIC'),
(6, 'WEEKEND'),
(7, 'ADMIN_BONUS');

-- ============================================================
-- 8) COMPETITIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS Competitions (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  api_id     INT           NOT NULL UNIQUE,
  name       VARCHAR(150)  NOT NULL,
  sport_id   INT           NOT NULL,
  country_id INT           DEFAULT NULL,
  logo_url   VARCHAR(255)  DEFAULT NULL,
  sort_order INT           DEFAULT NULL,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_comp_sport   FOREIGN KEY (sport_id)   REFERENCES Sports(id)    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_comp_country FOREIGN KEY (country_id) REFERENCES Countries(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 9) SEASONS
-- ============================================================
CREATE TABLE IF NOT EXISTS Seasons (
  id             INT           AUTO_INCREMENT PRIMARY KEY,
  competition_id INT           NOT NULL,
  name           VARCHAR(50)   DEFAULT NULL,
  start_year     SMALLINT      NOT NULL,
  end_year       SMALLINT      DEFAULT NULL,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_season_comp FOREIGN KEY (competition_id) REFERENCES Competitions(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 10) TEAMS
-- ============================================================
CREATE TABLE IF NOT EXISTS Teams (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  api_id     INT           DEFAULT NULL UNIQUE,
  name       VARCHAR(150)  NOT NULL,
  country_id INT           DEFAULT NULL,
  sport_id   INT           NOT NULL,
  logo_url   VARCHAR(255)  DEFAULT NULL,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_team_country FOREIGN KEY (country_id) REFERENCES Countries(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_team_sport   FOREIGN KEY (sport_id)   REFERENCES Sports(id)    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 11) EVENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS Events (
  id               INT           AUTO_INCREMENT PRIMARY KEY,
  api_id           INT           NOT NULL UNIQUE,
  competition_id   INT           NOT NULL,
  season_id        INT           DEFAULT NULL,
  sport_id         INT           NOT NULL,
  home_team_id     INT           DEFAULT NULL,
  away_team_id     INT           DEFAULT NULL,
  home_team_name   VARCHAR(150)  NOT NULL,
  away_team_name   VARCHAR(150)  NOT NULL,
  name             VARCHAR(255)  NOT NULL,
  start_time       DATETIME      NOT NULL,
  start_time_real  DATETIME      DEFAULT NULL,
  is_live          TINYINT(1)    NOT NULL DEFAULT 0,
  live_time        VARCHAR(20)   DEFAULT NULL,
  live_status      VARCHAR(30)   DEFAULT NULL,
  status_id        INT           NOT NULL,
  home_score       INT           DEFAULT NULL,
  away_score       INT           DEFAULT NULL,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_events_live (sport_id, is_live),
  INDEX idx_events_start (start_time),
  CONSTRAINT fk_event_comp      FOREIGN KEY (competition_id) REFERENCES Competitions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_event_season    FOREIGN KEY (season_id)      REFERENCES Seasons(id)      ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_event_sport     FOREIGN KEY (sport_id)       REFERENCES Sports(id)       ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_event_home_team FOREIGN KEY (home_team_id)   REFERENCES Teams(id)        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_event_away_team FOREIGN KEY (away_team_id)   REFERENCES Teams(id)        ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_event_status    FOREIGN KEY (status_id)      REFERENCES Status(id)       ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 12) EVENT MARKETS
-- ============================================================
CREATE TABLE IF NOT EXISTS EventMarkets (
  id             INT           AUTO_INCREMENT PRIMARY KEY,
  event_id       INT           NOT NULL,
  api_market_id  BIGINT        NOT NULL,
  type_id        INT           NOT NULL,
  name           VARCHAR(150)  NOT NULL,
  special_value  VARCHAR(30)   DEFAULT NULL,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  status         VARCHAR(20)   NOT NULL DEFAULT 'OPEN',
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_market_event (event_id),
  CONSTRAINT fk_market_event FOREIGN KEY (event_id) REFERENCES Events(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 13) ODDS OUTCOMES
-- ============================================================
CREATE TABLE IF NOT EXISTS OddsOutcomes (
  id              INT           AUTO_INCREMENT PRIMARY KEY,
  event_market_id INT           NOT NULL,
  api_outcome_id  BIGINT        NOT NULL,
  label           VARCHAR(150)  NOT NULL,
  odds            DECIMAL(8,4)  NOT NULL,
  role            TINYINT       DEFAULT NULL,
  status          VARCHAR(20)   NOT NULL DEFAULT 'OPEN',
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_outcome_market (event_market_id),
  CONSTRAINT fk_outcome_market FOREIGN KEY (event_market_id) REFERENCES EventMarkets(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 14) USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS Users (
  id                  INT           AUTO_INCREMENT PRIMARY KEY,
  username            VARCHAR(50)   NOT NULL UNIQUE,
  email               VARCHAR(150)  NOT NULL UNIQUE,
  password_hash       VARCHAR(255)  NOT NULL,
  full_name           VARCHAR(150)  DEFAULT NULL,
  pre_name            VARCHAR(100)  DEFAULT NULL,
  family_name         VARCHAR(100)  DEFAULT NULL,
  sure_name           VARCHAR(100)  DEFAULT NULL,
  mother_full_name    VARCHAR(150)  DEFAULT NULL,
  birthplace          VARCHAR(150)  DEFAULT NULL,
  birth_date          DATE          NOT NULL,
  mobile_number       VARCHAR(20)   DEFAULT NULL,
  phone               VARCHAR(20)   DEFAULT NULL,
  country             VARCHAR(100)  DEFAULT NULL,
  city                VARCHAR(100)  DEFAULT NULL,
  postal_code         VARCHAR(20)   DEFAULT NULL,
  address             VARCHAR(255)  DEFAULT NULL,
  id_image_first      VARCHAR(255)  DEFAULT NULL,
  id_image_second     VARCHAR(255)  DEFAULT NULL,
  address_image       VARCHAR(255)  DEFAULT NULL,
  balance             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  winnings_balance    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  bonus_balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_verified         TINYINT(1)    NOT NULL DEFAULT 0,
  is_active           TINYINT(1)    NOT NULL DEFAULT 1,
  remember_token      VARCHAR(64)   DEFAULT NULL,
  remember_expiry     DATETIME      DEFAULT NULL,
  reset_token         VARCHAR(64)   DEFAULT NULL,
  reset_token_expiry  DATETIME      DEFAULT NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;
-- ============================================================
-- 15) USER SESSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS UserSessions (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  user_id    INT           NOT NULL,
  token      VARCHAR(255)  NOT NULL UNIQUE,
  expires_at DATETIME      NOT NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES Users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 16) USER LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS UserLogs (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL,
  action       VARCHAR(100)  NOT NULL,
  related_type VARCHAR(20)   DEFAULT NULL,
  related_id   INT           DEFAULT NULL,
  details      VARCHAR(255)  DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_userlog_user (user_id),
  CONSTRAINT fk_userlog_user FOREIGN KEY (user_id) REFERENCES Users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 17) NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS Notifications (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  user_id      INT           NOT NULL,
  title        VARCHAR(100)  NOT NULL,
  message      VARCHAR(255)  NOT NULL,
  type         VARCHAR(30)   NOT NULL,
  is_read      TINYINT(1)    NOT NULL DEFAULT 0,
  related_type VARCHAR(20)   DEFAULT NULL,
  related_id   INT           DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notif_user (user_id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES Users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 18) WALLETS
-- ============================================================
CREATE TABLE IF NOT EXISTS Wallets (
  id            INT           AUTO_INCREMENT PRIMARY KEY,
  user_id       INT           NOT NULL UNIQUE,
  balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  locked_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_wallet_user FOREIGN KEY (user_id) REFERENCES Users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 19) WALLET TRANSACTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS WalletTransactions (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  wallet_id    INT           NOT NULL,
  amount       DECIMAL(12,2) NOT NULL,
  type_id      INT           NOT NULL,
  related_type VARCHAR(20)   DEFAULT NULL,
  related_id   INT           DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_wtx_wallet (wallet_id),
  CONSTRAINT fk_wtx_wallet FOREIGN KEY (wallet_id) REFERENCES Wallets(id)                ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_wtx_type   FOREIGN KEY (type_id)   REFERENCES WalletTransactionsType(id)  ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 20) TICKETS
-- ============================================================
CREATE TABLE IF NOT EXISTS Tickets (
  id              INT           AUTO_INCREMENT PRIMARY KEY,
  user_id         INT           NOT NULL,
  stake           DECIMAL(10,2) NOT NULL,
  total_odds      DECIMAL(10,3) NOT NULL,
  potential_win   DECIMAL(12,2) NOT NULL,
  status          VARCHAR(20)   NOT NULL DEFAULT 'OPEN',
  cashout_amount  DECIMAL(12,2) DEFAULT NULL,
  cashout_at      DATETIME      DEFAULT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ticket_user (user_id),
  CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES Users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 21) TICKET SELECTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS TicketSelections (
  id             INT           AUTO_INCREMENT PRIMARY KEY,
  ticket_id      INT           NOT NULL,
  outcome_id     INT           DEFAULT NULL,
  event_id       INT           DEFAULT NULL,
  match_id       INT           DEFAULT NULL         COMMENT 'API match ID (ha event_id nincs)',
  home_team      VARCHAR(150)  DEFAULT NULL,
  away_team      VARCHAR(150)  DEFAULT NULL,
  pick_label     VARCHAR(150)  DEFAULT NULL,
  market_name    VARCHAR(200)  DEFAULT NULL,
  odds_at_pick   DECIMAL(8,4)  NOT NULL,
  status         VARCHAR(20)   NOT NULL DEFAULT 'OPEN',
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tsel_ticket  FOREIGN KEY (ticket_id)  REFERENCES Tickets(id)      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_tsel_outcome FOREIGN KEY (outcome_id) REFERENCES OddsOutcomes(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_tsel_event   FOREIGN KEY (event_id)   REFERENCES Events(id)       ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 22) BONUS CODES
-- ============================================================
CREATE TABLE IF NOT EXISTS BonusCodes (
  id                   INT           AUTO_INCREMENT PRIMARY KEY,
  code                 VARCHAR(50)   DEFAULT NULL UNIQUE,
  name                 VARCHAR(150)  NOT NULL,
  description          VARCHAR(255)  DEFAULT NULL,
  bonus_type_id        INT           NOT NULL,
  bonus_amount         DECIMAL(10,2) NOT NULL,
  min_deposit          DECIMAL(10,2) DEFAULT NULL,
  max_bonus_amount     DECIMAL(10,2) DEFAULT NULL     COMMENT 'max kifizethetó bónusz összeg',
  match_percent        DECIMAL(5,2)  DEFAULT NULL     COMMENT 'pl. 100.00 = 100% feltöltési bónusz',
  bet_reward_type      VARCHAR(20)   DEFAULT NULL     COMMENT 'FREE_BET, BONUS_MONEY, MIXED',
  bonus_trigger        VARCHAR(20)   DEFAULT NULL     COMMENT 'DEPOSIT, BET, MANUAL, AUTO',
  sport_restriction    VARCHAR(50)   DEFAULT NULL     COMMENT 'pl. DARTS, ESPORT, ANY',
  live_only            TINYINT(1)    NOT NULL DEFAULT 0,
  min_odds             DECIMAL(5,2)  DEFAULT NULL,
  min_combo            INT           DEFAULT NULL     COMMENT 'minimális kötésszám',
  min_odds_per_event   DECIMAL(5,2)  DEFAULT NULL     COMMENT 'eseményenkénti min odds',
  wagering_multiplier  DECIMAL(5,2)  DEFAULT NULL     COMMENT 'forgatási követelmény (pl. 3.0)',
  max_win_multiplier   DECIMAL(5,2)  DEFAULT 5.00     COMMENT 'bónusz összeg max Nx nyerhetó',
  evaluate_on_settle   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'true = csak lezáráskor értékel',
  is_step_bonus        TINYINT(1)    NOT NULL DEFAULT 0,
  parent_bonus_id      INT           DEFAULT NULL     COMMENT 'FK → BonusCodes.id (lépcsós bónusz)',
  step_number          INT           DEFAULT NULL     COMMENT 'hanyadik lépcső',
  valid_weekdays_only  TINYINT(1)    NOT NULL DEFAULT 0,
  daily_start_time TIME DEFAULT NULL COMMENT 'pl. 10:00-tól aktiválható',
  activation_expire_hours INT DEFAULT NULL COMMENT 'aktiválás után hány óráig érvényes',
  specific_date        DATE          DEFAULT NULL     COMMENT 'adott napi bónusz',
  advent_week          INT           DEFAULT NULL     COMMENT 'adventi bónusznál 1-4',
  birthday_bonus       TINYINT(1)    NOT NULL DEFAULT 0,
  auto_assign          TINYINT(1)    NOT NULL DEFAULT 0,
  usage_limit          INT           DEFAULT NULL,
  per_user_limit       INT           DEFAULT 1,
  valid_from           DATETIME      DEFAULT NULL,
  valid_to             DATETIME      DEFAULT NULL,
  is_active            TINYINT(1)    NOT NULL DEFAULT 1,
  created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bonus_type   FOREIGN KEY (bonus_type_id)   REFERENCES BonusTypes(id)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_bonus_parent FOREIGN KEY (parent_bonus_id) REFERENCES BonusCodes(id)  ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 23) USER BONUSES
-- ============================================================
CREATE TABLE IF NOT EXISTS UserBonuses (
  id                  INT           AUTO_INCREMENT PRIMARY KEY,
  user_id             INT           NOT NULL,
  bonus_id            INT           NOT NULL,
  ticket_id           INT           DEFAULT NULL,
  step_index          INT           DEFAULT NULL     COMMENT 'lépcsós bónusznál hanyadik lépés',
  status              VARCHAR(20)   NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, ACTIVE, COMPLETED, FAILED, EXPIRED',
  granted_amount      DECIMAL(10,2) NOT NULL,
  free_bet_amount     DECIMAL(10,2) DEFAULT NULL,
  bonus_money_amount  DECIMAL(10,2) DEFAULT NULL,
  max_win_amount      DECIMAL(10,2) DEFAULT NULL     COMMENT 'max nyerhetó összeg (5x cap)',
  wagering_required   DECIMAL(10,2) DEFAULT NULL     COMMENT 'szükséges forgatás (snapshot)',
  wagering_progress   DECIMAL(10,2) DEFAULT 0.00     COMMENT 'eddig megforgatott összeg',
  source_deposit_id   INT           DEFAULT NULL,
  used                TINYINT(1)    NOT NULL DEFAULT 0,
  used_at             DATETIME      DEFAULT NULL,
  expires_at          DATETIME      DEFAULT NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ubonus_user (user_id),
  CONSTRAINT fk_ubonus_user   FOREIGN KEY (user_id)   REFERENCES Users(id)      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ubonus_bonus  FOREIGN KEY (bonus_id)  REFERENCES BonusCodes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_ubonus_ticket FOREIGN KEY (ticket_id) REFERENCES Tickets(id)    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 24) ADMIN USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS AdminUsers (
  id            INT           AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)   NOT NULL UNIQUE,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role_id       INT           NOT NULL DEFAULT 1,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login    DATETIME      DEFAULT NULL,
  CONSTRAINT fk_admin_role FOREIGN KEY (role_id) REFERENCES Roles(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 25) AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS AuditLogs (
  id             INT           AUTO_INCREMENT PRIMARY KEY,
  admin_id       INT           NOT NULL,
  bonus_codes_id INT           DEFAULT NULL,
  details        VARCHAR(255)  DEFAULT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id)       REFERENCES AdminUsers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_audit_bonus FOREIGN KEY (bonus_codes_id) REFERENCES BonusCodes(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 26) TRANSACTIONS (UserProfile - Befizetések/Kifizetések)
-- ============================================================
CREATE TABLE IF NOT EXISTS Transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    transaction_id VARCHAR(100) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 27) ACTIVITY LOG (Tevékenységi Napló)
-- ============================================================
CREATE TABLE IF NOT EXISTS activitylog (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    device_info VARCHAR(255),
    status VARCHAR(50) DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 28) USER PAYMENT METHODS (Fizetési Módok)
-- ============================================================
CREATE TABLE IF NOT EXISTS UserPaymentMethods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    account_holder VARCHAR(100),
    account_number VARCHAR(100),
    bank_name VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 29) BALANCE HISTORY (Egyenleg Történet)
-- ============================================================
CREATE TABLE IF NOT EXISTS BalanceHistory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id INT,
    previous_balance DECIMAL(10, 2),
    new_balance DECIMAL(10, 2),
    change_amount DECIMAL(10, 2),
    reason VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES Transactions(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;
-- Bonuszok itt leeszenk insertelve azert elmentjuk egyelore
-- ============================================================
-- ÖSSZES BÓNUSZ BESZÚRÁSA (EGYELŐRE INAKTÍV, CSAK ADMIN SZÁMÁRA)
-- ============================================================

-- 1. HÉTKÖZNAPI BÓNUSZ (VAN KÓDJA)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'BONUSZHETKOZNAP5K',
  'BÓNUSZ HÉTKÖZNAP (5.000 Ft, 100%, 3x)',
  'Hétfőtől péntekig minden nap aktiválható. Minimum 3.000 Ft befizetés, 100% bónusz max 5.000 Ft-ig. A bónusz összegét 3x kell megforgatni.',
  2,                          -- WEEKDAYS
  0.00,
  3000.00,
  5000.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  NULL,
  NULL,
  NULL,
  3.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  1,
  '00:00:00',
  NULL,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  1                           -- Aktív
);

-- 2. DARTS BÓNUSZ (VAN KÓDJA)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'DARTSBONUSZ5K',
  'DARTS BÓNUSZ (10.000 Ft fogadás, 5.000 Ft bónusz)',
  'Fogadjon 10.000 Ft értékben 2-es kötésben kizárólag darts mérkőzésekre. Sikeres fogadás után 5.000 Ft bónusz jár, amit 2x kell megforgatni.',
  4,                          -- EVENT_SPECIFIC
  5000.00,
  10000.00,
  5000.00,
  0.00,
  'BONUS_MONEY',
  'BET',
  'DARTS',
  0,
  2.0,
  2,
  NULL,
  2.0,
  5.0,
  1,
  0,
  NULL,
  NULL,
  0,
  NULL,
  48,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  1                           -- Aktív
);

-- 3. HÁROM LÉPCSŐS ÜDVÖZLŐ BÓNUSZ (NINCS KÓD)
-- 3.1 Első lépcső: 100% max 20.000 Ft-ig, 2-es kötés, 2-es odds
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ÜDVÖZLŐ BÓNUSZ 1. LÉPÉS (100% max 20.000 Ft)',
  'Első lépcső: 100% feltöltési bónusz maximum 20.000 Ft-ig. Követelmény: 2-es kötés, minimum 2-es össz odds. A bónusz összegét 3x kell megforgatni.',
  1,                          -- WELCOME
  0.00,
  3000.00,
  20000.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  2.0,
  2,
  NULL,
  3.0,
  5.0,
  0,
  1,
  NULL,
  1,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  1                           -- Aktív
);

-- 3.2 Második lépcső: 10.000 Ft feltöltés -> 5.000 Ft ingyenes fogadás
SET @parent_id1 = (SELECT id FROM BonusCodes WHERE name = 'ÜDVÖZLŐ BÓNUSZ 1. LÉPÉS (100% max 20.000 Ft)' LIMIT 1);
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ÜDVÖZLŐ BÓNUSZ 2. LÉPÉS (5.000 Ft ingyenes fogadás)',
  'Második lépcső: Minimum 10.000 Ft feltöltés esetén 5.000 Ft ingyenes fogadás jár. Az ingyenes fogadásra 2-es kötés és 2-es odds vonatkozik.',
  1,                          
  5000.00,
  10000.00,
  5000.00,
  0.00,
  'FREE_BET',
  'DEPOSIT',
  'ANY',
  0,
  2.0,
  2,
  NULL,
  0.0,
  5.0,
  0,
  1,
  @parent_id1,
  2,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  0                           -- Csak admin
);

-- 3.3 Harmadik lépcső: 50% bónusz max 25.000 Ft-ig
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ÜDVÖZLŐ BÓNUSZ 3. LÉPÉS (50% max 25.000 Ft)',
  'Harmadik lépcső: 50% feltöltési bónusz maximum 25.000 Ft-ig (50.000 Ft feltöltés esetén 25.000 Ft bónusz). A bónusz összegét 3x kell megforgatni.',
  1,                          
  0.00,
  3000.00,
  25000.00,
  50.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  NULL,
  NULL,
  NULL,
  3.0,
  5.0,
  0,
  1,
  @parent_id1,
  3,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  0                           -- Csak admin
);

-- 4. KARÁCSONYVÁRÓ ADVENTI VASÁRNAPI BÓNUSZOK (NINCS KÓD)
-- 4.1 Első adventi vasárnap
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ADVENTI VASÁRNAP 1. (dec. 6.)',
  'Első adventi vasárnap: 100% feltöltési bónusz maximum 10.000 Ft-ig. Követelmény: 3-as kötés, minimum 3-as össz odds, eseményenként minimum 1,3 odds.',
  3,                          -- SEASONAL
  0.00,
  3000.00,
  10000.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  3.0,
  3,
  1.3,
  3.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  '2026-12-06',
  1,
  0,
  0,                          
  NULL,
  1,
  '2026-01-01 00:00:00',
  '2026-12-06 23:59:59',
  0                           -- Csak admin
);

-- 4.2 Második adventi vasárnap
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ADVENTI VASÁRNAP 2. (dec. 13.)',
  'Második adventi vasárnap: 100% feltöltési bónusz maximum 10.000 Ft-ig. Követelmény: 3-as kötés, minimum 3-as össz odds, eseményenként minimum 1,3 odds.',
  3,
  0.00,
  3000.00,
  10000.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  3.0,
  3,
  1.3,
  3.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  '2026-12-13',
  2,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  '2026-12-13 23:59:59',
  0                           -- Csak admin
);

-- 4.3 Harmadik adventi vasárnap
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ADVENTI VASÁRNAP 3. (dec. 20.)',
  'Harmadik adventi vasárnap: 100% feltöltési bónusz maximum 10.000 Ft-ig. Követelmény: 3-as kötés, minimum 3-as össz odds, eseményenként minimum 1,3 odds.',
  3,
  0.00,
  3000.00,
  10000.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  3.0,
  3,
  1.3,
  3.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  '2026-12-20',
  3,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  '2026-12-20 23:59:59',
  0                           -- Csak admin
);

-- 4.4 Negyedik adventi vasárnap
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'ADVENTI VASÁRNAP 4. (dec. 27.)',
  'Negyedik adventi vasárnap: 100% feltöltési bónusz maximum 10.000 Ft-ig. Követelmény: 3-as kötés, minimum 3-as össz odds, eseményenként minimum 1,3 odds.',
  3,
  0.00,
  3000.00,
  10000.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  3.0,
  3,
  1.3,
  3.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  '2026-12-27',
  4,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  '2026-12-27 23:59:59',
  0                           -- Csak admin
);

-- 5. NB1-ES DERBY (ÚJPEST – FERENCVÁROS) - VAN KÓDJA
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'NB1DERBY',
  'NB1 DERBY BÓNUSZ (Újpest-Ferencváros)',
  'Élőben kell fogadni minimum 5.000 Ft értékben az Újpest-Ferencváros mérkőzésre, minimum 2-es odds. Sikeres fogadás után 5.000 Ft ingyenes fogadás jár.',
  4,
  5000.00,
  5000.00,
  5000.00,
  0.00,
  'FREE_BET',
  'BET',
  'FOOTBALL',
  1,
  2.0,
  NULL,
  NULL,
  0.0,
  5.0,
  1,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  0                           -- Csak admin
);

-- 6. ESPORT BÓNUSZ (VAN KÓDJA)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'ESPORT5K',
  'ESPORT BÓNUSZ (5.000 Ft bónusz)',
  'Fogadjon 5.000 Ft értékben bármilyen esport mérkőzésre, és kap 5.000 Ft bónuszt. A bónuszt 3-as kötésben kell megtennie, eseményenként minimum 1,3 odds, össz odds minimum 3.0.',
  4,
  5000.00,
  5000.00,
  5000.00,
  0.00,
  'BONUS_MONEY',
  'BET',
  'ESPORT',
  0,
  3.0,
  3,
  1.3,
  3.0,
  5.0,
  1,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  0                           -- Csak admin
);

-- 7. CSUPA KETTES BÓNUSZ (február 22.) - VAN KÓDJA
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  '0222',
  'CSUPA KETTES BÓNUSZ (február 22.)',
  'Töltsön fel 2222 Ft-ot és 100%-ban megkapja az összeget. Követelmény: 2-es odds, 2 esemény, minimum 1,4 odds eseményenként.',
  5,
  0.00,
  2222.00,
  2222.00,
  100.00,
  'BONUS_MONEY',
  'DEPOSIT',
  'ANY',
  0,
  2.0,
  2,
  1.4,
  3.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  '2026-02-22',
  NULL,
  0,
  0,
  NULL,
  1,
  '2026-01-01 00:00:00',
  '2026-02-22 23:59:59',
  0                           -- Csak admin
);

-- 8. SZÜLETÉSNAPI BÓNUSZ (NINCS KÓD - igénylős)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'SZÜLETÉSNAPI BÓNUSZ (5.000 Ft)',
  'Születésnap alkalmából 5.000 Ft bónusz, amellyel arra fogadhat, amire akar. Nincs forgatási követelmény. Igényelhető bónusz.',
  7,                          -- ADMIN_BONUS
  5000.00,
  0.00,
  5000.00,
  0.00,
  'BONUS_MONEY',
  'MANUAL',
  'ANY',
  0,
  NULL,
  NULL,
  NULL,
  0.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  1,                          -- birthday_bonus = IGEN
  0,                          
  NULL,
  1,
  '2026-01-01 00:00:00',
  NULL,
  0                           -- Csak admin
);

-- 9. BETMATCHBONUS SZÜLETÉSNAPI BÓNUSZ (NINCS KÓD - igénylős, első 500)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  NULL,                       
  'BETMATCH SZÜLETÉSNAPI BÓNUSZ (első 500)',
  'Első 500 ügyfél számára, aki igényli, 5.000 Ft bónusz születésnapján, amellyel arra fogadhat, amire akar. Nincs forgatási követelmény.',
  7,                          -- ADMIN_BONUS
  5000.00,
  0.00,
  5000.00,
  0.00,
  'BONUS_MONEY',
  'MANUAL',
  'ANY',
  0,
  NULL,
  NULL,
  NULL,
  0.0,
  5.0,
  0,
  0,
  NULL,
  NULL,
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  1,                          -- birthday_bonus = IGEN
  0,                          
  500,                        -- usage_limit = 500
  1,
  '2026-01-01 00:00:00',
  NULL,
  0                           -- Csak admin
);

-- HÉTVÉGI BÓNUSZ (szombat-vasárnap)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'HETVEGI5K',                
  'HÉTVÉGI BÓNUSZ (5.000 Ft ingyenes fogadás)',
  'Szombaton és vasárnap: 5.000 Ft befizetés esetén 5.000 Ft ingyenes fogadás jár. Követelmény: 2-es kötés, minimum 2-es össz odds. Nincs forgatási követelmény.',
  6,                          -- WEEKEND
  5000.00,                    
  5000.00,                    
  5000.00,                    
  100.00,                     
  'FREE_BET',                 
  'DEPOSIT',                  
  'ANY',                      
  0,                          
  2.0,                        
  2,                          
  1.4,                       
  0.0,                        
  5.0,                        
  0,                          
  0,                          
  NULL,                       
  NULL,                       
  0,                          
  NULL,                       
  NULL,                       
  NULL,                       
  NULL,                       
  0,                          
  0,                          
  NULL,                       
  1,                          
  '2026-01-01 00:00:00',      
  NULL,                       
  0                           -- Csak admin
);
-- ============================================================
-- 30) USERS TABLE - Hiányzó oszlopok hozzáadása
-- ============================================================
