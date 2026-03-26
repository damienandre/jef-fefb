-- Allow creating stages manually (without TRF import)
-- by giving round_count and player_count sensible defaults
ALTER TABLE jef_tournaments
  ALTER COLUMN round_count SET DEFAULT 0,
  ALTER COLUMN player_count SET DEFAULT 0;
