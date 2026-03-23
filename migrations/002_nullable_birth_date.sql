-- Allow NULL birth_date for players without a known date of birth in TRF files
ALTER TABLE jef_players MODIFY birth_date DATE NULL;
