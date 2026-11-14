# JEF Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-02-27

## Active Technologies

- PHP 8.5 + None (no framework). PHPUnit for testing. (001-public-rankings)

## Project Structure

```text
src/
tests/
```

## Commands

# Add commands for PHP 8.5

## Code Style

PHP 8.5: Follow standard conventions

## Recent Changes

- 001-public-rankings: Added PHP 8.5 + None (no framework). PHPUnit for testing.

<!-- MANUAL ADDITIONS START -->

## Database Conventions

- All tables MUST be prefixed with `jef_` (e.g., `jef_players`, `jef_tournaments`, `jef_circuit_rankings`)

## Deployment

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

<!-- MANUAL ADDITIONS END -->
