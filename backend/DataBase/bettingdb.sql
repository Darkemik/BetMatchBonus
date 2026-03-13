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
  code3      CHAR(3)       NOT NULL UNIQUE,
  name       VARCHAR(100)  NOT NULL,
  flag_url   VARCHAR(255)  DEFAULT NULL,
  sort_order INT           DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 3) SPORTS
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
-- 4) STATUS
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
-- 5) WALLET TRANSACTION TYPES
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
-- 6) BONUS TYPES
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
(6, 'ADMIN_BONUS');

-- ============================================================
-- 7) COMPETITIONS
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
-- 8) SEASONS
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
-- 9) TEAMS
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
-- 10) EVENTS
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
-- 11) EVENT MARKETS
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
-- 12) ODDS OUTCOMES
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
-- 13) USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS Users (
  id            INT           AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)   NOT NULL UNIQUE,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  full_name     VARCHAR(150)  DEFAULT NULL,
  birth_date    DATE          NOT NULL,
  is_verified   TINYINT(1)    NOT NULL DEFAULT 0,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 14) USER SESSIONS
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
-- 15) USER LOGS
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
-- 16) NOTIFICATIONS
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
-- 17) WALLETS
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
-- 18) WALLET TRANSACTIONS
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
-- 19) TICKETS
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
-- 20) TICKET SELECTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS TicketSelections (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  ticket_id    INT           NOT NULL,
  outcome_id   INT           NOT NULL,
  event_id     INT           NOT NULL,
  odds_at_pick DECIMAL(8,4)  NOT NULL,
  status       VARCHAR(20)   NOT NULL DEFAULT 'OPEN',
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tsel_ticket  FOREIGN KEY (ticket_id)  REFERENCES Tickets(id)      ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_tsel_outcome FOREIGN KEY (outcome_id) REFERENCES OddsOutcomes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_tsel_event   FOREIGN KEY (event_id)   REFERENCES Events(id)       ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 21) BONUS CODES
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
-- 22) USER BONUSES
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
-- 23) ADMIN USERS
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
-- 24) AUDIT LOGS
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