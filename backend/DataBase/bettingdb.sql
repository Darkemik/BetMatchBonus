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
-- 1b) ROLE PERMISSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS RolePermissions (
  id          INT           AUTO_INCREMENT PRIMARY KEY,
  role_id     INT           NOT NULL,
  page_key    VARCHAR(50)   NOT NULL,
  can_access  TINYINT(1)    NOT NULL DEFAULT 0,
  UNIQUE KEY uk_role_page (role_id, page_key),
  FOREIGN KEY (role_id) REFERENCES Roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- Alapértelmezett jogosultságok
INSERT INTO RolePermissions (role_id, page_key, can_access) VALUES
-- MOD: csak felhasználók + regisztrációk + adatellenőrzés + szelvények
(1, 'dashboard', 1), (1, 'registrations', 1), (1, 'data_verification', 1), (1, 'tickets', 1), (1, 'bonuses', 0), (1, 'freebet', 0), (1, 'deposits', 0), (1, 'withdrawals', 0), (1, 'statistics', 0), (1, 'notifications', 0),
-- ADMIN: minden kivéve staff
(2, 'dashboard', 1), (2, 'registrations', 1), (2, 'data_verification', 1), (2, 'tickets', 1), (2, 'bonuses', 1), (2, 'freebet', 1), (2, 'deposits', 1), (2, 'withdrawals', 1), (2, 'statistics', 1), (2, 'notifications', 1),
-- SUPERADMIN: mindenhez hozzáfér (a staff oldal mindig elérhető)
(3, 'dashboard', 1), (3, 'registrations', 1), (3, 'data_verification', 1), (3, 'tickets', 1), (3, 'bonuses', 1), (3, 'freebet', 1), (3, 'deposits', 1), (3, 'withdrawals', 1), (3, 'statistics', 1), (3, 'notifications', 1);

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
  game_tag   VARCHAR(30)   DEFAULT NULL,
  sport_id   INT           NOT NULL,
  country_id INT           DEFAULT NULL,
  logo_url   VARCHAR(255)  DEFAULT NULL,
  sort_order INT           DEFAULT NULL,
  is_active  TINYINT(1)    NOT NULL DEFAULT 1,
  INDEX idx_game_tag (game_tag),
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
  special_value  VARCHAR(60)   DEFAULT NULL,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  status         VARCHAR(20)   NOT NULL DEFAULT 'OPEN',
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_market_event (event_id),
  UNIQUE KEY uk_market_event_api_market (event_id, api_market_id),
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
  UNIQUE KEY uk_outcome_market_api_outcome (event_market_id, api_outcome_id),
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
  bank_statement_file VARCHAR(255)  DEFAULT NULL,
  balance             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  winnings_balance    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  bonus_balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_verified         TINYINT(1)    NOT NULL DEFAULT 0,
  is_active           TINYINT(1)    NOT NULL DEFAULT 1,
  remember_token      VARCHAR(64)   DEFAULT NULL,
  remember_expiry     DATETIME      DEFAULT NULL,
  reset_token         VARCHAR(64)   DEFAULT NULL,
  reset_token_expiry  DATETIME      DEFAULT NULL,
  approval_token      VARCHAR(64)   DEFAULT NULL,
  data_verified       TINYINT(1)    NOT NULL DEFAULT 0,
  data_verification_token VARCHAR(64) DEFAULT NULL,
  data_rejected_at    DATETIME      DEFAULT NULL,
  data_rejection_reason TEXT        DEFAULT NULL,
  password_changed_at DATETIME      DEFAULT NULL,
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;
-- ============================================================
-- 15) USER SESSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS UserSessions (
  id             INT           AUTO_INCREMENT PRIMARY KEY,
  user_id        INT           NOT NULL,
  token          VARCHAR(255)  NOT NULL UNIQUE,
  expires_at     DATETIME      NOT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1,
  ip_address     VARCHAR(45)   DEFAULT NULL,
  location       VARCHAR(100)  DEFAULT NULL,
  user_agent     VARCHAR(255)  DEFAULT NULL,
  last_active_at DATETIME      DEFAULT NULL,
  INDEX idx_session_user_active (user_id, is_active),
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES Users(id) ON UPDATE CASCADE ON DELETE CASCADE
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
-- 18b) BALANCE CAP TRIGGERS (MAX 100 000 000)
-- ============================================================
DROP TRIGGER IF EXISTS users_balance_cap_before_insert;
DROP TRIGGER IF EXISTS users_balance_cap_before_update;
DROP TRIGGER IF EXISTS wallets_balance_cap_before_insert;
DROP TRIGGER IF EXISTS wallets_balance_cap_before_update;

DELIMITER $$
CREATE TRIGGER users_balance_cap_before_insert
BEFORE INSERT ON Users
FOR EACH ROW
BEGIN
  SET NEW.balance = LEAST(COALESCE(NEW.balance, 0), 100000000.00);
END$$

CREATE TRIGGER users_balance_cap_before_update
BEFORE UPDATE ON Users
FOR EACH ROW
BEGIN
  SET NEW.balance = LEAST(COALESCE(NEW.balance, 0), 100000000.00);
END$$

CREATE TRIGGER wallets_balance_cap_before_insert
BEFORE INSERT ON Wallets
FOR EACH ROW
BEGIN
  SET NEW.balance = LEAST(COALESCE(NEW.balance, 0), 100000000.00);
END$$

CREATE TRIGGER wallets_balance_cap_before_update
BEFORE UPDATE ON Wallets
FOR EACH ROW
BEGIN
  SET NEW.balance = LEAST(COALESCE(NEW.balance, 0), 100000000.00);
END$$
DELIMITER ;

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
  bonus_stake     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  user_bonus_id   INT           DEFAULT NULL COMMENT 'multi-bonus: melyik UserBonuses-ból fogadtak',
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
  is_boosted     TINYINT(1)    NOT NULL DEFAULT 0,
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
  description          TEXT          DEFAULT NULL,
  image_url            VARCHAR(255)  DEFAULT NULL,
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
  admin_force_active   TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'admin kézi felülírás (hétvégén is aktív)',
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
  bonus_balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'egyedi bónusz egyenleg (multi-bonus)',
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
  action_type    VARCHAR(50)   DEFAULT NULL,
  target_type    VARCHAR(30)   DEFAULT NULL,
  target_id      INT           DEFAULT NULL,
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
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'rejected') DEFAULT 'pending',
    transaction_id VARCHAR(100) UNIQUE,
    description TEXT,
    rejection_reason TEXT,
    approval_token VARCHAR(128) DEFAULT NULL,
    account_holder VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(100) DEFAULT NULL,
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
    card_number VARCHAR(20) DEFAULT NULL,
    card_expiry VARCHAR(5) DEFAULT NULL,
    paypal_email VARCHAR(255) DEFAULT NULL,
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
  'Bónusz Hétköznap (5.000 Ft, 100%, 3x)',
  'Hétfőtől péntekig minden nap elérhető feltöltési bónusz! Hogyan aktiválhatod? 1) Fizess be legalább 3.000 Ft-ot a számládra. 2) A befizetett összeg 100%-át kapod bónuszként, maximum 5.000 Ft-ig. Például: 3.000 Ft befizetés = 3.000 Ft bónusz, 5.000 Ft befizetés = 5.000 Ft bónusz, 10.000 Ft befizetés = 5.000 Ft bónusz (max). 3) A kapott bónusz összeget 3-szorosan kell megforgatnod, mielőtt kifizethetővé válik. Tehát ha 5.000 Ft bónuszt kaptál, 15.000 Ft értékben kell fogadásokat megtenned. 4) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: A bónusz kizárólag hétköznapokon (hétfőtől péntekig), reggel 8 órától aktiválható, hétvégén nem érhető el!',
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
  '08:00:00',
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
  'Darts Bónusz (10.000 Ft fogadás, 5.000 Ft bónusz)',
  'Darts rajongóknak szóló exkluzív bónusz! Hogyan szerezheted meg? 1) Tégy meg egy legalább 10.000 Ft értékű fogadást kizárólag darts mérkőzésekre. 2) A fogadásnak legalább 2 eseményt (2-es kötést) kell tartalmaznia, minimum 2.00-es össz odds-szal. 3) A fogadásod lezárása és kiértékelése után 5.000 Ft bónusz pénzt kapsz a bónusz egyenlegedre. 4) A kapott 5.000 Ft bónuszt 2-szeresen kell megforgatnod (10.000 Ft értékű fogadás), mielőtt kifizethetővé válik. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: Az aktiválás után 48 órád van a bónusz felhasználására!',
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

-- 3. VESZTES FOGADÁS CASHBACK (30% Free Bet)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'CASHBACK30',
  'Vesztes fogadás cashback (30% Free Bet)',
  'Ha egy legalább 5.000 Ft-os fogadásod veszít (min. odds: 1.80), visszakapsz 30%-ot Free Bet formájában. Naponta egyszer aktiválódik automatikusan a vesztes szelvény lezárásakor. A kapott Free Bet-et bármilyen fogadásra felhasználhatod.',
  4,                          -- EVENT_SPECIFIC
  0.00,                       -- bonus_amount: 0 (match_percent-ből számolódik)
  5000.00,                    -- min_deposit: minimum tét összeg
  NULL,                       -- max_bonus_amount: nincs cap
  30.00,                      -- match_percent: 30% cashback
  'FREE_BET',
  'LOSS',                     -- LOSS trigger: vesztes fogadáskor aktiválódik
  'ANY',
  0,
  1.80,                       -- min_odds: minimum össz odds
  NULL,
  NULL,
  NULL,                       -- nincs forgatási követelmény free betnél
  NULL,                       -- nincs max win multiplier (free bet nyeremény = tét × (odds-1))
  0,
  0,
  NULL,
  NULL,
  0,                          -- valid_weekdays_only: 0 (egész héten elérhető)
  NULL,
  48,                         -- activation_expire_hours: 48 óra a free bet felhasználására
  NULL,
  NULL,
  0,
  0,
  NULL,
  1,                          -- per_user_limit: 1 (egyszer kell aktiválni, utána naponta automatikus)
  '2026-01-01 00:00:00',
  NULL,
  1                           -- Aktív
);

-- 4. NAPI TOP JUTALOM (AUTOMATIKUS, NINCS KÓDJA)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  'TOP_REWARD_DAILY',
  'Napi Top Jutalom (1.000 Ft Free Bet)',
  'Automatikus napi jutalom a top befizető, top fogadó és top nyertes számára.',
  7,                          -- DAILY_REWARD
  1000.00,
  NULL,
  NULL,
  NULL,
  'FREE_BET',
  'MANUAL',
  'ANY',
  0,
  NULL,
  NULL,
  NULL,
  NULL,
  NULL,
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
  9999,
  NULL,
  NULL,
  1                           -- Aktív
);

-- 5. ADMIN FREE BET (BELSŐ HASZNÁLAT)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  '__ADMIN_FREEBET__',
  'Admin Free Bet',
  'Admin által adott free bet',
  7,                          -- DAILY_REWARD
  0.00,
  NULL,
  NULL,
  NULL,
  'FREE_BET',
  'MANUAL',
  NULL,
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
  0,
  0,
  NULL,
  0,
  NULL,
  NULL,
  0                           -- Alapból inaktív; refresh_all auto-toggle állítja derbi-nap szerint
);

-- 6. NB1-ES DERBY (ÚJPEST – FERENCVÁROS) - VAN KÓDJA
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
  'NB1 Derby Bónusz (Újpest-Ferencváros)',
  'Az NB1 legnagyobb derbiéhez kapcsolódó exkluzív élő fogadási bónusz! Hogyan szerezheted meg? 1) Várd meg, amíg elindul az Újpest FC – Ferencvárosi TC mérkőzés. 2) A meccs közben (élő fogadásként) tégy meg egy legalább 5.000 Ft értékű fogadást, minimum 2.00-es odds-szal. 3) A fogadásod lezárása és kiértékelése után 5.000 Ft értékű ingyenes fogadást (Free Bet) kapsz jutalmul. 4) Az ingyenes fogadásnál a tét nem kerül visszafizetésre, csak a tiszta nyereményt kapod. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: Kizárólag élő fogadásra érvényes, előzetes (pre-match) fogadás nem számít!',
  4,                          -- EVENT_SPECIFIC
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

-- 7. ESPORT BÓNUSZ (VAN KÓDJA)
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
  'Esport Bónusz (5.000 Ft bónusz)',
  'Esport rajongóknak szóló bónusz — League of Legends, Counter-Strike és Valorant mérkőzésekre! Hogyan működik? 1) Tégy meg egy legalább 5.000 Ft értékű fogadást bármely esport mérkőzésre. 2) A fogadásnak legalább 3 eseményt (3-as kötést) kell tartalmaznia. 3) Minden egyes eseménynek minimum 1,30-as odds-szal kell rendelkeznie, és az össz odds-nak el kell érnie a 3.00-at. 4) A fogadásod lezárása és kiértékelése után 5.000 Ft bónusz pénzt kapsz. 5) A kapott bónuszt 3-szorosan kell megforgatnod (15.000 Ft értékű fogadás). 6) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Próbáld ki az esport fogadást és szerezd meg az extra bónuszt!',
  4,                          -- EVENT_SPECIFIC
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
  0                           -- Alapból inaktív; refresh_all auto-toggle állítja eseménynap szerint
);

-- 8. SZÜLETÉSNAPI BÓNUSZ (NINCS KÓD - IGÉNYLŐS)
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
  'Születésnapi Bónusz (5.000 Ft)',
  'Boldog születésnapot! Ajándékunk neked: 5.000 Ft bónusz a nagy napodon. Hogyan működik? 1) A rendszer a regisztrációnál megadott születési dátum alapján automatikusan ellenőrzi a jogosultságot. 2) A születésnapodon automatikusan jóváírásra kerül 5.000 Ft bónusz pénz a bónusz egyenlegedre. 3) A bónusszal bármilyen sportra, bármilyen mérkőzésre fogadhatsz — nincs sportági megkötés. 4) Nincs forgatási követelmény, tehát a nyereményed azonnal kifizethetővé válik. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Minden évben egyszer jár automatikusan.',
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
  0                           -- Nem publikus listaelem; refresh_all évente 1x automatikusan jóváírja jogosult usernek
);

-- 9. BETMATCHBONUS SZÜLETÉSNAPI BÓNUSZ (NINCS KÓD - IGÉNYLŐS, ELSŐ 500)
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
  'BetMatch Születésnapi Bónusz (első 500)',
  'A BetMatchBonus születésnapi különleges promóciója — limitált számban elérhető! Hogyan működik? 1) A promóció évente április 25-én aktiválódik. 2) Ezen a napon az első 500 jogosult igénylés teljesülhet. 3) A bónusszal bármilyen sportra, bármilyen mérkőzésre fogadhatsz — nincs sportági megkötés. 4) Nincs forgatási követelmény, a nyereményed azonnal kifizethetővé válik. 5) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Fontos: Csak 500 db érhető el összesen.',
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
  0                           -- Alapból inaktív; refresh_all auto-toggle aktiválja április 25-én
);

-- 10. HÉTVÉGI BÓNUSZ (SZOMBAT-VASÁRNAP, VAN KÓDJA)
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
  'Hétvégi Bónusz (5.000 Ft ingyenes fogadás)',
  'Hétvégi extra — szombaton és vasárnap elérhető ingyenes fogadás! Hogyan aktiválhatod? 1) Szombaton vagy vasárnap fizess be legalább 5.000 Ft-ot. 2) Cserébe 5.000 Ft értékű ingyenes fogadást (Free Bet) kapsz. 3) Az ingyenes fogadást 2-es kötésben (legalább 2 esemény) kell felhasználnod. 4) Az össz odds-nak legalább 2.00-nak kell lennie, és minden eseménynél minimum 1,40-es odds szükséges. 5) Nincs forgatási követelmény — a nyereményed azonnal kifizethetővé válik (a tét összege nem kerül visszafizetésre, csak a nyereményt kapod). 6) A maximálisan nyerhető összeg a bónusz 5-szöröse (25.000 Ft). Tipp: Használd a hétvégi nagy meccsekre!',
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
  0                           -- Alapból inaktív; refresh_all auto-toggle állítja hétvégi napokon
);

-- 11. ADMIN BÓNUSZ (BELSŐ HASZNÁLAT - BÓNUSZ PÉNZ)
INSERT INTO BonusCodes
(code, name, description, bonus_type_id, bonus_amount, min_deposit, max_bonus_amount, match_percent,
 bet_reward_type, bonus_trigger, sport_restriction, live_only, min_odds, min_combo, min_odds_per_event,
 wagering_multiplier, max_win_multiplier, evaluate_on_settle, is_step_bonus, parent_bonus_id, step_number,
 valid_weekdays_only, daily_start_time, activation_expire_hours,
 specific_date, advent_week, birthday_bonus, auto_assign, usage_limit, per_user_limit,
 valid_from, valid_to, is_active)
VALUES
(
  '__ADMIN_BONUS__',
  'Admin Bónusz',
  'Admin által manuálisan adott bónusz pénz a felhasználó bónusz egyenlegére.',
  7,                          -- ADMIN_BONUS
  0.00,
  NULL,
  NULL,
  NULL,
  'BONUS_MONEY',
  'MANUAL',
  NULL,
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
  0,
  0,
  NULL,
  0,
  NULL,
  NULL,
  0                           -- Csak admin
);

-- ============================================================
-- 30) Hiányzó oszlopok hozzáadása
-- ============================================================
ALTER TABLE Transactions
  ADD COLUMN IF NOT EXISTS account_holder VARCHAR(100) DEFAULT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS account_number VARCHAR(100) DEFAULT NULL AFTER account_holder,
  ADD COLUMN IF NOT EXISTS approval_token VARCHAR(64)  DEFAULT NULL AFTER account_number,
  ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL AFTER approval_token;

ALTER TABLE Transactions
  MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'cancelled', 'rejected') DEFAULT 'pending';

ALTER TABLE Users
  ADD COLUMN IF NOT EXISTS failed_login_attempts INT NOT NULL DEFAULT 0 AFTER password_changed_at,
  ADD COLUMN IF NOT EXISTS login_locked_until DATETIME DEFAULT NULL AFTER failed_login_attempts,
  ADD COLUMN IF NOT EXISTS force_logout_at DATETIME DEFAULT NULL AFTER login_locked_until;

ALTER TABLE BonusCodes
  MODIFY COLUMN description TEXT DEFAULT NULL;

ALTER TABLE BonusCodes
  ADD COLUMN IF NOT EXISTS admin_force_active TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'admin kézi felülírás (hétvégén is aktív)'
  AFTER per_user_limit;

-- ============================================================
-- SystemSettings — Rendszerbeállítások (admin felületről módosítható)
-- ============================================================
CREATE TABLE IF NOT EXISTS SystemSettings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    label VARCHAR(100) DEFAULT NULL,
    input_type VARCHAR(20) DEFAULT 'number',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO SystemSettings (setting_key, setting_value, description, category, label, input_type) VALUES
    ('min_deposit',             '3000',   'Minimum befizetés (Ft)',                  'deposit',       'Minimum befizetés',              'number'),
    ('max_deposit',             '600000', 'Maximum befizetés (Ft)',                  'deposit',       'Maximum befizetés',              'number'),
    ('min_withdrawal',          '6000',   'Minimum kifizetés (Ft)',                  'withdrawal',    'Minimum kifizetés',              'number'),
    ('max_withdrawal',          '50000',  'Maximum kifizetés (Ft)',                  'withdrawal',    'Maximum kifizetés',              'number'),
    ('min_bet_amount',          '100',    'Minimum tét összeg (Ft)',                 'betting',       'Minimum tét',                    'number'),
    ('min_password_length',     '7',      'Minimum jelszóhossz',                    'security',      'Minimum jelszóhossz',            'number'),
    ('min_user_age',            '18',     'Minimum regisztrációs kor',              'registration',  'Minimum életkor',                'number'),
    ('min_phone_length',        '11',     'Minimum telefonszám hossz',              'registration',  'Minimum telefonszám hossz',      'number'),
    ('session_timeout_minutes', '30',     'Inaktivitási időkorlát (perc)',           'security',      'Inaktivitási időkorlát (perc)',   'number'),
    ('session_max_duration_minutes', '60', 'Munkamenet időkorlát (perc)',             'security',      'Munkamenet időkorlát (perc)',     'number'),
    ('max_login_attempts',      '3',      'Maximum bejelentkezési próbálkozás',     'security',      'Max. bejelentkezési próbálkozás', 'number'),
    ('login_lockout_minutes',   '60',     'Zárolás időtartama (perc)',              'security',      'Zárolás időtartama (perc)',       'number'),
    ('recaptcha_threshold',     '0.5',    'reCAPTCHA küszöbérték',                  'security',      'reCAPTCHA küszöbérték',           'number'),
    ('daily_tip_multiplier',    '1.2',    'Napi tipp szorzó',                       'betting',       'Napi tipp szorzó',               'number'),
    ('odds_pyramid_multiplier', '1.3',    'Odds piramis szorzó',                    'betting',       'Odds piramis szorzó',            'number'),
    ('min_pyramid_selections',  '6',      'Minimum piramis választás',              'betting',       'Min. piramis választás',         'number');

-- ============================================================
-- Hiányzó oszlopok biztosítása (meglévő DB frissítéshez)
-- ============================================================
ALTER TABLE AuditLogs
  ADD COLUMN IF NOT EXISTS action_type VARCHAR(50) DEFAULT NULL AFTER admin_id,
  ADD COLUMN IF NOT EXISTS target_type VARCHAR(30) DEFAULT NULL AFTER action_type,
  ADD COLUMN IF NOT EXISTS target_id INT DEFAULT NULL AFTER target_type;

-- ============================================================
-- BALANCE CAP MIGRATION (existing data cleanup)
-- ============================================================
UPDATE Users
SET balance = 100000000.00
WHERE balance > 100000000.00;

UPDATE Wallets
SET balance = 100000000.00
WHERE balance > 100000000.00;
