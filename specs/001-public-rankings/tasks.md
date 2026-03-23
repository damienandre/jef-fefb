# Tasks: Classement Public du Circuit JEF

**Input**: Design documents from `/specs/001-public-rankings/`
**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md, contracts/routes.md, quickstart.md

**Tests**: Included per Constitution Principle II (Quality Through Testing). Unit tests for TRF parser, ranking calculator, age category. Integration test for import workflow.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `src/`, `tests/`, `public/`, `pages/`, `templates/`, `migrations/` at repository root
- PSR-4 autoload: `src/` → `Jef\` namespace

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization, Composer, config, front controller

- [x] T001 Create composer.json with PHPUnit dev dependency and PSR-4 autoload (Jef\ → src/) in composer.json
- [x] T002 [P] Create config.example.php with database credentials, base_url, and app settings in config.example.php
- [x] T003 [P] Create phpunit.xml with test suite configuration pointing to tests/ in phpunit.xml
- [x] T004 [P] Create public/.htaccess with RewriteEngine rules directing all requests to index.php in public/.htaccess
- [x] T005 Create public/index.php with front controller using match statement for all routes (/, /tournoi, /admin/login, /admin, /admin/import, /admin/settings, /admin/logout, default 404) per contracts/routes.md in public/index.php

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**CRITICAL**: No user story work can begin until this phase is complete

- [x] T006 Create src/Database.php with PDO singleton connection helper using config.php credentials, utf8mb4 charset, exception error mode in src/Database.php
- [x] T007 [P] Create src/View.php with template rendering helper (render method that extracts data array into variables and includes template file, layout support) in src/View.php
- [x] T008 Create migrations/001_create_schema.sql with all jef_ tables (jef_seasons, jef_players, jef_tournaments, jef_tournament_players, jef_circuit_rankings, jef_circuit_results, jef_users, jef_settings, jef_schema_migrations) per data-model.md, including indexes and foreign keys in migrations/001_create_schema.sql
- [x] T009 Create migrate.php CLI script that reads jef_schema_migrations version, finds and executes pending numbered SQL files from migrations/ directory in migrate.php
- [x] T010 Create templates/layout.php with base HTML layout in French: DOCTYPE, meta viewport, charset utf8, link to css/style.css, FEFB logo from jef_settings (fallback to text), navigation, content block, footer in templates/layout.php
- [x] T011 [P] Create pages/404.php page controller that renders a styled French 404 error page using templates/layout.php in pages/404.php
- [x] T011a [P] Create public/uploads/.gitkeep to ensure the uploads directory exists in version control and is writable for logo uploads

**Checkpoint**: Foundation ready - user story implementation can now begin

---

## Phase 3: User Story 1 - Consulter le classement general (Priority: P1) MVP

**Goal**: Display the general circuit rankings for the current year with all player details (name, age category, total points, per-tournament ranking and points)

**Independent Test**: Open the site in a browser and verify the general ranking table displays correctly with data for the current year, sorted by total points descending

### Tests for User Story 1

- [x] T011b [P] [US1] Create tests/Unit/Ranking/AgeCategoryTest.php with test cases for age category determination: boundary dates for each category (U8 through U20), edge cases (exactly on Jan 1st boundary, Dec 31st) in tests/Unit/Ranking/AgeCategoryTest.php

### Implementation for User Story 1

- [x] T012 [US1] Create pages/rankings.php page controller that queries jef_circuit_rankings (type='general') joined with jef_players for the current year, also queries jef_tournaments and jef_circuit_results for per-tournament columns, handles empty state message in pages/rankings.php
- [x] T013 [US1] Create templates/rankings.html.php with rankings table: columns for rang, nom (prenom + nom), categorie d'age (calculated from birth_date), total points, then dynamic columns per tournament (classement + points from jef_circuit_results), dash for non-participation, ex-aequo ranking display in templates/rankings.html.php
- [x] T014 [P] [US1] Create public/css/style.css with simple modern responsive styles: clean table styling, mobile-friendly layout (horizontal scroll for wide tables), French typography, header with logo area, color scheme in public/css/style.css
- [x] T015 [US1] Add src/Ranking/AgeCategory.php with static method to determine age category (U8/U10/U12/U14/U16/U20) from birth date and season year (age on January 1st) in src/Ranking/AgeCategory.php
- [x] T015a [US1] Add sample seed data SQL file with 1 season, 2 tournaments, ~10 players, and pre-calculated circuit rankings for development/testing in migrations/seed_dev_data.sql

**Checkpoint**: General rankings for current year display correctly with seed data. Site is deployable.

---

## Phase 4: User Story 2 - Naviguer par annee (Priority: P2)

**Goal**: Allow visitors to select a year and view rankings for that year, with URL persistence

**Independent Test**: Select a past year in the dropdown and verify rankings update. Reload the page and verify the year selection is preserved via URL.

### Implementation for User Story 2

- [x] T016 [US2] Update pages/rankings.php to accept ?annee= query parameter, query jef_seasons for available years, default to current year when no parameter provided in pages/rankings.php
- [x] T017 [US2] Update templates/rankings.html.php to add year selector dropdown above the table, pre-select current year, form submits via GET to preserve URL state in templates/rankings.html.php

**Checkpoint**: Year navigation works. Rankings switch correctly between years. URL reflects selection.

---

## Phase 5: User Story 3 - Classement par categorie d'age (Priority: P3)

**Goal**: Display separate rankings calculated per age category (U8, U10, U12, U14, U16, U20) with their own ranks, not a simple filter of the general ranking

**Independent Test**: Select an age category and verify the displayed ranking has its own rank numbering (1, 2, 3...) specific to that category, different from the general ranking

### Implementation for User Story 3

- [x] T019 [US3] Update pages/rankings.php to accept ?categorie= query parameter (values: general, U8, U10, U12, U14, U16, U20), query jef_circuit_rankings with matching ranking_type, combine with ?annee= parameter in pages/rankings.php
- [x] T020 [US3] Update templates/rankings.html.php to add category selector dropdown (Toutes les categories + U8/U10/U12/U14/U16/U20), preserve selection across year changes, update form action to include both parameters in templates/rankings.html.php

**Checkpoint**: Category-specific rankings display with their own rank numbering. Combined year + category filtering works.

---

## Phase 6: User Story 4 - Import TRF et calcul des classements (Priority: P4)

**Goal**: Admin imports TRF files, system parses them, saves tournament data, identifies/creates players, and recalculates all circuit rankings (general + per category) within a single transaction

**Independent Test**: Log in as admin, upload a valid TRF file, verify tournament data is saved and rankings are recalculated. Upload an invalid file and verify error message with no data changes.

### Tests for User Story 4

- [x] T021 [P] [US4] Create tests/fixtures/sample.trf with a valid TRF16 test file containing ~10 players across 5 rounds with varied results (wins, losses, draws, byes) and realistic header data in tests/fixtures/sample.trf
- [x] T022 [P] [US4] Create tests/fixtures/invalid.trf with an invalid TRF file (malformed 001 lines, missing required headers) for error handling tests in tests/fixtures/invalid.trf
- [x] T023 [P] [US4] Create tests/Unit/Trf/TrfParserTest.php with test cases: parse valid file extracts correct tournament name/date/players, parse player record extracts name/birthdate/fideId/points/rank/rounds, handle missing FIDE ID, handle partial birth date, reject invalid file in tests/Unit/Trf/TrfParserTest.php
- [x] T024 [P] [US4] Create tests/Unit/Ranking/RankingCalculatorTest.php with test cases: calculate general ranking from tournament results, calculate per-category rankings, handle ex-aequo, handle player with no results in tests/Unit/Ranking/RankingCalculatorTest.php
- [x] T025 [US4] Create tests/Integration/ImportWorkflowTest.php with test cases: full import cycle (parse TRF → save tournament → recalculate rankings), reimport replaces data, invalid file rolls back transaction in tests/Integration/ImportWorkflowTest.php

### Implementation for User Story 4

- [x] T026 [P] [US4] Create src/Trf/TrfTournament.php DTO class with properties: name, city, federation, dateStart, dateEnd, playerCount, roundCount, arbiter, timeControl, roundDates in src/Trf/TrfTournament.php
- [x] T027 [P] [US4] Create src/Trf/TrfPlayer.php DTO class with properties: startingRank, sex, title, name (last,first), fideRating, federation, fideId, birthDate, points, rank, rounds (array of opponent/color/result) in src/Trf/TrfPlayer.php
- [x] T028 [US4] Create src/Trf/TrfParser.php with parse(string $content): parses TRF16 content, extracts tournament headers (012-132 DIN codes), parses 001 player records using regex with named groups, returns TrfTournament + TrfPlayer[] array, throws exception on invalid format in src/Trf/TrfParser.php
- [x] T029 [US4] Create src/Ranking/RankingCalculator.php with recalculate(PDO $db, int $seasonId): deletes existing jef_circuit_results and jef_circuit_rankings for the season, recalculates circuit points per tournament per player (general + per age category), computes ranks with ex-aequo handling (same rank, skip next), inserts into jef_circuit_results and jef_circuit_rankings in src/Ranking/RankingCalculator.php
- [x] T030 [P] [US4] Create src/Auth.php with static methods: requireAuth() (redirect to /admin/login if not authenticated), login(PDO, username, password) (password_verify + session_regenerate_id), logout() (session_destroy), generateCsrfToken(), validateCsrfToken(string) in src/Auth.php
- [x] T031 [P] [US4] Create cli/create-user.php CLI script that accepts username and password arguments, hashes password with password_hash(), inserts into jef_users table in cli/create-user.php
- [x] T032 [US4] Create src/ImportService.php with import(PDO, int $seasonYear, int $sortOrder, string $trfContent): within single transaction — parse TRF via TrfParser, create/get season, create/update tournament, match existing players by FIDE ID then by name+birthdate or create new, insert jef_tournament_players with rounds_data JSON, call RankingCalculator::recalculate(), rollback on error in src/ImportService.php
- [x] T033 [US4] Create templates/admin/layout.php with admin base layout: simplified header, admin navigation (Tableau de bord, Importer TRF, Parametres, Deconnexion), content block in templates/admin/layout.php
- [x] T034 [US4] Create pages/admin/login.php + templates/admin/login.html.php with GET (login form with CSRF token) and POST (validate credentials via Auth::login, redirect to /admin or show error) in pages/admin/login.php and templates/admin/login.html.php
- [x] T035 [US4] Create pages/admin/dashboard.php + templates/admin/dashboard.html.php with list of seasons and their tournaments (name, date, player count), links to import page in pages/admin/dashboard.php and templates/admin/dashboard.html.php
- [x] T036 [US4] Create pages/admin/import.php + templates/admin/import.html.php with GET (upload form: season year input, sort order input, file input, CSRF token) and POST (validate file upload, read content, call ImportService::import, redirect with success/error flash message) in pages/admin/import.php and templates/admin/import.html.php
- [x] T037 [US4] Create pages/admin/settings.php + templates/admin/settings.html.php with GET (logo upload form, current logo preview) and POST (validate image upload PNG/JPG/SVG, save to public/uploads/, store path in jef_settings, CSRF validation) in pages/admin/settings.php and templates/admin/settings.html.php
- [x] T038 [US4] Create pages/admin/logout.php with POST handler: validate CSRF token, call Auth::logout(), redirect to /admin/login in pages/admin/logout.php

**Checkpoint**: Full admin workflow operational. TRF import creates tournaments/players and calculates rankings. Rankings visible on public site. Invalid files rejected safely.

---

## Phase 7: User Story 5 - Consulter le detail d'un tournoi (Priority: P5)

**Goal**: Display a tournament detail page with the full grid (player results per round: opponent, color, result)

**Independent Test**: Click a tournament name in the rankings table header and verify the tournament grid page displays with per-round details for each player

### Implementation for User Story 5

- [x] T039 [US5] Create pages/tournament.php page controller that queries jef_tournaments by id parameter, queries jef_tournament_players with jef_players join, decodes rounds_data JSON, resolves opponent names from starting_rank, returns 404 if not found in pages/tournament.php
- [x] T040 [US5] Create templates/tournament.html.php with tournament grid table: tournament name/date/location header, columns for rang, nom, points, then one column per round showing opponent name + color indicator + result symbol, back link to rankings in templates/tournament.html.php
- [x] T041 [US5] Update templates/rankings.html.php to make tournament column headers clickable links to /tournoi?id={tournament_id} in templates/rankings.html.php

**Checkpoint**: Tournament detail pages accessible from rankings. Full round-by-round grid visible.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final quality, security, and deployment readiness

- [x] T042 Run quickstart.md validation: follow all setup steps from scratch, verify local dev environment works end-to-end
- [x] T043 Security review: verify all POST routes check CSRF token, all admin routes call Auth::requireAuth(), all SQL uses prepared statements, file upload validates type and size

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can proceed sequentially in priority order (P1 → P2 → P3 → P4 → P5)
  - US2 depends on US1 (extends the rankings page)
  - US3 depends on US1 (extends the rankings page)
  - US4 is independent of US1-US3 (admin area)
  - US5 depends on US1 (links from rankings page) and US4 (tournament data from TRF import)
- **Polish (Phase 8)**: Depends on all user stories being complete

### User Story Dependencies

- **US1 (P1)**: Can start after Foundational — no dependencies on other stories
- **US2 (P2)**: Depends on US1 — extends pages/rankings.php and templates/rankings.html.php
- **US3 (P3)**: Depends on US1 — extends pages/rankings.php and templates/rankings.html.php (AgeCategory.php created in US1)
- **US4 (P4)**: Can start after Foundational — independent admin area (TRF parser, import, auth)
- **US5 (P5)**: Depends on US4 (needs tournament data) — adds tournament detail page and links from US1

### Within Each User Story

- Tests written first when included (US1, US4)
- DTOs before parsers before services
- Page controllers before templates
- Story complete before moving to next priority

### Parallel Opportunities

- Setup: T002, T003, T004 can run in parallel
- Foundational: T007 and T011 can run in parallel (after T006)
- US1: T014 can run in parallel with T012/T013
- US4: T021/T022/T023/T024 (test fixtures + test files) in parallel; T026/T027 (DTOs) in parallel; T030/T031 (auth + CLI) in parallel

---

## Parallel Example: User Story 4

```bash
# Phase 1 - Test fixtures and test files (all parallel):
Task T021: "Create sample TRF test fixture in tests/fixtures/sample.trf"
Task T022: "Create invalid TRF test fixture in tests/fixtures/invalid.trf"
Task T023: "Create TRF parser unit tests in tests/Unit/Trf/TrfParserTest.php"
Task T024: "Create ranking calculator unit tests in tests/Unit/Ranking/RankingCalculatorTest.php"

# Phase 2 - DTOs (parallel):
Task T026: "Create TrfTournament DTO in src/Trf/TrfTournament.php"
Task T027: "Create TrfPlayer DTO in src/Trf/TrfPlayer.php"

# Phase 3 - Auth + CLI (parallel):
Task T030: "Create Auth helper in src/Auth.php"
Task T031: "Create admin user CLI in cli/create-user.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 + 4)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1 (rankings display with seed data)
4. **STOP and VALIDATE**: Rankings table displays correctly
5. Complete Phase 6: User Story 4 (TRF import + ranking calculation)
6. **STOP and VALIDATE**: Full end-to-end — import TRF, see rankings
7. Deploy to Infomaniak as MVP

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. US1 → Rankings visible with seed data → Demo to FEFB
3. US4 → Admin can import TRF files → First real data
4. US2 → Year navigation → Historical rankings
5. US3 → Category rankings → Complete ranking views
6. US5 → Tournament detail pages → Full feature set
7. Polish → Security review, deployment validation

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- All database tables use `jef_` prefix per CLAUDE.md convention
- Ranking calculation rules will be provided by the user — T029 (RankingCalculator) should be designed with a configurable points system
- TRF parser references: Python sklangen/TRF library, FIDE TRF16 specification (see research.md)
