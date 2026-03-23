<?php

declare(strict_types=1);

namespace Jef\Ranking;

use PDO;

final class RankingCalculator
{
    /**
     * Recalculate all circuit rankings for a season.
     * Deletes existing results/rankings and recomputes from tournament data.
     * Must be called within an active transaction.
     *
     * Circuit points are assigned based on tournament rank:
     * 1st = 25, 2nd = 20, 3rd = 16, 4th = 13, 5th = 11,
     * 6th = 9, 7th = 7, 8th = 5, 9th = 3, 10th+ = 1
     *
     * TODO: Make scoring rules configurable per FEFB requirements.
     */
    private const POINTS_TABLE = [25, 20, 16, 13, 11, 9, 7, 5, 3, 1];

    public static function recalculate(PDO $db, int $seasonId): void
    {
        // Clear existing circuit results and rankings for this season
        $db->prepare("DELETE FROM jef_circuit_results WHERE season_id = ?")->execute([$seasonId]);
        $db->prepare("DELETE FROM jef_circuit_rankings WHERE season_id = ?")->execute([$seasonId]);

        // Get all tournaments for the season
        $tourStmt = $db->prepare(
            "SELECT id FROM jef_tournaments WHERE season_id = ? ORDER BY sort_order"
        );
        $tourStmt->execute([$seasonId]);
        $tournaments = $tourStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tournaments)) {
            return;
        }

        // Get season year for age category computation
        $yearStmt = $db->prepare("SELECT year FROM jef_seasons WHERE id = ?");
        $yearStmt->execute([$seasonId]);
        $seasonYear = (int) $yearStmt->fetchColumn();

        // Calculate circuit results for each ranking type
        $rankingTypes = array_merge(['general'], AgeCategory::all());

        foreach ($tournaments as $tournamentId) {
            // Get tournament players with their birth dates
            $tpStmt = $db->prepare(
                "SELECT tp.player_id, tp.final_rank, tp.points, p.birth_date
                 FROM jef_tournament_players tp
                 JOIN jef_players p ON p.id = tp.player_id
                 WHERE tp.tournament_id = ?
                 ORDER BY tp.final_rank ASC"
            );
            $tpStmt->execute([$tournamentId]);
            $tournamentPlayers = $tpStmt->fetchAll();

            foreach ($rankingTypes as $type) {
                // Filter players for this ranking type
                $filteredPlayers = [];
                foreach ($tournamentPlayers as $tp) {
                    if ($type === 'general') {
                        $filteredPlayers[] = $tp;
                    } else {
                        $category = AgeCategory::determine(
                            new \DateTimeImmutable($tp['birth_date']),
                            $seasonYear
                        );
                        if ($category === $type) {
                            $filteredPlayers[] = $tp;
                        }
                    }
                }

                if (empty($filteredPlayers)) {
                    continue;
                }

                // Assign ranks within this type and compute circuit points
                $rank = 1;
                foreach ($filteredPlayers as $i => $tp) {
                    // Handle ex-aequo: same points = same rank
                    if ($i > 0 && (float) $tp['points'] === (float) $filteredPlayers[$i - 1]['points']) {
                        // Same rank as previous
                    } else {
                        $rank = $i + 1;
                    }

                    $circuitPoints = self::POINTS_TABLE[$rank - 1] ?? self::POINTS_TABLE[array_key_last(self::POINTS_TABLE)];

                    $insertStmt = $db->prepare(
                        "INSERT INTO jef_circuit_results
                         (season_id, tournament_id, player_id, ranking_type, tournament_rank, circuit_points)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $insertStmt->execute([
                        $seasonId, $tournamentId, $tp['player_id'],
                        $type, $rank, $circuitPoints,
                    ]);
                }
            }
        }

        // Calculate overall circuit rankings from circuit results
        foreach ($rankingTypes as $type) {
            $sumStmt = $db->prepare(
                "SELECT player_id, SUM(circuit_points) as total
                 FROM jef_circuit_results
                 WHERE season_id = ? AND ranking_type = ?
                 GROUP BY player_id
                 ORDER BY total DESC"
            );
            $sumStmt->execute([$seasonId, $type]);
            $totals = $sumStmt->fetchAll();

            if (empty($totals)) {
                continue;
            }

            $rank = 1;
            foreach ($totals as $i => $row) {
                // Handle ex-aequo
                if ($i > 0 && (float) $row['total'] === (float) $totals[$i - 1]['total']) {
                    // Same rank as previous
                } else {
                    $rank = $i + 1;
                }

                $crStmt = $db->prepare(
                    "INSERT INTO jef_circuit_rankings
                     (season_id, player_id, ranking_type, total_points, `rank`)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $crStmt->execute([
                    $seasonId, $row['player_id'], $type, $row['total'], $rank,
                ]);
            }
        }
    }
}
