# Research: Classement Public du Circuit JEF

**Date**: 2026-02-27
**Feature**: 001-public-rankings

## R1: TRF File Parsing in PHP

**Decision**: Write a custom TRF16 parser in PHP.

**Rationale**: The only existing PHP package (`chessfy/trf`) is
abandoned (2021), tightly coupled to Laravel 8, has 1 total install
on Packagist, and is incompatible with PHP 8.5. The TRF format is
well-documented and the parsing logic is straightforward (fixed-width
fields + regex). The Python `sklangen/TRF` library provides an
excellent reference architecture.

**Alternatives considered**:
- `chessfy/trf` — Rejected: abandoned, Laravel-coupled, PHP 7.4 only,
  missing birth date property, would break on PHP 8.2+ dynamic
  properties deprecation.
- Using a Python microservice for parsing — Rejected: violates
  Hosting-Aware Design principle (shared hosting, no background
  processes).

**Key implementation details**:
- TRF16 player records (001 lines) are fixed-width, best parsed with
  a regex pattern with named groups.
- Field positions (after "001" prefix): starting rank (1-4), sex (6),
  title (8-9), name (11-43), rating (45-48), federation (50-52),
  FIDE ID (54-64), birth date (66-75), points (77-80), rank (82-85),
  round results (86+, 10 chars each).
- Tournament header fields: 012 (name), 022 (city), 032 (federation),
  042 (start date), 052 (end date), 062 (player count), 092 (type),
  102 (arbiter), 122 (time control), 132 (round dates).
- Round results: 4-char opponent rank + 1-char color (w/b/s/-) +
  1-char result (1/0/=/+/-/h/f/u/z).
- Handle encoding carefully (mb_ functions), missing fields (empty
  FIDE ID, partial birth dates), and unknown DIN codes gracefully.

## R2: Application Architecture

**Decision**: Plain PHP with no framework. Front controller pattern
with `match` statement routing.

**Rationale**: The project has ~7 routes and serves server-rendered
HTML pages. A framework would add complexity without value per the
Simplicity First constitution principle. Plain PHP with PDO is the
most direct approach for Infomaniak shared hosting.

**Alternatives considered**:
- Slim 4 — Rejected: PSR-7/PSR-15 middleware architecture designed
  for APIs, 21 Composer dependencies, overkill for 7 routes.
- Flight PHP — Rejected: lighter than Slim but still adds 5 Composer
  dependencies for routing and templating that a `match` statement
  handles in 10 lines.
- Laravel/Symfony — Rejected: massively over-engineered for this
  scope, violates Simplicity First principle.

## R3: Database Migrations

**Decision**: Numbered SQL files with a version-tracking table and
a simple PHP migration runner script.

**Rationale**: The project has ~5 tables and will have few schema
changes over its lifetime. A full migration tool (Phinx) adds
complexity and a learning curve that are not justified. Numbered SQL
files are version-controlled, ordered, and repeatable.

**Alternatives considered**:
- Phinx — Rejected: adds Composer dependency, PHP migration API
  learning curve, rollback features unlikely to be used.
- Manual SQL dump — Rejected: not repeatable for incremental changes
  on a live database.

## R4: Admin Authentication

**Decision**: PHP native `password_hash`/`password_verify` with
session-based authentication and CSRF protection.

**Rationale**: 1-3 admin users, no registration flow, no password
reset needed (admin can reset via CLI/DB). PHP's built-in functions
provide secure bcrypt hashing. Sessions are the standard server-side
auth mechanism for server-rendered apps.

**Alternatives considered**:
- Third-party auth library (delight-im/PHP-Auth) — Rejected: solves
  problems we do not have (registration, email verification, account
  locking).
- Token-based/JWT auth — Rejected: designed for stateless APIs, adds
  complexity for server-rendered pages.

## R5: Infomaniak Shared Hosting Compatibility

**Decision**: All technical choices validated against Infomaniak
shared hosting capabilities.

**Rationale**: Confirmed via Infomaniak documentation:
- Apache with mod_rewrite via .htaccess: supported
- PHP 8.5 with standard extensions (PDO, mbstring, json, session):
  available
- MariaDB: available (unlimited databases within storage quota)
- SSH access: available (for Composer install, migrations)
- Document root can be set to `public/` subdirectory
- .user.ini supported for PHP directives

**No blockers identified.**

## R6: Frontend Approach

**Decision**: Server-rendered HTML with plain CSS. No JavaScript
framework. Minimal vanilla JS only for interactive elements (year
selector, category filter).

**Rationale**: The site is primarily a data display application.
Rankings tables are static content. Server-side rendering is simpler,
faster for first load, SEO-friendly, and requires no build toolchain.
Per Simplicity First, a JS framework would be over-engineering.

**Alternatives considered**:
- Alpine.js — Considered acceptable if interactivity needs grow, but
  not needed for initial scope (year/category selection can use simple
  form submissions or minimal vanilla JS).
- htmx — Similar to Alpine.js, acceptable future addition but not
  justified for initial scope.
