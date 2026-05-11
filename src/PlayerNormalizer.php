<?php

declare(strict_types=1);

namespace Jef;

use Jef\Ranking\RankingCalculator;
use Jef\Trf\TrfParser;
use PDO;

/**
 * Backfill helper that normalizes existing jef_players names to UTF-8 NFC
 * and merges duplicate rows that PR #16 prevents at import time but does not
 * retroactively clean up.
 *
 * The merge picks a canonical row per (last_name, first_name, birth_date)
 * cluster, repoints jef_tournament_players from duplicates onto the canonical,
 * deletes the duplicates, and recalculates rankings for every affected season.
 */
final class PlayerNormalizer
{
    /**
     * @return array{
     *     renamed: array<int, array{id:int, before_last:string, before_first:string, after_last:string, after_first:string}>,
     *     merged: array<int, array{canonical_id:int, duplicate_ids:int[], tournaments_moved:int}>,
     *     skipped: array<int, array{last_name:string, first_name:string, birth_date:?string, ids:int[], reason:string}>,
     *     seasons_recalculated: int[]
     * }
     */
    public static function run(PDO $db, bool $dryRun = false): array
    {
        $report = [
            'renamed' => [],
            'merged' => [],
            'skipped' => [],
            'seasons_recalculated' => [],
        ];

        $db->beginTransaction();
        try {
            $report['renamed'] = self::normalizeNames($db);
            [$merged, $skipped, $seasonIds] = self::mergeClusters($db);
            $report['merged'] = $merged;
            $report['skipped'] = $skipped;

            sort($seasonIds);
            foreach ($seasonIds as $sid) {
                RankingCalculator::recalculate($db, $sid);
            }
            $report['seasons_recalculated'] = $seasonIds;

            if ($dryRun) {
                $db->rollBack();
            } else {
                $db->commit();
            }
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $report;
    }

    /**
     * @return array<int, array{id:int, before_last:string, before_first:string, after_last:string, after_first:string}>
     */
    private static function normalizeNames(PDO $db): array
    {
        $rows = $db->query("SELECT id, last_name, first_name FROM jef_players")->fetchAll();
        $update = $db->prepare("UPDATE jef_players SET last_name = ?, first_name = ? WHERE id = ?");
        $renamed = [];

        foreach ($rows as $row) {
            $newLast = trim(TrfParser::normalizeUtf8($row['last_name']));
            $newFirst = trim(TrfParser::normalizeUtf8($row['first_name']));
            if ($newLast === $row['last_name'] && $newFirst === $row['first_name']) {
                continue;
            }
            $update->execute([$newLast, $newFirst, (int) $row['id']]);
            $renamed[] = [
                'id' => (int) $row['id'],
                'before_last' => $row['last_name'],
                'before_first' => $row['first_name'],
                'after_last' => $newLast,
                'after_first' => $newFirst,
            ];
        }

        return $renamed;
    }

    /**
     * @return array{0: array, 1: array, 2: int[]}
     */
    private static function mergeClusters(PDO $db): array
    {
        $allPlayers = $db->query(
            "SELECT id, fide_id, last_name, first_name, birth_date
             FROM jef_players
             ORDER BY last_name, first_name, birth_date, id"
        )->fetchAll();

        $clusters = [];
        foreach ($allPlayers as $p) {
            $key = $p['last_name'] . "\0" . $p['first_name'] . "\0" . ($p['birth_date'] ?? '');
            $clusters[$key][] = $p;
        }

        $merged = [];
        $skipped = [];
        $touchedSeasons = [];

        foreach ($clusters as $rows) {
            if (count($rows) < 2) {
                continue;
            }

            // UNIQUE(fide_id) means at most one row per cluster has any given
            // FIDE id; multiple rows with non-null FIDE → distinct values →
            // plausibly different people, needs human review.
            $withFide = array_values(array_filter($rows, fn($r) => $r['fide_id'] !== null));
            if (count($withFide) > 1) {
                $skipped[] = [
                    'last_name' => $rows[0]['last_name'],
                    'first_name' => $rows[0]['first_name'],
                    'birth_date' => $rows[0]['birth_date'],
                    'ids' => array_map(fn($r) => (int) $r['id'], $rows),
                    'reason' => 'multiple distinct FIDE ids: '
                        . implode(', ', array_map(fn($r) => (int) $r['fide_id'], $withFide)),
                ];
                continue;
            }

            $canonical = $withFide[0] ?? $rows[0];
            $canonicalId = (int) $canonical['id'];
            $duplicateIds = array_values(array_filter(
                array_map(fn($r) => (int) $r['id'], $rows),
                fn($id) => $id !== $canonicalId
            ));

            $tournamentsMoved = 0;
            foreach ($duplicateIds as $dupId) {
                // Collect every season this duplicate touches before we mutate.
                $seasonsStmt = $db->prepare(
                    "SELECT DISTINCT t.season_id
                     FROM jef_tournament_players tp
                     JOIN jef_tournaments t ON t.id = tp.tournament_id
                     WHERE tp.player_id = ?"
                );
                $seasonsStmt->execute([$dupId]);
                foreach ($seasonsStmt->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                    $touchedSeasons[(int) $sid] = true;
                }

                // Drop dup's TP rows for tournaments the canonical also played
                // (uk_tournament_player would otherwise block the repoint).
                $overlapStmt = $db->prepare(
                    "SELECT dup.tournament_id
                     FROM jef_tournament_players dup
                     JOIN jef_tournament_players canon
                       ON canon.tournament_id = dup.tournament_id AND canon.player_id = ?
                     WHERE dup.player_id = ?"
                );
                $overlapStmt->execute([$canonicalId, $dupId]);
                $overlapping = array_map('intval', $overlapStmt->fetchAll(PDO::FETCH_COLUMN));

                if (!empty($overlapping)) {
                    $placeholders = implode(',', array_fill(0, count($overlapping), '?'));
                    $del = $db->prepare(
                        "DELETE FROM jef_tournament_players
                         WHERE player_id = ? AND tournament_id IN ({$placeholders})"
                    );
                    $del->execute(array_merge([$dupId], $overlapping));
                }

                $move = $db->prepare(
                    "UPDATE jef_tournament_players SET player_id = ? WHERE player_id = ?"
                );
                $move->execute([$canonicalId, $dupId]);
                $tournamentsMoved += $move->rowCount();

                // FK on jef_circuit_{results,rankings}.player_id is RESTRICT, so
                // clear them before deleting the player. They'll be rebuilt by
                // RankingCalculator::recalculate for the affected seasons.
                $db->prepare("DELETE FROM jef_circuit_results WHERE player_id = ?")->execute([$dupId]);
                $db->prepare("DELETE FROM jef_circuit_rankings WHERE player_id = ?")->execute([$dupId]);
                $db->prepare("DELETE FROM jef_players WHERE id = ?")->execute([$dupId]);
            }

            $merged[] = [
                'canonical_id' => $canonicalId,
                'duplicate_ids' => $duplicateIds,
                'tournaments_moved' => $tournamentsMoved,
            ];
        }

        return [$merged, $skipped, array_keys($touchedSeasons)];
    }
}
