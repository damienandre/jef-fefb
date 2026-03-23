# Data Model: Classement Public du Circuit JEF

**Date**: 2026-02-27
**Database**: MariaDB 10.11 (InnoDB)

## Entity Relationship Overview

```
Season 1──* Tournament 1──* TournamentResult *──1 Player
                                    │
Season 1──* CircuitRanking *──1 Player
```

A Season groups Tournaments. Each Tournament has TournamentResults
for participating Players. CircuitRankings are calculated per Season
(general + per age category) from TournamentResults.

## Entities

### jef_seasons

Represents a year of the JEF circuit.

| Column     | Type         | Constraints              | Description            |
|------------|--------------|--------------------------|------------------------|
| id         | INT UNSIGNED | PK, AUTO_INCREMENT       | Internal ID            |
| year       | SMALLINT     | UNIQUE, NOT NULL         | Calendar year (e.g. 2026) |
| status     | ENUM         | NOT NULL, DEFAULT 'active' | 'active' or 'finished' |
| created_at | DATETIME     | NOT NULL, DEFAULT NOW()  | Record creation time   |

### jef_players

A youth chess player participating in the JEF circuit.

| Column      | Type             | Constraints                | Description              |
|-------------|------------------|----------------------------|--------------------------|
| id          | INT UNSIGNED     | PK, AUTO_INCREMENT         | Internal ID              |
| fide_id     | INT UNSIGNED     | UNIQUE, NULL               | FIDE ID (optional)       |
| last_name   | VARCHAR(100)     | NOT NULL                   | Family name              |
| first_name  | VARCHAR(100)     | NOT NULL                   | Given name               |
| birth_date  | DATE             | NULL                       | Date of birth (may be missing in TRF) |
| created_at  | DATETIME         | NOT NULL, DEFAULT NOW()    | Record creation time     |
| updated_at  | DATETIME         | NOT NULL, DEFAULT NOW() ON UPDATE NOW() | Last update |

**Uniqueness rule**: FIDE ID when present, otherwise (last_name,
first_name, birth_date) composite. Enforced at application level
during TRF import (not a DB unique constraint on name+dob due to
the FIDE ID priority logic).

**Unique index**: `UNIQUE(fide_id)` where fide_id IS NOT NULL.

### jef_tournaments

A single tournament (manche) within a season's circuit.

| Column       | Type             | Constraints              | Description                |
|--------------|------------------|--------------------------|----------------------------|
| id           | INT UNSIGNED     | PK, AUTO_INCREMENT       | Internal ID                |
| season_id    | INT UNSIGNED     | FK → jef_seasons.id, NOT NULL | Season this belongs to    |
| name         | VARCHAR(200)     | NOT NULL                 | Tournament name (from TRF) |
| location     | VARCHAR(200)     | NULL                     | City/venue (from TRF 022)  |
| date_start   | DATE             | NOT NULL                 | Start date (from TRF 042)  |
| date_end     | DATE             | NULL                     | End date (from TRF 052)    |
| round_count  | TINYINT UNSIGNED | NOT NULL                 | Number of rounds           |
| player_count | SMALLINT UNSIGNED| NOT NULL                 | Number of participants     |
| sort_order   | TINYINT UNSIGNED | NOT NULL                 | Display order in circuit   |
| trf_raw      | MEDIUMTEXT       | NULL                     | Original TRF file content  |
| created_at   | DATETIME         | NOT NULL, DEFAULT NOW()  | Record creation time       |

**Unique constraint**: `UNIQUE(season_id, sort_order)` — one
tournament per position in a season.

### jef_tournament_players

A player's participation and per-round results in a tournament.
This stores the full tournament grid data from the TRF file.

| Column         | Type             | Constraints                | Description                  |
|----------------|------------------|----------------------------|------------------------------|
| id             | INT UNSIGNED     | PK, AUTO_INCREMENT         | Internal ID                  |
| tournament_id  | INT UNSIGNED     | FK → jef_tournaments.id, NOT NULL | Tournament reference      |
| player_id      | INT UNSIGNED     | FK → jef_players.id, NOT NULL  | Player reference             |
| starting_rank  | SMALLINT UNSIGNED| NOT NULL                   | Starting rank in tournament  |
| final_rank     | SMALLINT UNSIGNED| NULL                       | Final rank in tournament     |
| points         | DECIMAL(4,1)     | NOT NULL                   | Total points scored          |
| rounds_data    | JSON             | NOT NULL                   | Per-round details (see below)|
| created_at     | DATETIME         | NOT NULL, DEFAULT NOW()    | Record creation time         |

**Unique constraint**: `UNIQUE(tournament_id, player_id)` — one
entry per player per tournament.

**rounds_data JSON structure**:
```json
[
  {
    "round": 1,
    "opponent_rank": 42,
    "color": "w",
    "result": "1"
  },
  {
    "round": 2,
    "opponent_rank": 7,
    "color": "b",
    "result": "="
  }
]
```

Result codes: "1" (win), "0" (loss), "=" (draw), "+" (forfeit win),
"-" (forfeit loss/absent), "h" (half-point bye), "f" (full-point
bye), "u" (unplayed bye), "z" (zero-point bye).

### jef_circuit_rankings

Calculated ranking of a player for a season, in a given context
(general or age category). Recalculated after each TRF import.

| Column          | Type             | Constraints                | Description                    |
|-----------------|------------------|----------------------------|--------------------------------|
| id              | INT UNSIGNED     | PK, AUTO_INCREMENT         | Internal ID                    |
| season_id       | INT UNSIGNED     | FK → jef_seasons.id, NOT NULL  | Season reference               |
| player_id       | INT UNSIGNED     | FK → jef_players.id, NOT NULL  | Player reference               |
| ranking_type    | VARCHAR(20)      | NOT NULL                   | 'general' or category (e.g. 'U12') |
| total_points    | DECIMAL(6,1)     | NOT NULL                   | Calculated total circuit points |
| rank            | SMALLINT UNSIGNED| NOT NULL                   | Position in this ranking        |
| created_at      | DATETIME         | NOT NULL, DEFAULT NOW()    | Record creation time           |
| updated_at      | DATETIME         | NOT NULL, DEFAULT NOW() ON UPDATE NOW() | Last recalculation |

**Unique constraint**: `UNIQUE(season_id, player_id, ranking_type)`
— one ranking per player per type per season.

### jef_circuit_results

Points earned by a player in a specific tournament for circuit
ranking purposes. Derived from tournament_players data using
FEFB ranking rules.

| Column          | Type             | Constraints                | Description                    |
|-----------------|------------------|----------------------------|--------------------------------|
| id              | INT UNSIGNED     | PK, AUTO_INCREMENT         | Internal ID                    |
| season_id       | INT UNSIGNED     | FK → jef_seasons.id, NOT NULL  | Season reference               |
| tournament_id   | INT UNSIGNED     | FK → jef_tournaments.id, NOT NULL | Tournament reference        |
| player_id       | INT UNSIGNED     | FK → jef_players.id, NOT NULL  | Player reference               |
| ranking_type    | VARCHAR(20)      | NOT NULL                   | 'general' or category          |
| tournament_rank | SMALLINT UNSIGNED| NOT NULL                   | Player's rank in this tournament (within context) |
| circuit_points  | DECIMAL(6,1)     | NOT NULL                   | Points earned for the circuit  |

**Unique constraint**:
`UNIQUE(season_id, tournament_id, player_id, ranking_type)`

### jef_users

Admin users for the back-office.

| Column        | Type             | Constraints              | Description          |
|---------------|------------------|--------------------------|----------------------|
| id            | INT UNSIGNED     | PK, AUTO_INCREMENT       | Internal ID          |
| username      | VARCHAR(50)      | UNIQUE, NOT NULL         | Login username       |
| password_hash | VARCHAR(255)     | NOT NULL                 | bcrypt hash          |
| created_at    | DATETIME         | NOT NULL, DEFAULT NOW()  | Record creation time |

### jef_settings

Key-value store for application configuration (logo path, etc.).

| Column | Type         | Constraints        | Description        |
|--------|--------------|--------------------|--------------------|
| key    | VARCHAR(50)  | PK                 | Setting name       |
| value  | TEXT         | NULL               | Setting value      |

### jef_schema_migrations

Tracks applied database migrations.

| Column     | Type         | Constraints        | Description               |
|------------|--------------|--------------------|--------------------------  |
| version    | INT UNSIGNED | PK                 | Migration number applied  |
| applied_at | DATETIME     | NOT NULL, DEFAULT NOW() | When applied         |

## Age Category Determination

A player's age category for a season is determined by their age on
January 1st of the season year:

- **U8**: age < 8 on Jan 1st
- **U10**: age >= 8 and < 10 on Jan 1st
- **U12**: age >= 10 and < 12 on Jan 1st
- **U14**: age >= 12 and < 14 on Jan 1st
- **U16**: age >= 14 and < 16 on Jan 1st
- **U20**: age >= 16 and < 20 on Jan 1st

Calculated at application level from `jef_players.birth_date` and
the season year. Not stored in the database (derived value).

## Foreign Key Constraints

All foreign keys use InnoDB with:
- `ON DELETE RESTRICT` (prevent orphaned records)
- `ON UPDATE CASCADE` (propagate ID changes)

## Indexes

Beyond primary keys and unique constraints:
- `jef_circuit_rankings(season_id, ranking_type, rank)` — for ranked
  listing queries (the main public page query).
- `jef_circuit_results(season_id, ranking_type)` — for circuit
  results per season listing.
- `jef_tournament_players(tournament_id, final_rank)` — for
  tournament detail page.
- `jef_players(last_name, first_name, birth_date)` — for player
  lookup during TRF import.

## Transaction Boundaries

Per Data Integrity constitution principle:
- **TRF import**: Single transaction wrapping: delete existing
  jef_tournament_players for that tournament → insert new
  jef_tournament_players → delete existing jef_circuit_results →
  recalculate jef_circuit_results → delete existing
  jef_circuit_rankings → recalculate jef_circuit_rankings.
  Rollback on any error.
