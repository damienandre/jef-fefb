# JEF: Jeunesse Échecs Francophone Rankings

JEF is a web application for managing and displaying youth chess circuit rankings. It allows administrators to import tournament results via TRF (Tournament Report File) and provides public rankings by year and age category (U8 to U20).

## Project Overview

- **Language:** PHP 8.5 (Vanilla, no framework)
- **Database:** MySQL/MariaDB (PDO)
- **Frontend:** HTML/CSS (Responsive, French language)
- **Testing:** PHPUnit ^12.0
- **Routing:** Handled in `public/index.php` using `match`
- **Architecture:** 
    - `public/`: Entry point and public assets
    - `pages/`: Page controllers/handlers
    - `src/`: Business logic (PSR-4 `Jef\` namespace)
    - `templates/`: HTML templates (`.html.php` extension)
    - `migrations/`: SQL schema migrations
    - `specs/`: Detailed feature specifications and plans

## Building and Running

### Setup
1. Copy `config.example.php` to `config.php` and update database credentials.
2. Run `composer install` to install dependencies.
3. Run `php migrate.php` to create the database schema.

### Running
- Use the built-in PHP server: `php -S localhost:8000 -t public`
- Access the application at `http://localhost:8000`

### Testing
- Run all tests: `./vendor/bin/phpunit`
- Configuration is in `phpunit.xml`.
- Integration tests empty the `jef_*` tables of the configured database: run them only against a local/dev database, never on the server.

### Deployment

Production runs on Infomaniak shared hosting. Nothing is deployed automatically
(no CI/CD, no webhook): after merging to `main`, deploy by hand.

1. Open the SSH console from the Infomaniak control panel.
2. `cd jef-app` (a clone of this repository, branch `main`).
3. `git pull`
4. Only if the release adds files under `migrations/`: `php migrate.php`.
5. Only if `composer.json` / `composer.lock` changed: `composer install`.

**Never run `vendor/bin/phpunit` on the server.** The integration tests delete
all rows from the `jef_*` tables of the database configured in `config.php`,
which on the server is production.

**Recurring: player club enrichment.** Imports enrich only the tournament just
imported, and only players currently affiliated to a club (or holding a
provisional licence) get one. Players who renew late get no club until the
enrichment is run again, and nothing runs it automatically. So, from the SSH
console in `jef-app`, after each season's renewals have been processed (around
November) and whenever clubs look missing:

1. `php cli/enrich-players.php --dry-run` (downloads the FRBE player export,
   changes nothing, prints what it would do)
2. `php cli/enrich-players.php`

It sets every tournament participation's club to the player's current club and
never clears a club when the player has none. Unlike phpunit, it is meant to
run against production.

## Development Conventions

### Coding Style
- **PHP 8.5+**: Use modern PHP features (e.g., `match`, `declare(strict_types=1);`).
- **Namespace**: PSR-4 `Jef\` mapping to `src/`.
- **Views**: Use `Jef\View::render` to render templates from the `templates/` directory.

### Database
- All table names MUST be prefixed with `jef_` (e.g., `jef_players`, `jef_tournaments`).
- Migrations are stored in `migrations/` as `.sql` files and applied via `migrate.php`.

### Workflow
- Follow the feature plans in `specs/`. Each feature has its own directory with `spec.md`, `plan.md`, `tasks.md`, etc.
- Use `.specify/scripts/` for automated workflow management if applicable.

### UI/UX
- The user interface is in **French**.
- Designs should be simple, modern, and responsive.
- The FEFB logo is a key branding element.

## Key Files
- `public/index.php`: Main router.
- `src/Database.php`: Singleton PDO connection.
- `src/View.php`: Template renderer.
- `src/Ranking/RankingCalculator.php`: Core logic for computing circuit points.
- `src/Trf/TrfParser.php`: TRF file parsing logic.
