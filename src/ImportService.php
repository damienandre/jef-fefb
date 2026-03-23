<?php

declare(strict_types=1);

namespace Jef;

use Jef\Ranking\RankingCalculator;
use Jef\Trf\TrfParser;
use PDO;

final class ImportService
{
    /**
     * Import a TRF file for a season.
     * Creates/updates season, tournament, players, and recalculates rankings.
     * All operations happen within a single transaction.
     *
     * @return array{player_count: int, tournament_name: string}
     * @throws \InvalidArgumentException on parse errors
     * @throws \RuntimeException on database errors
     */
    public static function import(PDO $db, int $seasonYear, int $sortOrder, string $trfContent): array
    {
        $parser = new TrfParser();
        $result = $parser->parse($trfContent);
        $trfTournament = $result['tournament'];
        $trfPlayers = $result['players'];

        $db->beginTransaction();

        try {
            // Get or create season
            $stmt = $db->prepare("SELECT id FROM jef_seasons WHERE year = ?");
            $stmt->execute([$seasonYear]);
            $seasonId = $stmt->fetchColumn();

            if (!$seasonId) {
                $db->prepare("INSERT INTO jef_seasons (year) VALUES (?)")->execute([$seasonYear]);
                $seasonId = (int) $db->lastInsertId();
            }

            // Check if tournament exists for this sort_order (reimport)
            $stmt = $db->prepare(
                "SELECT id FROM jef_tournaments WHERE season_id = ? AND sort_order = ?"
            );
            $stmt->execute([$seasonId, $sortOrder]);
            $existingTournamentId = $stmt->fetchColumn();

            if ($existingTournamentId) {
                // Delete existing tournament players for reimport
                $db->prepare("DELETE FROM jef_tournament_players WHERE tournament_id = ?")
                    ->execute([$existingTournamentId]);
                // Update tournament record
                $db->prepare(
                    "UPDATE jef_tournaments SET name = ?, location = ?, date_start = ?, date_end = ?,
                     round_count = ?, player_count = ?, trf_raw = ? WHERE id = ?"
                )->execute([
                    $trfTournament->name,
                    $trfTournament->city,
                    $trfTournament->dateStart,
                    $trfTournament->dateEnd,
                    $trfTournament->roundCount,
                    count($trfPlayers),
                    $trfContent,
                    $existingTournamentId,
                ]);
                $tournamentId = (int) $existingTournamentId;
            } else {
                $db->prepare(
                    "INSERT INTO jef_tournaments
                     (season_id, name, location, date_start, date_end, round_count, player_count, sort_order, trf_raw)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $seasonId,
                    $trfTournament->name,
                    $trfTournament->city,
                    $trfTournament->dateStart,
                    $trfTournament->dateEnd,
                    $trfTournament->roundCount,
                    count($trfPlayers),
                    $sortOrder,
                    $trfContent,
                ]);
                $tournamentId = (int) $db->lastInsertId();
            }

            $insertTpStmt = $db->prepare(
                "INSERT INTO jef_tournament_players
                 (tournament_id, player_id, starting_rank, final_rank, points, rounds_data)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($trfPlayers as $trfPlayer) {
                $playerId = self::findOrCreatePlayer($db, $trfPlayer);

                $roundsJson = json_encode($trfPlayer->rounds, JSON_UNESCAPED_UNICODE);

                $insertTpStmt->execute([
                    $tournamentId,
                    $playerId,
                    $trfPlayer->startingRank,
                    $trfPlayer->rank,
                    $trfPlayer->points,
                    $roundsJson,
                ]);
            }

            // Recalculate all rankings for the season
            RankingCalculator::recalculate($db, $seasonId);

            $db->commit();

            return [
                'player_count' => count($trfPlayers),
                'tournament_name' => $trfTournament->name,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private static function findOrCreatePlayer(PDO $db, \Jef\Trf\TrfPlayer $trfPlayer): int
    {
        // Try matching by FIDE ID first
        if ($trfPlayer->fideId !== null) {
            $stmt = $db->prepare("SELECT id FROM jef_players WHERE fide_id = ?");
            $stmt->execute([$trfPlayer->fideId]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }

        // Try matching by name + birth date
        if ($trfPlayer->birthDate !== null) {
            $stmt = $db->prepare(
                "SELECT id FROM jef_players WHERE last_name = ? AND first_name = ? AND birth_date = ?"
            );
            $stmt->execute([$trfPlayer->lastName, $trfPlayer->firstName, $trfPlayer->birthDate]);
            $id = $stmt->fetchColumn();
            if ($id) {
                // Update FIDE ID if we now have it
                if ($trfPlayer->fideId !== null) {
                    $db->prepare("UPDATE jef_players SET fide_id = ? WHERE id = ?")
                        ->execute([$trfPlayer->fideId, $id]);
                }
                return (int) $id;
            }
        }

        // Create new player
        $db->prepare(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date) VALUES (?, ?, ?, ?)"
        )->execute([
            $trfPlayer->fideId,
            $trfPlayer->lastName,
            $trfPlayer->firstName,
            $trfPlayer->birthDate,
        ]);

        return (int) $db->lastInsertId();
    }
}
