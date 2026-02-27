<!--
  Sync Impact Report
  ===================
  Version change: N/A → 1.0.0 (initial ratification)
  Modified principles: N/A (first version)
  Added sections: Core Principles (5), Technical Constraints,
                  Development Workflow, Governance
  Removed sections: None
  Templates requiring updates:
    - .specify/templates/plan-template.md ✅ compatible (no changes needed)
    - .specify/templates/spec-template.md ✅ compatible (no changes needed)
    - .specify/templates/tasks-template.md ✅ compatible (no changes needed)
    - .specify/templates/commands/*.md ✅ compatible (no changes needed)
  Follow-up TODOs: None
-->

# JEF Constitution

## Core Principles

### I. Simplicity First

Every design decision MUST favor the simplest viable solution.
- YAGNI: features and abstractions MUST NOT be introduced until a
  concrete, immediate need exists.
- No framework unless its value clearly outweighs the added
  complexity; plain PHP is acceptable where sufficient.
- Database schema MUST remain normalized only to the degree that
  serves actual query patterns — avoid speculative modeling.
- If two approaches deliver equivalent outcomes, the one with fewer
  moving parts MUST be chosen.

**Rationale**: The project targets a specific, well-scoped domain
(youth chess rankings). Over-engineering wastes effort and makes
maintenance harder on a shared hosting environment with limited
resources.

### II. Quality Through Testing

All non-trivial logic MUST be covered by automated tests.
- Unit tests MUST exist for ranking calculations, score
  computations, and any business rule logic.
- Integration tests MUST verify database interactions for critical
  paths (tournament import, ranking generation).
- Tests MUST be runnable locally via PHPUnit without external
  service dependencies beyond MariaDB.
- Bug fixes MUST include a regression test before the fix is applied.

**Rationale**: Ranking accuracy is the core value proposition.
Incorrect rankings erode trust with the FEFB, clubs, and parents.
Testing is the primary safeguard against data errors.

### III. Data Integrity

Tournament and ranking data MUST be accurate, consistent, and
auditable at all times.
- All ranking-affecting writes MUST occur within database
  transactions.
- Foreign key constraints MUST be enforced at the database level
  (MariaDB InnoDB).
- Input validation MUST happen at the application boundary before
  any persistence.
- Ranking recalculations MUST be idempotent — running the same
  calculation twice MUST produce identical results.

**Rationale**: The JEF circuit rankings directly affect youth
players. Errors in ranking data can cause disputes and undermine
the credibility of the federation's competition system.

### IV. Hosting-Aware Design

All code MUST run within the constraints of Infomaniak shared
hosting.
- No long-running background processes or daemon requirements.
- No custom server configuration (no custom Apache/Nginx rules
  beyond standard `.htaccess`).
- Memory and execution time MUST stay within shared hosting limits;
  batch operations MUST be chunked if needed.
- Dependencies MUST be installable via Composer and MUST NOT
  require native extensions beyond those available on Infomaniak
  (PHP 8.5 standard extensions).
- File uploads and generated assets MUST use paths compatible with
  the shared hosting directory structure.

**Rationale**: The project will be deployed on Infomaniak shared
hosting. Solutions that require VPS-level control are not viable.
Every technical choice MUST be validated against this constraint.

### V. Incremental Delivery

Features MUST be delivered as independently testable and deployable
increments.
- Each user story MUST be viable as a standalone slice of
  functionality.
- No "big bang" releases — the application MUST be deployable after
  each completed story.
- Database migrations MUST be forward-compatible and non-destructive
  (no data loss on schema changes).
- The first deployable increment MUST deliver core ranking
  visibility before any administrative or import features.

**Rationale**: Incremental delivery reduces risk, enables early
feedback from FEFB stakeholders, and ensures the application is
always in a releasable state.

## Technical Constraints

- **Language**: PHP 8.5
- **Database**: MariaDB 10.11 (InnoDB engine required for
  transactions and foreign keys)
- **Hosting**: Infomaniak shared hosting (no root access, no custom
  server config, no background workers)
- **Package Manager**: Composer (all dependencies MUST be declared
  in `composer.json`)
- **Testing**: PHPUnit (MUST be runnable via `vendor/bin/phpunit`)
- **Version Control**: Git with feature branches; `main` branch
  MUST always be deployable

## Development Workflow

- Code changes MUST be submitted via pull requests against `main`.
- Every PR MUST pass all existing tests before merge.
- Database schema changes MUST be delivered as migration files,
  never as manual SQL.
- Commit messages MUST follow conventional commits format
  (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`).
- Dependencies MUST be reviewed before addition — each new package
  MUST justify its inclusion against the Simplicity First principle.

## Governance

This constitution is the authoritative reference for all
development decisions on the JEF project. In case of conflict
between this document and any other practice or convention, this
constitution prevails.

- **Amendments**: Any change to this constitution MUST be documented
  with a version bump, rationale, and effective date.
- **Versioning**: MAJOR for principle removals or redefinitions,
  MINOR for new principles or material expansions, PATCH for
  clarifications and typo fixes.
- **Compliance**: All pull requests and code reviews MUST verify
  adherence to the principles defined above. Non-compliance MUST be
  flagged and resolved before merge.

**Version**: 1.0.0 | **Ratified**: 2026-02-27 | **Last Amended**: 2026-02-27
