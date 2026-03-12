CREATE DATABASE IF NOT EXISTS betmatchbonusbeta
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_hungarian_ci;

USE betmatchbonusbeta;

-- ============================================================
-- 1) ROLES (jogosultsági szintek)
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
-- 2) USERS (sima felhasználók)
-- ============================================================
CREATE TABLE IF NOT EXISTS Users (
  id             INT           AUTO_INCREMENT PRIMARY KEY,
  username       VARCHAR(50)   NOT NULL UNIQUE,
  email          VARCHAR(150)  NOT NULL UNIQUE,
  password_hash  VARCHAR(255)  NOT NULL,
  full_name      VARCHAR(150)  DEFAULT NULL,
  birth_date     DATE          NOT NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_verified    TINYINT(1)    NOT NULL DEFAULT 0,
  is_active      TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 3) ADMIN USERS (admin / mod felhasználók)
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
  CONSTRAINT fk_admin_role
    FOREIGN KEY (role_id) REFERENCES Roles(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;
ALTER TABLE AdminUsers DROP CONSTRAINT chk_admin_role;
ALTER TABLE AdminUsers DROP COLUMN role;

ALTER TABLE AdminUsers
  ADD COLUMN role_id INT NOT NULL DEFAULT 1 AFTER password_hash,
  ADD CONSTRAINT fk_admin_role
    FOREIGN KEY (role_id) REFERENCES Roles(id)
    ON UPDATE CASCADE ON DELETE RESTRICT;
    
-- ============================================================
-- 4) AUDIT LOGS (admin tevékenységek naplója)
-- ============================================================
CREATE TABLE IF NOT EXISTS AuditLogs (
  id          INT           AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT           NOT NULL,
  details     VARCHAR(255)  DEFAULT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_admin
    FOREIGN KEY (admin_id) REFERENCES AdminUsers(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 5) COUNTRIES
-- ============================================================
CREATE TABLE IF NOT EXISTS Countries (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  code       CHAR(3)       NOT NULL UNIQUE,
  name       VARCHAR(100)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 6) CHAMPIONSHIPS
-- ============================================================
CREATE TABLE IF NOT EXISTS Championships (
  id            INT           AUTO_INCREMENT PRIMARY KEY,
  api_id        INT           NOT NULL UNIQUE,
  sport_id      INT           NOT NULL,
  country_code  CHAR(3)       NOT NULL,
  name          VARCHAR(150)  NOT NULL,
  INDEX idx_ch_country_code (country_code),
  CONSTRAINT fk_ch_country
    FOREIGN KEY (country_code) REFERENCES Countries(code)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 7) MATCHES
-- ============================================================
CREATE TABLE IF NOT EXISTS Matches (
  id               INT           AUTO_INCREMENT PRIMARY KEY,
  api_id           BIGINT        NOT NULL UNIQUE,
  sport_id         INT           NOT NULL,
  championship_id  INT           NOT NULL,
  name             VARCHAR(255)  NOT NULL,
  start_utc        DATETIME      NOT NULL,
  is_live          TINYINT(1)    NOT NULL DEFAULT 0,
  live_time        VARCHAR(20)   DEFAULT NULL,
  INDEX idx_matches_live (sport_id, is_live),
  INDEX idx_matches_start (start_utc),
  CONSTRAINT fk_matches_championship
    FOREIGN KEY (championship_id) REFERENCES Championships(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- ============================================================
-- 8) BONUSES (bónuszok)
-- ============================================================
CREATE TABLE IF NOT EXISTS Bonuses (
  id              INT           AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(150)  NOT NULL,
  description     TEXT          DEFAULT NULL,
  bonus_type      VARCHAR(50)   NOT NULL COMMENT 'WELCOME, DEPOSIT, FREEBET, CASHBACK, RELOAD, VIP',
  amount          DECIMAL(10,2) NOT NULL DEFAULT 0,
  percentage      INT           DEFAULT NULL     COMMENT 'pl. 100 = 100%-os bónusz',
  min_deposit     DECIMAL(10,2) DEFAULT NULL     COMMENT 'minimum befizetés a bónuszhoz',
  max_bonus       DECIMAL(10,2) DEFAULT NULL     COMMENT 'maximum bónusz összeg',
  wagering        INT           DEFAULT NULL     COMMENT 'megforgatási szorzó (pl. 5x)',
  min_odds        DECIMAL(4,2)  DEFAULT NULL     COMMENT 'minimum odds a megforgatáshoz',
  valid_days      INT           DEFAULT NULL     COMMENT 'hány napig érvényes',
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

Table BonusCodes {
  id                    int           [pk, increment]
  code                  varchar(50)   // nullable auto-bónusznál; partial unique index (csak NOT NULL értékekre)
  name                  varchar(150)  [not null]
  description           varchar(255)
  bonus_type_id         int           [not null]  // FK → BonusTypes.id
  bonus_amount          decimal(10,2) [not null]
  min_deposit           decimal(10,2)
  max_bonus_amount      decimal(10,2)             // max kifizetható bónusz összeg (pl. 20.000 Ft cap)
  match_percent         decimal(5,2)              // pl. 100% feltöltési bónusznál: 1.00
  bet_reward_type       varchar(20)               // 'FREE_BET', 'BONUS_MONEY', 'MIXED'
  bonus_trigger         varchar(20)               // 'DEPOSIT', 'BET', 'MANUAL', 'AUTO'
  sport_restriction     varchar(50)               // pl. 'DARTS', 'ESPORT', 'ANY'
  live_only             boolean       [not null, default: false]
  min_odds              decimal(5,2)              // minimális odds (pl. 2.0)
  min_combo             int                       // minimális kötésszám (pl. 2, 3)
  min_odds_per_event    decimal(5,2)              // eseményenkénti min odds (pl. 1.3)
  wagering_multiplier   decimal(5,2)              // forgatási követelmény (pl. 3.0)
  max_win_multiplier    decimal(5,2)  [default: 5]// bónusz összeg max 5x nyerhető
  evaluate_on_settle    boolean       [not null, default: false] // true = csak lezáráskor értékel
  is_step_bonus         boolean       [not null, default: false]
  parent_bonus_id       int                       // FK → BonusCodes.id (lépcsős bónusznál)
  step_number           int                       // hanyadik lépcső (1, 2, 3)
  valid_weekdays_only   boolean       [not null, default: false] // csak hétköznapokon aktív
  specific_date         date                      // adott napi bónusz (pl. febr. 22.)
  advent_week           int                       // adventi bónusznál 1-4
  birthday_bonus        boolean       [not null, default: false]
  auto_assign           boolean       [not null, default: false]
  usage_limit           int
  per_user_limit        int           [default: 1]
  valid_from            datetime
  valid_to              datetime
  is_active             boolean       [not null, default: true]
  created_at            datetime      [not null]
}

Table UserBonuses {
  id                    int           [pk, increment]
  user_id               int           [not null]  // FK → Users.id
  bonus_id              int           [not null]  // FK → BonusCodes.id
  ticket_id             int                       // FK → Tickets.id
  step_index            int                       // lépcsős bónusznál hanyadik lépésnél jár
  status                varchar(20)   [not null]  // 'PENDING', 'ACTIVE', 'COMPLETED', 'FAILED', 'EXPIRED'
  granted_amount        decimal(10,2) [not null]
  free_bet_amount       decimal(10,2)             // ingyenes fogadás összege
  bonus_money_amount    decimal(10,2)             // bónusz pénz összege
  max_win_amount        decimal(10,2)             // max nyerhető összeg (5x cap)
  wagering_required     decimal(10,2)             // szükséges forgatás (snapshot)
  wagering_progress     decimal(10,2) [default: 0]// eddig megforgatott összeg
  source_deposit_id     int                       // FK → Deposits.id
  used                  boolean       [not null, default: false]
  used_at               datetime
  expires_at            datetime                  // mikor jár le aktiválás után
  created_at            datetime      [not null]
}