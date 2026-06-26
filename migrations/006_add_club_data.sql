-- Club & rating enrichment sourced from the FRBE/KBSB public API.
-- jef_clubs must exist before the FK on jef_tournament_players.

-- Reference table of FRBE clubs (loaded from the public clubs API)
CREATE TABLE jef_clubs (
    idclub INT UNSIGNED NOT NULL PRIMARY KEY,
    name_long VARCHAR(200) NOT NULL,
    name_short VARCHAR(100) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Belgian federation member id, once per player (stable identifier)
ALTER TABLE jef_players
    ADD COLUMN belgian_id INT UNSIGNED NULL AFTER fide_id,
    ADD UNIQUE KEY uk_belgian_id (belgian_id);

-- Club (from API) + FIDE rating (from the TRF), per tournament participation
ALTER TABLE jef_tournament_players
    ADD COLUMN club_id INT UNSIGNED NULL AFTER player_id,
    ADD COLUMN rating SMALLINT UNSIGNED NULL AFTER club_id,
    ADD KEY idx_tp_club (club_id),
    ADD CONSTRAINT fk_tp_club FOREIGN KEY (club_id)
        REFERENCES jef_clubs (idclub) ON DELETE SET NULL ON UPDATE CASCADE;
