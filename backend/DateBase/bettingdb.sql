-- A BetMatchBonus projekt adatbázis sémája
-- DATABASE: betmatchbonusbeta

CREATE TABLE Countries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(3) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Championships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_id INT NOT NULL UNIQUE,
  sport_id INT NOT NULL,
  country_code VARCHAR(3) NOT NULL,
  name VARCHAR(150) NOT NULL,
  CONSTRAINT fk_champ_country FOREIGN KEY (country_code) REFERENCES Countries(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE Matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  api_id BIGINT NOT NULL UNIQUE,
  sport_id INT NOT NULL,
  championship_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  start_utc DATETIME NOT NULL,
  is_live TINYINT(1) NOT NULL DEFAULT 0,
  live_time VARCHAR(20),
  CONSTRAINT fk_match_champ FOREIGN KEY (championship_id) REFERENCES Championships(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;