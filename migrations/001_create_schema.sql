-- JEF Circuit Rankings - Initial Schema
-- All tables prefixed with jef_ per project convention

CREATE TABLE IF NOT EXISTS jef_schema_migrations (
    version INT UNSIGNED NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_seasons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    year SMALLINT NOT NULL,
    status ENUM('active', 'finished') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fide_id INT UNSIGNED NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    birth_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fide_id (fide_id),
    KEY idx_name_dob (last_name, first_name, birth_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_tournaments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    season_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    location VARCHAR(200) NULL,
    date_start DATE NOT NULL,
    date_end DATE NULL,
    round_count TINYINT UNSIGNED NOT NULL,
    player_count SMALLINT UNSIGNED NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL,
    trf_raw MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_season_order (season_id, sort_order),
    CONSTRAINT fk_tournaments_season FOREIGN KEY (season_id)
        REFERENCES jef_seasons (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_tournament_players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    starting_rank SMALLINT UNSIGNED NOT NULL,
    final_rank SMALLINT UNSIGNED NULL,
    points DECIMAL(4,1) NOT NULL,
    rounds_data JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tournament_player (tournament_id, player_id),
    KEY idx_tournament_rank (tournament_id, final_rank),
    CONSTRAINT fk_tp_tournament FOREIGN KEY (tournament_id)
        REFERENCES jef_tournaments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_tp_player FOREIGN KEY (player_id)
        REFERENCES jef_players (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_circuit_rankings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    season_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    ranking_type VARCHAR(20) NOT NULL,
    total_points DECIMAL(6,1) NOT NULL,
    `rank` SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_season_player_type (season_id, player_id, ranking_type),
    KEY idx_season_type_rank (season_id, ranking_type, `rank`),
    CONSTRAINT fk_cr_season FOREIGN KEY (season_id)
        REFERENCES jef_seasons (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cr_player FOREIGN KEY (player_id)
        REFERENCES jef_players (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_circuit_results (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    season_id INT UNSIGNED NOT NULL,
    tournament_id INT UNSIGNED NOT NULL,
    player_id INT UNSIGNED NOT NULL,
    ranking_type VARCHAR(20) NOT NULL,
    tournament_rank SMALLINT UNSIGNED NOT NULL,
    circuit_points DECIMAL(6,1) NOT NULL,
    UNIQUE KEY uk_season_tournament_player_type (season_id, tournament_id, player_id, ranking_type),
    KEY idx_season_type (season_id, ranking_type),
    CONSTRAINT fk_cres_season FOREIGN KEY (season_id)
        REFERENCES jef_seasons (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cres_tournament FOREIGN KEY (tournament_id)
        REFERENCES jef_tournaments (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cres_player FOREIGN KEY (player_id)
        REFERENCES jef_players (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jef_settings (
    `key` VARCHAR(50) NOT NULL PRIMARY KEY,
    `value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
