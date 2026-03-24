<?php

declare(strict_types=1);

namespace Jef\Ranking;

use PDO;

final class RankingCalculator
{
    /**
     * FEFB Article 10: circuit points based on tournament score position.
     * Players with the same score share the same position and points.
     * 8th position and beyond all receive 10 points.
     */
    private const POINTS_TABLE = [150, 120, 100, 80, 60, 40, 20];
    private const POINTS_DEFAULT = 10;

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
                 ORDER BY tp.points DESC"
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

                $scoreGroup = 0;
                $prevPoints = null;
                foreach ($filteredPlayers as $tp) {
                    if ($tp['points'] !== $prevPoints) {
                        $scoreGroup++;
                        $prevPoints = $tp['points'];
                    }

                    $circuitPoints = self::POINTS_TABLE[$scoreGroup - 1] ?? self::POINTS_DEFAULT;

                    $insertResultStmt->execute([
                        $seasonId, $tournamentId, $tp['player_id'],
                        $type, $scoreGroup, $circuitPoints,
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

            $scoreGroup = 0;
            $prevTotal = null;
            foreach ($totals as $row) {
                if ($row['total'] !== $prevTotal) {
                    $scoreGroup++;
                    $prevTotal = $row['total'];
                }

                $insertRankingStmt->execute([
                    $seasonId, $row['player_id'], $type, $row['total'], $scoreGroup,
                ]);
            }
        }
    }
}
