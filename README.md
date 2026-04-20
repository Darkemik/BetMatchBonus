# BetMatchBonus

A **BetMatchBonus** egy PHP + MySQL alapú sportfogadási webalkalmazás demó/prototípus, amely főoldali meccslistát, élő és eSport nézetet, bónuszrendszert, felhasználói profilt és admin felületet tartalmaz.

## Fő funkciók

- Főoldal közelgő meccsekkel és kereséssel
- Élő és eSport oldalak
- Fogadószelvény kezelés
- Bónuszok (pl. hétköznapi, szezonális, admin bónusz)
- Felhasználói fiók: befizetés, kifizetés, tranzakciók, értesítések
- Admin panel jogosultságkezeléssel
- Többnyelvű UI (HU/EN)

## Technológiai stack

- **Backend:** PHP (natív, külön API endpointokkal)
- **Frontend:** PHP sablonok + JavaScript + Bootstrap + CSS
- **Adatbázis:** MySQL/MariaDB
- **Külső integrációk:** reCAPTCHA v3, SMTP (PHPMailer), külső sport API

## Könyvtárszerkezet

- `frontend/` – oldalak (MainMenu, Live, Esport, Bonus, Help, UserProfile, Admin)
- `backend/` – auth, API endpointok, adatbázis script-ek, konfiguráció
- `js/` – kliens oldali logika
- `css/` – stílusok
- `json/` – nyelvi és tartalmi JSON fájlok
- `img/` – képek, logó

## Előfeltételek

- PHP 8.x (javasolt)
- MySQL vagy MariaDB
- Webszerver (Apache/Nginx, vagy XAMPP/Laragon)
- Internetkapcsolat a CDN-ekhez és külső API-khoz

## Telepítés (lokális környezet)

1. Klónozd a repositoryt a webszerver gyökér alá.
2. Hozd létre a `.env` fájlt az `.env.example` alapján.
3. Állítsd be legalább ezeket a változókat a `.env` fájlban:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
   - `API_BASE_URL`
   - `RECAPTCHA_*`, `MAIL_*`, `SITE_BASE_URL`
4. Importáld az adatbázis sémát:
   - `backend/DataBase/bettingdb.sql`
5. Az adatbázis importálása után futtasd a kapcsolódási ellenőrzést:
   - `php backend/connect.php`
6. Ezt követően futtasd az összesített adatfrissítést:
   - `php backend/refresh_all.php`
7. Ellenőrizd, hogy a `backend/uploads/` könyvtár írható.

## Futtatás

- Nyisd meg a főoldalt:
  - `http://localhost/BetMatchBonus/frontend/MainMenu/MainMenu.php`
- Admin belépés:
  - `http://localhost/BetMatchBonus/frontend/Admin/admin_login.php`

> Fontos: fejlesztés előtt ellenőrizd a `backend/DataBase/seed_admins.php` tartalmát, és használj saját, biztonságos admin fiókadatokat.
## Adatfrissítés / karbantartás

A `backend/refresh_all.php` script több feladatot futtat egyben:

- bónusz státuszok frissítése,
- sportadatok szinkronizálása,
- nyitott szelvények kiértékelése,
- napi jutalmak kiosztása (időfüggően).

Futtatás például:

```bash
php backend/refresh_all.php
```

## Megjegyzés tesztelésről

Ebben a repositoryban nincs központi `package.json`/`composer.json` alapú build vagy egységes teszt parancs. A backendben több segéd- és ellenőrző script található a `backend/DataBase/` mappában.

## Biztonság

- A `.env` fájl ne kerüljön verziókezelésbe.
- Ne használj valós kulcsokat/jelszavakat fejlesztői környezetben.
- Éles környezetben minden seedelt vagy teszt hitelesítést cserélj le.
