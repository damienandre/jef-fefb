# Routes Contract: Classement Public du Circuit JEF

**Date**: 2026-02-27

## Public Routes (no authentication)

### GET /

**Description**: Page d'accueil — classement general du circuit JEF
pour l'annee en cours.

**Query parameters**:
- `annee` (optional, int) — Year to display. Default: current year.
- `categorie` (optional, string) — Age category filter. Values:
  `general` (default), `U8`, `U10`, `U12`, `U14`, `U16`, `U20`.

**Response**: HTML page with rankings table. Columns: rang, nom,
categorie d'age, total points, then one column pair (classement +
points) per tournament in the season.

**Examples**:
- `/` — General ranking, current year
- `/?annee=2025` — General ranking, 2025
- `/?categorie=U12` — U12 ranking, current year
- `/?annee=2025&categorie=U14` — U14 ranking, 2025

**Empty state**: Message "Aucun classement disponible pour cette
annee/categorie."

### GET /tournoi

**Description**: Page de detail d'un tournoi specifique.

**Query parameters**:
- `id` (required, int) — Tournament ID.

**Response**: HTML page with tournament grid. Columns: rang, nom,
points, then one column per round (adversaire, couleur, resultat).

**Error**: 404 if tournament ID not found.

**Example**: `/tournoi?id=42`

## Admin Routes (authentication required)

### GET /admin/login

**Description**: Login form for admin area.

**Response**: HTML page with username/password form.

### POST /admin/login

**Description**: Process login.

**Form fields**:
- `username` (string, required)
- `password` (string, required)
- `csrf_token` (string, required)

**Success**: Redirect to `/admin`.
**Failure**: Redirect to `/admin/login` with error message.

### GET /admin

**Description**: Admin dashboard. Lists seasons and their
tournaments. Provides access to TRF import and settings.

**Response**: HTML page with season/tournament overview and action
links.

### GET /admin/import

**Description**: TRF file upload form.

**Response**: HTML page with file upload form. Fields: season year
(select), tournament sort order (number), TRF file (file input).

### POST /admin/import

**Description**: Process TRF file upload and import.

**Form fields**:
- `season_year` (int, required) — Season year. Creates season if
  it does not exist.
- `sort_order` (int, required) — Tournament position in circuit.
- `trf_file` (file, required) — TRF file upload.
- `csrf_token` (string, required)

**Success**: Redirect to `/admin` with success message showing
imported player count and recalculated rankings.
**Failure**: Redirect to `/admin/import` with error message
(invalid TRF, parse errors). No data modified on failure.

### GET /admin/settings

**Description**: Application settings (logo upload).

**Response**: HTML page with logo upload form and current logo
preview.

### POST /admin/settings

**Description**: Update application settings.

**Form fields**:
- `logo` (file, optional) — FEFB logo image (PNG/JPG/SVG).
- `csrf_token` (string, required)

**Success**: Redirect to `/admin/settings` with success message.

### POST /admin/logout

**Description**: Destroy admin session.

**Form fields**:
- `csrf_token` (string, required)

**Success**: Redirect to `/admin/login`.

## Error Pages

- **404**: Styled error page in French.
- **500**: Generic error page in French (no technical details
  exposed).
