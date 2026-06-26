<?php

declare(strict_types=1);

namespace Jef;

use Jef\Frbe\FrbeApi;
use PDO;

/**
 * Enriches players with Belgian-federation data fetched from the FRBE public API:
 *   - jef_players.belgian_id              (resolved once per player from its FIDE id)
 *   - jef_tournament_players.club_id      (the member's club, captured per participation)
 *   - jef_clubs                           (reference table of club id -> name)
 *
 * The API returns *current* values, so club is stored per tournament rather than
 * once per player. Players without a FIDE id cannot be matched and are left as-is.
 *
 * Used two ways:
 *   - ImportService calls {@see enrichTournament} best-effort after an import commits.
 *   - cli/enrich-players.php calls {@see run} to (re)enrich everything in one pass.
 */
final class PlayerClubEnricher
{
    /**
     * Full backfill: refresh the clubs table, resolve missing Belgian ids, and
     * set the club on every tournament participation. Wrapped in one transaction
     * so --dry-run can roll everything back.
     *
     * @return array{
     *     clubs_loaded:int,
     *     belgian_ids_resolved:int,
     *     tp_clubs_set:int,
     *     players_skipped_no_fide:int
     * }
     */
    public static function run(PDO $db, FrbeApi $api, bool $dryRun = false): array
    {
        $db->beginTransaction();
        try {
            $clubsLoaded = self::syncClubs($db, $api);
            $knownClubs = self::loadKnownClubIds($db);
            $belCache = [];

            // Resolve belgian_id for every FIDE player still missing one (covers
            // players not present in any tournament too).
            $belResolved = 0;
            $needBel = $db->query(
                "SELECT id, fide_id FROM jef_players
                 WHERE fide_id IS NOT NULL AND belgian_id IS NULL"
            )->fetchAll();
            foreach ($needBel as $p) {
                $belId = self::resolveBelgianId($db, $api, (int) $p['id'], (int) $p['fide_id'], $belCache);
                if ($belId !== null) {
                    $belResolved++;
                }
            }

            // Set club per tournament participation. memberCache is shared across
            // all tournaments so a player appearing in N events is fetched once.
            $tpClubsSet = 0;
            $memberCache = [];
            $tournamentIds = $db->query("SELECT id FROM jef_tournaments")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tournamentIds as $tid) {
                $r = self::enrichRows($db, $api, (int) $tid, $belCache, $knownClubs, $memberCache);
                $belResolved += $r['belgian_ids_resolved'];
                $tpClubsSet += $r['tp_clubs_set'];
            }

            $skippedNoFide = (int) $db->query(
                "SELECT COUNT(*) FROM jef_players WHERE fide_id IS NULL"
            )->fetchColumn();

            if ($dryRun) {
                $db->rollBack();
            } else {
                $db->commit();
            }

            return [
                'clubs_loaded' => $clubsLoaded,
                'belgian_ids_resolved' => $belResolved,
                'tp_clubs_set' => $tpClubsSet,
                'players_skipped_no_fide' => $skippedNoFide,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Best-effort enrichment of a single tournament's participants. Does not
     * manage a transaction (callers run it outside the import transaction).
     *
     * @return array{belgian_ids_resolved:int, tp_clubs_set:int}
     */
    public static function enrichTournament(PDO $db, FrbeApi $api, int $tournamentId): array
    {
        $belCache = [];
        $memberCache = [];
        $knownClubs = self::loadKnownClubIds($db);

        // On a fresh database jef_clubs is empty; populate it once so club names
        // (not just placeholder ids) are available for display after an import.
        if ($knownClubs === []) {
            self::syncClubs($db, $api);
            $knownClubs = self::loadKnownClubIds($db);
        }

        return self::enrichRows($db, $api, $tournamentId, $belCache, $knownClubs, $memberCache);
    }

    /**
     * Upsert the full clubs list into jef_clubs. Returns the number of clubs seen.
     */
    public static function syncClubs(PDO $db, FrbeApi $api): int
    {
        $clubs = $api->listClubs();
        if ($clubs === []) {
            return 0;
        }

        $stmt = $db->prepare(
            "INSERT INTO jef_clubs (idclub, name_long, name_short, enabled)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name_long = VALUES(name_long),
                name_short = VALUES(name_short),
                enabled = VALUES(enabled)"
        );

        foreach ($clubs as $club) {
            $stmt->execute([
                $club['idclub'],
                $club['name_long'],
                $club['name_short'],
                $club['enabled'] ? 1 : 0,
            ]);
        }

        return count($clubs);
    }

    /**
     * Resolve and persist a player's Belgian member id from its FIDE id.
     *
     * @param array<int, ?int> $belCache keyed by fide_id
     */
    public static function resolveBelgianId(
        PDO $db,
        FrbeApi $api,
        int $playerId,
        int $fideId,
        array &$belCache
    ): ?int {
        if (!array_key_exists($fideId, $belCache)) {
            $belCache[$fideId] = $api->fideIdToBelgianId($fideId);
        }
        $belId = $belCache[$fideId];

        if ($belId !== null) {
            try {
                $db->prepare("UPDATE jef_players SET belgian_id = ? WHERE id = ? AND belgian_id IS NULL")
                    ->execute([$belId, $playerId]);
            } catch (\PDOException) {
                // uk_belgian_id collision: this id already belongs to another
                // (likely duplicate) player row. Skip persisting rather than
                // aborting the whole backfill — the failed statement rolls back
                // on its own, leaving the surrounding transaction usable.
            }
        }

        return $belId;
    }

    /**
     * Resolve belgian ids (as needed) and set club_id for one tournament's rows.
     *
     * @param array<int, ?int>   $belCache    keyed by fide_id, shared across tournaments
     * @param array<int, true>   $knownClubs  set of club ids already present in jef_clubs
     * @param array<int, ?array> $memberCache keyed by belgian_id, shared across tournaments
     * @return array{belgian_ids_resolved:int, tp_clubs_set:int}
     */
    private static function enrichRows(
        PDO $db,
        FrbeApi $api,
        int $tournamentId,
        array &$belCache,
        array &$knownClubs,
        array &$memberCache
    ): array {
        $rowsStmt = $db->prepare(
            "SELECT tp.id AS tp_id, p.id AS player_id, p.fide_id, p.belgian_id
             FROM jef_tournament_players tp
             JOIN jef_players p ON p.id = tp.player_id
             WHERE tp.tournament_id = ?"
        );
        $rowsStmt->execute([$tournamentId]);
        $rows = $rowsStmt->fetchAll();

        $setClubStmt = $db->prepare("UPDATE jef_tournament_players SET club_id = ? WHERE id = ?");

        $belResolved = 0;
        $clubsSet = 0;

        foreach ($rows as $row) {
            if ($row['fide_id'] === null) {
                continue;
            }
            $fideId = (int) $row['fide_id'];

            $belId = $row['belgian_id'] !== null ? (int) $row['belgian_id'] : null;
            if ($belId === null) {
                $belId = self::resolveBelgianId($db, $api, (int) $row['player_id'], $fideId, $belCache);
                if ($belId !== null) {
                    $belResolved++;
                }
            }

            // Prefer the member record keyed by Belgian id; fall back to the
            // FIDE-keyed endpoint when the Belgian id is unknown or 404s.
            $member = null;
            if ($belId !== null) {
                if (!array_key_exists($belId, $memberCache)) {
                    $memberCache[$belId] = $api->getMember($belId);
                }
                $member = $memberCache[$belId];
            }
            if (!is_array($member)) {
                $member = $api->getFideMember($fideId);
            }

            $idclub = is_array($member) ? (int) ($member['idclub'] ?? 0) : 0;
            if ($idclub <= 0) {
                continue;
            }

            self::ensureClub($db, $idclub, $knownClubs);
            $setClubStmt->execute([$idclub, (int) $row['tp_id']]);
            $clubsSet++;
        }

        return ['belgian_ids_resolved' => $belResolved, 'tp_clubs_set' => $clubsSet];
    }

    /**
     * Guarantee a club row exists before it is referenced by the FK. The clubs
     * list usually already covers it; this backstops a stale/incomplete list.
     *
     * @param array<int, true> $knownClubs
     */
    private static function ensureClub(PDO $db, int $idclub, array &$knownClubs): void
    {
        if (isset($knownClubs[$idclub])) {
            return;
        }
        $db->prepare(
            "INSERT INTO jef_clubs (idclub, name_long, name_short, enabled)
             VALUES (?, ?, NULL, 1)
             ON DUPLICATE KEY UPDATE idclub = idclub"
        )->execute([$idclub, (string) $idclub]);
        $knownClubs[$idclub] = true;
    }

    /**
     * @return array<int, true> set of club ids present in jef_clubs
     */
    private static function loadKnownClubIds(PDO $db): array
    {
        $ids = $db->query("SELECT idclub FROM jef_clubs")->fetchAll(PDO::FETCH_COLUMN);
        $set = [];
        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }
}
