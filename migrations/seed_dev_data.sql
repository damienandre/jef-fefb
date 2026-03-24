-- Development seed data for JEF Circuit Rankings
-- Run manually: mysql -u root jef < migrations/seed_dev_data.sql
-- This file is NOT a migration — it is not tracked by jef_schema_migrations

INSERT INTO jef_seasons (year, status) VALUES (2026, 'active');

SET @season_id = LAST_INSERT_ID();

-- Players (10 youth chess players)
INSERT INTO jef_players (fide_id, last_name, first_name, birth_date) VALUES
    (NULL,    'Dupont',    'Lucas',    '2014-03-15'),
    (NULL,    'Martin',    'Emma',     '2015-07-22'),
    (NULL,    'Leroy',     'Thomas',   '2013-11-08'),
    (NULL,    'Dumont',    'Clara',    '2016-01-30'),
    (NULL,    'Lambert',   'Nathan',   '2012-09-12'),
    (NULL,    'Peeters',   'Julie',    '2014-05-25'),
    (NULL,    'Janssens',  'Maxime',   '2015-12-03'),
    (NULL,    'Claes',     'Marie',    '2013-02-18'),
    (NULL,    'Wouters',   'Arthur',   '2016-08-07'),
    (NULL,    'Vermeersch','Lina',     '2012-04-14');

-- Tournament 1
INSERT INTO jef_tournaments (season_id, name, location, date_start, date_end, round_count, player_count, sort_order)
VALUES (@season_id, 'Tournoi de Bruxelles', 'Bruxelles', '2026-01-18', '2026-01-18', 5, 10, 1);

SET @t1_id = LAST_INSERT_ID();

-- Tournament 2
INSERT INTO jef_tournaments (season_id, name, location, date_start, date_end, round_count, player_count, sort_order)
VALUES (@season_id, 'Tournoi de Namur', 'Namur', '2026-02-15', '2026-02-15', 5, 10, 2);

SET @t2_id = LAST_INSERT_ID();

-- Tournament players for Tournament 1 (simplified rounds_data)
INSERT INTO jef_tournament_players (tournament_id, player_id, starting_rank, final_rank, points, rounds_data) VALUES
    (@t1_id, 1, 1, 2, 4.0, '[]'),
    (@t1_id, 2, 2, 5, 3.0, '[]'),
    (@t1_id, 3, 3, 1, 4.5, '[]'),
    (@t1_id, 4, 4, 7, 2.5, '[]'),
    (@t1_id, 5, 5, 3, 3.5, '[]'),
    (@t1_id, 6, 6, 4, 3.5, '[]'),
    (@t1_id, 7, 7, 6, 3.0, '[]'),
    (@t1_id, 8, 8, 8, 2.0, '[]'),
    (@t1_id, 9, 9, 9, 1.5, '[]'),
    (@t1_id, 10, 10, 10, 1.0, '[]');

-- Tournament players for Tournament 2
INSERT INTO jef_tournament_players (tournament_id, player_id, starting_rank, final_rank, points, rounds_data) VALUES
    (@t2_id, 1, 1, 1, 4.5, '[]'),
    (@t2_id, 2, 2, 3, 3.5, '[]'),
    (@t2_id, 3, 3, 4, 3.0, '[]'),
    (@t2_id, 4, 4, 6, 2.5, '[]'),
    (@t2_id, 5, 5, 2, 4.0, '[]'),
    (@t2_id, 6, 6, 5, 3.0, '[]'),
    (@t2_id, 7, 7, 8, 2.0, '[]'),
    (@t2_id, 8, 8, 7, 2.5, '[]'),
    (@t2_id, 9, 9, 10, 1.0, '[]'),
    (@t2_id, 10, 10, 9, 1.5, '[]');

-- Circuit results (general) — FEFB Article 10 points by distinct score group
-- T1 scores: P3=4.5, P1=4.0, P5=3.5, P6=3.5, P2=3.0, P7=3.0, P4=2.5, P8=2.0, P9=1.5, P10=1.0
INSERT INTO jef_circuit_results (season_id, tournament_id, player_id, ranking_type, score_position, circuit_points) VALUES
    (@season_id, @t1_id, 3, 'general', 1, 150.0),
    (@season_id, @t1_id, 1, 'general', 2, 120.0),
    (@season_id, @t1_id, 5, 'general', 3, 100.0),
    (@season_id, @t1_id, 6, 'general', 3, 100.0),
    (@season_id, @t1_id, 2, 'general', 4, 80.0),
    (@season_id, @t1_id, 7, 'general', 4, 80.0),
    (@season_id, @t1_id, 4, 'general', 5, 60.0),
    (@season_id, @t1_id, 8, 'general', 6, 40.0),
    (@season_id, @t1_id, 9, 'general', 7, 20.0),
    (@season_id, @t1_id, 10, 'general', 8, 10.0),
-- T2 scores: P1=4.5, P5=4.0, P2=3.5, P3=3.0, P6=3.0, P4=2.5, P8=2.5, P7=2.0, P10=1.5, P9=1.0
    (@season_id, @t2_id, 1, 'general', 1, 150.0),
    (@season_id, @t2_id, 5, 'general', 2, 120.0),
    (@season_id, @t2_id, 2, 'general', 3, 100.0),
    (@season_id, @t2_id, 3, 'general', 4, 80.0),
    (@season_id, @t2_id, 6, 'general', 4, 80.0),
    (@season_id, @t2_id, 4, 'general', 5, 60.0),
    (@season_id, @t2_id, 8, 'general', 5, 60.0),
    (@season_id, @t2_id, 7, 'general', 6, 40.0),
    (@season_id, @t2_id, 10, 'general', 7, 20.0),
    (@season_id, @t2_id, 9, 'general', 8, 10.0);

-- Circuit rankings (general) — sum of circuit_points across tournaments
INSERT INTO jef_circuit_rankings (season_id, player_id, ranking_type, total_points, `rank`) VALUES
    (@season_id, 1, 'general', 270.0, 1),
    (@season_id, 3, 'general', 230.0, 2),
    (@season_id, 5, 'general', 220.0, 3),
    (@season_id, 2, 'general', 180.0, 4),
    (@season_id, 6, 'general', 180.0, 4),
    (@season_id, 4, 'general', 120.0, 6),
    (@season_id, 7, 'general', 120.0, 6),
    (@season_id, 8, 'general', 100.0, 8),
    (@season_id, 10, 'general', 30.0, 9),
    (@season_id, 9, 'general', 30.0, 9);
