<?php

declare(strict_types=1);

namespace Jef\Ranking;

use PDO;

final class RankingCalculator
{
    /**
     * Circuit points assigned based on tournament rank:
     * 1st = 25, 2nd = 20, 3rd = 16, 4th = 13, 5th = 11,
     * 6th = 9, 7th = 7, 8th = 5, 9th = 3, 10th+ = 1
     *
     * TODO: Make scoring rules configurable per FEFB requirements.
     */
    private const POINTS_TABLE = [25, 20, 16, 13, 11, 9, 7, 5, 3, 1];

    public static function recalculate(PDO $db, int $seasonId): void
    {
        $wasInTransaction = $db->inTransaction();
        if (!$wasInTransaction) {
            $db->beginTransaction();
        }

        try {
            self::doRecalculate($db, $seasonId);
            if (!$wasInTransaction) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if (!$wasInTransaction) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private static function doRecalculate(PDO $db, int $seasonId): void
    {
        $db->prepare("DELETE FROM jef_circuit_results WHERE season_id = ?")->execute([$seasonId]);
        $db->prepare("DELETE FROM jef_circuit_rankings WHERE season_id = ?")->execute([$seasonId]);

        $tourStmt = $db->prepare(
            "SELECT id FROM jef_tournaments WHERE season_id = ? ORDER BY sort_order"
        );
        $tourStmt->execute([$seasonId]);
        $tournaments = $tourStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tournaments)) {
            return;
        }

        $yearStmt = $db->prepare("SELECT year FROM jef_seasons WHERE id = ?");
        $yearStmt->execute([$seasonId]);
        $seasonYear = (int) $yearStmt->fetchColumn();

        $rankingTypes = array_merge(['general'], AgeCategory::all());

        $insertResultStmt = $db->prepare(
            "INSERT INTO jef_circuit_results
             (season_id, tournament_id, player_id, ranking_type, tournament_rank, circuit_points)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        foreach ($tournaments as $tournamentId) {
            $tpStmt = $db->prepare(
                "SELECT tp.player_id, tp.final_rank, tp.points, p.birth_date
                 FROM jef_tournament_players tp
                 JOIN jef_players p ON p.id = tp.player_id
                 WHERE tp.tournament_id = ?
                 ORDER BY tp.final_rank ASC"
            );
            $tpStmt->execute([$tournamentId]);
            $tournamentPlayers = $tpStmt->fetchAll();

            $playerCategories = [];
            foreach ($tournamentPlayers as $tp) {
                $playerCategories[$tp['player_id']] = $tp['birth_date'] !== null
                    ? AgeCategory::determine(new \DateTimeImmutable($tp['birth_date']), $seasonYear)
                    : null;
            }

            foreach ($rankingTypes as $type) {
                $filteredPlayers = [];
                foreach ($tournamentPlayers as $tp) {
                    if ($type === 'general' || $playerCategories[$tp['player_id']] === $type) {
                        $filteredPlayers[] = $tp;
                    }
                }

                if (empty($filteredPlayers)) {
                    continue;
                }

                $rank = 1;
                foreach ($filteredPlayers as $i => $tp) {
                    if ($i > 0 && $tp['points'] === $filteredPlayers[$i - 1]['points']) {
                        // Ex-aequo: keep same rank
                    } else {
                        $rank = $i + 1;
                    }

                    $circuitPoints = self::POINTS_TABLE[$rank - 1] ?? self::POINTS_TABLE[array_key_last(self::POINTS_TABLE)];

                    $insertResultStmt->execute([
                        $seasonId, $tournamentId, $tp['player_id'],
                        $type, $rank, $circuitPoints,
                    ]);
                }
            }
        }

        $insertRankingStmt = $db->prepare(
            "INSERT INTO jef_circuit_rankings
             (season_id, player_id, ranking_type, total_points, `rank`)
             VALUES (?, ?, ?, ?, ?)"
        );

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
                if ($i > 0 && $row['total'] === $totals[$i - 1]['total']) {
                    // Ex-aequo: keep same rank
                } else {
                    $rank = $i + 1;
                }

                $insertRankingStmt->execute([
                    $seasonId, $row['player_id'], $type, $row['total'], $rank,
                ]);
            }
        }
    }
}
