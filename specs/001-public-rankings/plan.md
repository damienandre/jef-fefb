# Implementation Plan: Classement Public du Circuit JEF

**Branch**: `001-public-rankings` | **Date**: 2026-02-27 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-public-rankings/spec.md`

## Summary

Build a public-facing web application for the JEF youth chess
circuit rankings (FEFB). The system allows administrators to import
tournament results via TRF files (FIDE standard), automatically
calculates circuit rankings (general + 6 age categories), and
displays them on a responsive French-language website. Visitors can
browse rankings by year and age category, and view individual
tournament grids.

Built with plain PHP 8.5 (no framework), MariaDB 10.11, deployed
on Infomaniak shared hosting.

## Technical Context

**Language/Version**: PHP 8.5
**Primary Dependencies**: None (no framework). PHPUnit for testing.
**Storage**: MariaDB 10.11 (InnoDB)
**Testing**: PHPUnit (via `vendor/bin/phpunit`)
**Target Platform**: Infomaniak shared hosting (Apache + mod_rewrite)
**Project Type**: Web application (server-rendered)
**Performance Goals**: <3s initial page load, <2s year/category switch
**Constraints**: Shared hosting (no background workers, no custom
server config, standard PHP extensions only, .htaccess only)
**Scale/Scope**: ~100-500 players/season, 4-10 tournaments/year,
low traffic (parents, clubs), 1-3 admin users

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Simplicity First — PASS

- No framework used. Plain PHP with PDO and `match` routing.
- No unnecessary abstractions. Direct page controllers.
- Database schema models actual query patterns (rankings table
  pre-calculated for fast reads).
- Only dev dependency: PHPUnit.

### II. Quality Through Testing — PASS

- Unit tests planned for: TRF parser, ranking calculator, age
  category determination.
- Integration tests planned for: TRF import workflow (parse →
  save → recalculate).
- PHPUnit runnable locally with only MariaDB as external dependency.

### III. Data Integrity — PASS

- TRF import wrapped in single database transaction.
- All tables use InnoDB for FK constraints.
- Input validation at TRF parser level before any persistence.
- Ranking recalculations are idempotent (delete + recalculate in
  same transaction).

### IV. Hosting-Aware Design — PASS

- No background processes. All computation happens during HTTP
  request (TRF import is synchronous).
- .htaccess routing only. No custom server config.
- TRF files are small (~10-50KB), parsing is fast.
- All dependencies available via Composer, standard PHP extensions.
- File uploads use `public/uploads/` compatible with shared hosting.

### V. Incremental Delivery — PASS

- User stories ordered by priority: P1 (rankings display) can be
  deployed independently with static seed data.
- Each story adds functionality without breaking previous stories.
- Database migrations are forward-compatible (additive).
- MVP = P1 (rankings table) + P4 (TRF import) for a functional
  first deployment.

## Project Structure

### Documentation (this feature)

```text
specs/001-public-rankings/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   └── routes.md        # HTTP routes contract
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
public/                    # Document root (Infomaniak points here)
  index.php                # Front controller
  .htaccess                # URL rewriting
  css/
    style.css              # Application styles
  uploads/                 # Logo uploads (writable)

src/                       # Application source (autoloaded via PSR-4)
  Trf/
    TrfParser.php          # TRF16 file parser
    TrfPlayer.php          # Player record DTO
    TrfTournament.php      # Tournament header DTO
  Ranking/
    RankingCalculator.php  # Circuit ranking calculation engine
    AgeCategory.php        # Age category determination
  Database.php             # PDO connection helper
  Auth.php                 # Session-based authentication
  View.php                 # Template rendering helper

pages/                     # Page controllers (require'd by router)
  rankings.php             # GET / — public rankings
  tournament.php           # GET /tournoi — tournament detail
  admin/
    dashboard.php          # GET /admin
    import.php             # GET|POST /admin/import
    login.php              # GET|POST /admin/login
    settings.php           # GET|POST /admin/settings
    logout.php             # POST /admin/logout
  404.php                  # Error page

templates/                 # HTML templates (included by pages)
  layout.php               # Base layout (header, nav, footer)
  rankings.html.php        # Rankings table partial
  tournament.html.php      # Tournament grid partial
  admin/
    layout.php             # Admin base layout
    dashboard.html.php
    import.html.php
    login.html.php
    settings.html.php

migrations/                # Numbered SQL files
  001_create_schema.sql    # Initial schema

tests/                     # PHPUnit tests
  Unit/
    Trf/
      TrfParserTest.php
    Ranking/
      RankingCalculatorTest.php
      AgeCategoryTest.php
  Integration/
    ImportWorkflowTest.php

cli/                       # CLI tools
  create-user.php          # Create admin user

config.example.php         # Config template (committed)
config.php                 # Local config (gitignored)
migrate.php                # Migration runner script
composer.json              # PHPUnit dependency + PSR-4 autoload
phpunit.xml                # PHPUnit configuration
```

**Structure Decision**: Single project layout. No frontend/backend
separation needed — this is a traditional server-rendered PHP
application. The `public/` directory is the web document root,
keeping `src/`, `pages/`, `templates/`, `config.php`, and
`migrations/` outside the web-accessible directory for security.

## Complexity Tracking

No constitution violations. No complexity justifications needed.
