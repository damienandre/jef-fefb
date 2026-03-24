-- Rename tournament_rank to score_position to reflect dense score group semantics
ALTER TABLE jef_circuit_results CHANGE tournament_rank score_position SMALLINT UNSIGNED NOT NULL;
