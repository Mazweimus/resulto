# Resulto – Darts Tournament Management System

**Resulto** je webová aplikace postavená na frameworku **Laravel**, určená k organizaci, řízení a správě šipkových turnajů, skupinových fází, vyřazovacích pavouků a dlouhodobých žebříčků.

---

## Hlavní funkce

* **Správa turnajů (`TournamentService`, `TournamentGroupService`)**
  * Vytváření a konfigurace turnajů.
  * Automatické nebo manuální rozlosování hráčů do skupin.
  * Generování vyřazovací fáze (play-off).

* **Správa zápasů a živé výsledky (`GameMatch`, `MatchController`)**
  * Evidence jednotlivých utkání a zapisování výsledků v reálném čase.
  * Sledování stavu zápasů (naplánováno, probíhá, dokončeno).

* **Automatický přepočet tabulek (`RecalculateGroupStandingsAction`)**
  * Okamžitá aktualizace pořadí ve skupinách po zadání výsledku zápasu.
  * Zohlednění bodů, výher/proher a skóre legů.

* **Žebříčky a statistiky (`LeaderboardController`, `TournamentStanding`)**
  * Dlouhodobá sezónní pořadí hráčů.
  * Statistiky odehraných turnajů a zápasů.

* **Správa hráčů a uživatelů (`PlayerController`, `UserController`)**
  * Profil hráče, správa uživatelských účtů a rolí.
  * Nástroje pro deduplikaci a údržbu databáze hráčů.

---

## Technologický stoh

* **Backend:** PHP 8.x, [Laravel Framework](https://laravel.com)
* **Frontend:** Blade Templates, Tailwind CSS / JavaScript (Vite)
* **Databáze:** MySQL / PostgreSQL / SQLite
* **Nástroje:** Composer, NPM

---

## Požadavky na prostředí

* **PHP:** `>= 8.1`
* **Composer**
* **Node.js & NPM**
* **Databáze:** SQLite
* **PHP rozšíření:** `PDO`, `Mbstring`, `OpenSSL`, `Tokenizer`, `XML`, `Ctype`, `JSON`

---

## Instalace a zprovoznění

1. **Klonování repozitáře**
   ```bash
   git clone <url-repozitare>
   cd resulto-main
   ```

2. **Instalace PHP závislostí**
   ```bash
   composer install
   ```

3. **Instalace JavaScript závislostí a sestavení assetů**
   ```bash
   npm install
   npm run build
   ```

4. **Konfigurace prostředí**
   Vytvořte soubor `.env` z přiložené šablony:
   ```bash
   cp .env.example .env
   ```
   Vygenerujte aplikační klíč:
   ```bash
   php artisan key:generate
   ```
5. **Nastavení SQLite databáze**
   Vytvořte prázdný soubor databáze v adresáři `database/`:
   
   * **Linux / macOS:**
     ```bash
     touch database/database.sqlite
     ```
   * **Windows (PowerShell):**
     ```powershell
     New-Item database/database.sqlite -ItemType File
     ```

   V `.env` souboru upravte nastavení databáze následovně:
   ```env
   DB_CONNECTION=sqlite
   # Ostatní řádky DB_* (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD) můžete zakomentovat nebo smazat.
   ```

6. **Spuštění migrací a seederů**
   ```bash
   php artisan migrate --seed
   ```

7. **Spuštění vývojového serveru**
   ```bash
   php artisan serve
   ```
   Aplikace bude dostupná na adrese `http://localhost:8000`.

---

## Struktura projektu

```text
app/
├── Actions/              # Logika pro konkrétní operace (přepočet tabulek apod.)
├── Http/
│   ├── Controllers/      # Kontrolery pro turnaje, zápasy, hráče a žebříčky
│   └── Middleware/       # Autentizace a oprávnění
├── Models/               # Eloquent modely (Tournament, GameMatch, Player...)
└── Services/             # Byznys logika (TournamentService, TournamentGroupService)

database/
├── migrations/           # Databázové schémata pro turnaje, skupiny, zápasy a hráče
└── seeders/              # Výchozí data pro testování

resources/
└── views/                # Blade šablony uživatelského rozhraní
```

---

## 🧪 Užitečné Artisan příkazy

* **Přepočet žebříčků / údržba databáze:**
  ```bash
  php artisan migrate:fresh --seed
  ```
