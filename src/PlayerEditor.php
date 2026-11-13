<?php

declare(strict_types=1);

namespace Jef;

use Jef\Ranking\RankingCalculator;
use Jef\Trf\TrfParser;
use PDO;

/**
 * Manual correction of a player's identity data (names, birth date, IDs) and
 * of the club recorded on each of their tournament participations.
 *
 * Note: cli/enrich-players.php overwrites jef_tournament_players.club_id with
 * the player's current FRBE club, so manual club edits are not permanent.
 */
final class PlayerEditor
{
    private const MAX_NAME_LENGTH = 100;
    private const MAX_ID = 999999999;

    /**
     * Validate and normalize raw form input.
     *
     * @param array<string, mixed> $input
     * @return array{0: array{last_name:string, first_name:string, birth_date:?string, fide_id:?int, belgian_id:?int, clubs:array<int, ?int>}, 1: list<string>}
     */
    public static function validate(array $input): array
    {
        $errors = [];

        // Same normalization as TRF import (UTF-8 NFC) so manually edited names
        // stay byte-identical to imported ones for the name + birth date match.
        $lastName = trim(TrfParser::normalizeUtf8((string) ($input['last_name'] ?? '')));
        $firstName = trim(TrfParser::normalizeUtf8((string) ($input['first_name'] ?? '')));

        if ($lastName === '' || $firstName === '') {
            $errors[] = 'Le nom et le prénom sont obligatoires.';
        }
        if (mb_strlen($lastName) > self::MAX_NAME_LENGTH || mb_strlen($firstName) > self::MAX_NAME_LENGTH) {
            $errors[] = 'Le nom et le prénom ne peuvent pas dépasser 100 caractères.';
        }

        $birthDate = trim((string) ($input['birth_date'] ?? ''));
        if ($birthDate === '') {
            $birthDate = null;
        } else {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
            if ($parsed === false || $parsed->format('Y-m-d') !== $birthDate) {
                $errors[] = 'La date de naissance n\'est pas valide.';
                $birthDate = null;
            } elseif ($parsed > new \DateTimeImmutable('today')) {
                $errors[] = 'La date de naissance ne peut pas être dans le futur.';
            }
        }

        $fideId = self::parseOptionalId($input['fide_id'] ?? '', 'FIDE', $errors);
        $belgianId = self::parseOptionalId($input['belgian_id'] ?? '', 'belge', $errors);

        $clubs = [];
        $rawClubs = $input['clubs'] ?? [];
        if (!is_array($rawClubs)) {
            $rawClubs = [];
        }
        foreach ($rawClubs as $tpId => $clubId) {
            $tpId = filter_var($tpId, FILTER_VALIDATE_INT);
            if ($tpId === false || $tpId <= 0) {
                $errors[] = 'Participation invalide.';
                continue;
            }
            $clubId = trim((string) $clubId);
            if ($clubId === '') {
                $clubs[$tpId] = null;
                continue;
            }
            $clubId = filter_var($clubId, FILTER_VALIDATE_INT);
            if ($clubId === false || $clubId <= 0) {
                $errors[] = 'Club invalide.';
                continue;
            }
            $clubs[$tpId] = $clubId;
        }

        return [[
            'last_name' => $lastName,
            'first_name' => $firstName,
            'birth_date' => $birthDate,
            'fide_id' => $fideId,
            'belgian_id' => $belgianId,
            'clubs' => $clubs,
        ], $errors];
    }

    /**
     * Apply validated data. Rankings of every season the player took part in
     * are recalculated when the birth date changes (it drives age categories).
     *
     * @param array{last_name:string, first_name:string, birth_date:?string, fide_id:?int, belgian_id:?int, clubs:array<int, ?int>} $data
     * @return list<string> errors (empty on success)
     */
    public static function update(PDO $db, int $playerId, array $data): array
    {
        $stmt = $db->prepare("SELECT birth_date FROM jef_players WHERE id = ?");
        $stmt->execute([$playerId]);
        $current = $stmt->fetch();
        if ($current === false) {
            return ['Joueur introuvable.'];
        }

        $errors = [];

        if ($data['fide_id'] !== null) {
            $stmt = $db->prepare("SELECT id FROM jef_players WHERE fide_id = ? AND id != ?");
            $stmt->execute([$data['fide_id'], $playerId]);
            if ($stmt->fetch()) {
                $errors[] = 'Cet ID FIDE est déjà attribué à un autre joueur.';
            }
        }
        if ($data['belgian_id'] !== null) {
            $stmt = $db->prepare("SELECT id FROM jef_players WHERE belgian_id = ? AND id != ?");
            $stmt->execute([$data['belgian_id'], $playerId]);
            if ($stmt->fetch()) {
                $errors[] = 'Cet ID belge est déjà attribué à un autre joueur.';
            }
        }

        if ($data['clubs'] !== []) {
            $tpStmt = $db->prepare("SELECT id FROM jef_tournament_players WHERE player_id = ?");
            $tpStmt->execute([$playerId]);
            $ownTpIds = array_map('intval', $tpStmt->fetchAll(PDO::FETCH_COLUMN));
            if (array_diff(array_keys($data['clubs']), $ownTpIds) !== []) {
                $errors[] = 'Participation invalide.';
            }

            $clubIds = array_values(array_unique(array_filter($data['clubs'], fn ($c) => $c !== null)));
            if ($clubIds !== []) {
                $placeholders = implode(',', array_fill(0, count($clubIds), '?'));
                $clubStmt = $db->prepare("SELECT COUNT(*) FROM jef_clubs WHERE idclub IN ($placeholders)");
                $clubStmt->execute($clubIds);
                if ((int) $clubStmt->fetchColumn() !== count($clubIds)) {
                    $errors[] = 'Club inconnu.';
                }
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $birthDateChanged = $current['birth_date'] !== $data['birth_date'];

        $db->beginTransaction();
        try {
            $db->prepare(
                "UPDATE jef_players
                 SET last_name = ?, first_name = ?, birth_date = ?, fide_id = ?, belgian_id = ?
                 WHERE id = ?"
            )->execute([
                $data['last_name'],
                $data['first_name'],
                $data['birth_date'],
                $data['fide_id'],
                $data['belgian_id'],
                $playerId,
            ]);

            $setClub = $db->prepare(
                "UPDATE jef_tournament_players SET club_id = ? WHERE id = ? AND player_id = ?"
            );
            foreach ($data['clubs'] as $tpId => $clubId) {
                $setClub->execute([$clubId, $tpId, $playerId]);
            }

            if ($birthDateChanged) {
                $seasonStmt = $db->prepare(
                    "SELECT DISTINCT t.season_id
                     FROM jef_tournament_players tp
                     JOIN jef_tournaments t ON t.id = tp.tournament_id
                     WHERE tp.player_id = ?"
                );
                $seasonStmt->execute([$playerId]);
                foreach ($seasonStmt->fetchAll(PDO::FETCH_COLUMN) as $seasonId) {
                    RankingCalculator::recalculate($db, (int) $seasonId);
                }
            }

            $db->commit();
        } catch (\PDOException $e) {
            $db->rollBack();
            // The duplicate checks above are not locked: a concurrent import or
            // edit can still claim the same ID before our UPDATE runs.
            $duplicate = self::duplicateKeyMessage($e);
            if ($duplicate !== null) {
                return [$duplicate];
            }
            throw $e;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [];
    }

    private static function duplicateKeyMessage(\PDOException $e): ?string
    {
        if (($e->errorInfo[1] ?? null) !== 1062) {
            return null;
        }
        $message = $e->getMessage();
        if (str_contains($message, 'uk_fide_id')) {
            return 'Cet ID FIDE est déjà attribué à un autre joueur.';
        }
        if (str_contains($message, 'uk_belgian_id')) {
            return 'Cet ID belge est déjà attribué à un autre joueur.';
        }
        return null;
    }

    /** @param list<string> $errors */
    private static function parseOptionalId(mixed $raw, string $label, array &$errors): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $id = filter_var($raw, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0 || $id > self::MAX_ID) {
            $errors[] = "L'ID {$label} doit être un nombre entier positif (max 999 999 999).";
            return null;
        }
        return $id;
    }
}
