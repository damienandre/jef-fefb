# Quickstart: Classement Public du Circuit JEF

## Prerequisites

- PHP 8.5 with extensions: pdo_mysql, mbstring, json, session
- MariaDB 10.11+
- Composer (for PHPUnit dev dependency)
- Apache with mod_rewrite (or PHP built-in server for development)

## Local Setup

1. **Clone and switch to feature branch**:

   ```bash
   git clone <repo-url> jef
   cd jef
   git switch 001-public-rankings
   ```

2. **Install dev dependencies** (PHPUnit):

   ```bash
   composer install
   ```

3. **Create the database**:

   ```bash
   mysql -u root -e "CREATE DATABASE jef CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

4. **Configure the application**:

   ```bash
   cp config.example.php config.php
   ```

   Edit `config.php` with your database credentials:
   ```php
   return [
       'db_host' => '127.0.0.1',
       'db_name' => 'jef',
       'db_user' => 'root',
       'db_pass' => '',
       'base_url' => 'http://localhost:8080',
   ];
   ```

5. **Run database migrations**:

   ```bash
   php migrate.php
   ```

6. **Create an admin user**:

   ```bash
   php cli/create-user.php admin <password>
   ```

7. **Start the development server**:

   ```bash
   php -S localhost:8080 -t public/
   ```

8. **Access the application**:
   - Public site: http://localhost:8080/
   - Admin login: http://localhost:8080/admin/login

## Running Tests

```bash
vendor/bin/phpunit
```

## Importing Tournament Data

1. Log in to the admin area at `/admin/login`.
2. Navigate to "Import TRF" at `/admin/import`.
3. Select the season year and tournament position (sort order).
4. Upload a TRF file.
5. The system parses the file, saves tournament data, and
   recalculates circuit rankings automatically.
6. Visit the public site to verify the rankings.

## Deploying to Infomaniak

1. Set the document root to the `public/` directory in Infomaniak
   Manager.
2. Upload all files via SFTP or Git (SSH access available).
3. Copy `config.example.php` to `config.php` and set production
   database credentials.
4. Run migrations via SSH: `php migrate.php`.
5. Create the admin user via SSH: `php cli/create-user.php admin <password>`.
6. Ensure the `uploads/` directory is writable by the web server
   (for logo uploads).

## Project Structure

```
project/
  public/                  # Document root (web-accessible)
    index.php              # Front controller
    .htaccess              # URL rewriting rules
    css/                   # Stylesheets
    uploads/               # Uploaded files (logo)
  src/                     # Application source code
    Trf/                   # TRF parser
      TrfParser.php        # TRF file parser
      TrfPlayer.php        # Player data from TRF
      TrfTournament.php    # Tournament data from TRF
    Ranking/               # Ranking calculation
      RankingCalculator.php
    Database.php           # PDO wrapper
    Auth.php               # Session authentication
    View.php               # Template rendering helper
  pages/                   # Page controllers
    rankings.php           # Public: circuit rankings
    tournament.php         # Public: tournament detail
    admin/                 # Admin pages
      dashboard.php
      import.php
      login.php
      settings.php
      logout.php
    404.php                # Error page
  templates/               # HTML templates
    layout.php             # Base layout (header, nav, footer)
    rankings.html.php      # Rankings table template
    tournament.html.php    # Tournament detail template
    admin/                 # Admin templates
  migrations/              # Numbered SQL files
    001_create_schema.sql
  tests/                   # PHPUnit tests
    Unit/
      Trf/
        TrfParserTest.php
      Ranking/
        RankingCalculatorTest.php
    Integration/
      ImportWorkflowTest.php
  cli/                     # CLI scripts
    create-user.php
  config.example.php       # Configuration template
  config.php               # Local configuration (gitignored)
  migrate.php              # Migration runner
  composer.json
  phpunit.xml
```
