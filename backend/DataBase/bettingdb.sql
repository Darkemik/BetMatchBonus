-- A BetMatchBonus projekt adatbázis sémája
-- DATABASE: betmatchbonusbeta

CREATE DATABASE IF NOT EXISTS betmatchbonusb​eta
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_hungarian_ci;

USE betmatchbonusb​eta;


CREATE TABLE IF NOT EXISTS Users (
  id             INT           AUTO_INCREMENT PRIMARY KEY   COMMENT 'Felhasználó azonosító',
  username       VARCHAR(50)   NOT NULL UNIQUE              COMMENT 'Bejelentkezési név',
  email          VARCHAR(150)  NOT NULL UNIQUE              COMMENT 'Egyedi email cím',
  password_hash  VARCHAR(255)  NOT NULL                     COMMENT 'Jelszó: bcrypt hash',
  full_name      VARCHAR(150)  DEFAULT NULL                 COMMENT 'Teljes név (opcionális)',
  birth_date     DATE          NOT NULL                     COMMENT 'Születési dátum (18+ ellenőrzés)',
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP          COMMENT 'Regisztráció dátuma',
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP 
                                        ON UPDATE CURRENT_TIMESTAMP        COMMENT 'Utolsó módosítás',
  is_verified    TINYINT(1)    NOT NULL DEFAULT 0           COMMENT 'Email/KYC megerősítve (0=nem, 1=igen)',
  is_active      TINYINT(1)    NOT NULL DEFAULT 1           COMMENT 'Fiók aktív-e (0=inaktív, 1=aktív)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;