CREATE DATABASE IF NOT EXISTS betmatchbonusbeta
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_hungarian_ci;

USE betmatchbonusbeta;

-- USERS
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

-- COUNTRIES (a mostani PHP-k ezt várják: code + name)
CREATE TABLE IF NOT EXISTS Countries (
  id         INT           AUTO_INCREMENT PRIMARY KEY,
  code       CHAR(3)       NOT NULL UNIQUE,
  name       VARCHAR(100)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- CHAMPIONSHIPS (a PHP: api_id, sport_id, country_code, name)
CREATE TABLE IF NOT EXISTS Championships (
  id           INT           AUTO_INCREMENT PRIMARY KEY,
  api_id        INT          NOT NULL UNIQUE,
  sport_id      INT          NOT NULL,
  country_code  CHAR(3)      NOT NULL,
  name          VARCHAR(150) NOT NULL,
  INDEX idx_ch_country_code (country_code),
  CONSTRAINT fk_ch_country
    FOREIGN KEY (country_code) REFERENCES Countries(code)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- MATCHES (a PHP: api_id, sport_id, championship_id, name, start_utc, is_live, live_time)
CREATE TABLE IF NOT EXISTS Matches (
  id              INT           AUTO_INCREMENT PRIMARY KEY,
  api_id           BIGINT       NOT NULL UNIQUE,
  sport_id         INT          NOT NULL,
  championship_id  INT          NOT NULL,
  name             VARCHAR(255) NOT NULL,
  start_utc        DATETIME     NOT NULL,
  is_live          TINYINT(1)   NOT NULL DEFAULT 0,
  live_time        VARCHAR(20)  DEFAULT NULL,
  INDEX idx_matches_live (sport_id, is_live),
  INDEX idx_matches_start (start_utc),
  CONSTRAINT fk_matches_championship
    FOREIGN KEY (championship_id) REFERENCES Championships(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;