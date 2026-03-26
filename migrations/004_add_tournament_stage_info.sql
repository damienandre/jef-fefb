ALTER TABLE jef_tournaments
    ADD COLUMN organizer VARCHAR(200) NULL AFTER location,
    ADD COLUMN address VARCHAR(500) NULL AFTER organizer,
    ADD COLUMN info_url VARCHAR(500) NULL AFTER address,
    ADD COLUMN registration_url VARCHAR(500) NULL AFTER info_url;
