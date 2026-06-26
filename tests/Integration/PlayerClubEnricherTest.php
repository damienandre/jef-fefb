<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use Jef\PlayerClubEnricher;
use PHPUnit\Framework\TestCase;

final class PlayerClubEnricherTest extends TestCase
{
    private static ?\PDO $db = null;

    public static function setUpBeforeClass(): void
    {
        $configFile = __DIR__ . '/../../config.php';
        if (!file_exists($configFile)) {
            self::markTestSkipped('config.php not found — integration tests skipped');
        }
        try {
            self::$db = Database::get();
        } catch (\PDOException $e) {
            self::markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('No database connection');
        }
        self::$db->exec("DELETE FROM jef_circuit_results");
        self::$db->exec("DELETE FROM jef_circuit_rankings");
        self::$db->exec("DELETE FROM jef_tournament_players");
        self::$db->exec("DELETE FROM jef_tournaments");
        self::$db->exec("DELETE FROM jef_players");
        self::$db->exec("DELETE FROM jef_seasons");
        self::$db->exec("DELETE FROM jef_clubs");
    }

    public function testSyncClubsUpserts(): void
    {
        $api = new FrbeApiStub(clubs: [
            ['idclub' => 101, 'name_long' => 'Club Long A', 'name_short' => 'A', 'enabled' => true],
            ['idclub' => 109, 'name_long' => 'Club Long B', 'name_short' => null, 'enabled' => false],
        ]);

        $count = PlayerClubEnricher::syncClubs(self::$db, $api);

        $this->assertSame(2, $count);
        $rows = self::$db->query("SELECT idclub, name_short, enabled FROM jef_clubs ORDER BY idclub")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertSame('A', $rows[0]['name_short']);
        $this->assertNull($rows[1]['name_short']);
        $this->assertSame(0, (int) $rows[1]['enabled']);

        // Re-sync with a renamed club updates in place rather than duplicating.
        $api2 = new FrbeApiStub(clubs: [
            ['idclub' => 101, 'name_long' => 'Renamed', 'name_short' => 'AA', 'enabled' => true],
        ]);
        PlayerClubEnricher::syncClubs(self::$db, $api2);
        $this->assertSame('AA', self::$db->query("SELECT name_short FROM jef_clubs WHERE idclub = 101")->fetchColumn());
    }

    public function testEnrichTournamentResolvesBelgianIdAndSetsClub(): void
    {
        $tournamentId = $this->seedTournament();
        $playerId = $this->seedPlayer(233595, 'Engelen', 'Joseph');
        $tpId = $this->seedParticipation($tournamentId, $playerId, 1985);

        $api = new FrbeApiStub(
            clubs: [['idclub' => 109, 'name_long' => 'KSK 109', 'name_short' => 'K109', 'enabled' => true]],
            fideToBel: [233595 => 3131],
            members: [3131 => ['idnumber' => 3131, 'idfide' => 233595, 'idclub' => 109, 'fiderating' => 1985, 'natrating' => 2008]],
        );
        PlayerClubEnricher::syncClubs(self::$db, $api);

        $report = PlayerClubEnricher::enrichTournament(self::$db, $api, $tournamentId);

        $this->assertSame(1, $report['belgian_ids_resolved']);
        $this->assertSame(1, $report['tp_clubs_set']);
        $this->assertSame(3131, (int) self::$db->query("SELECT belgian_id FROM jef_players WHERE id = {$playerId}")->fetchColumn());
        $this->assertSame(109, (int) self::$db->query("SELECT club_id FROM jef_tournament_players WHERE id = {$tpId}")->fetchColumn());
    }

    public function testEnsuresClubMissingFromList(): void
    {
        // Member reports a club id the clubs list did not include — the FK still
        // resolves because the enricher backfills a minimal jef_clubs row.
        $tournamentId = $this->seedTournament();
        $playerId = $this->seedPlayer(999, 'Ghost', 'Club');
        $this->seedParticipation($tournamentId, $playerId, null);

        $api = new FrbeApiStub(
            clubs: [],
            fideToBel: [999 => 4242],
            members: [4242 => ['idclub' => 777]],
        );

        PlayerClubEnricher::enrichTournament(self::$db, $api, $tournamentId);

        $this->assertSame('777', (string) self::$db->query("SELECT name_long FROM jef_clubs WHERE idclub = 777")->fetchColumn());
        $this->assertSame(777, (int) self::$db->query("SELECT club_id FROM jef_tournament_players")->fetchColumn());
    }

    public function testFallsBackToFideMemberWhenBelgianIdUnknown(): void
    {
        // fideid2belid yields nothing, but the FIDE-keyed endpoint still returns
        // the club — the enricher must use that fallback.
        $tournamentId = $this->seedTournament();
        $playerId = $this->seedPlayer(207624, 'Thora', 'Jack');
        $tpId = $this->seedParticipation($tournamentId, $playerId, null);

        $api = new FrbeApiStub(
            clubs: [['idclub' => 55, 'name_long' => 'Fallback Club', 'name_short' => 'FB', 'enabled' => true]],
            fideToBel: [],
            members: [],
            fideMembers: [207624 => ['idclub' => 55]],
        );
        PlayerClubEnricher::syncClubs(self::$db, $api);

        $report = PlayerClubEnricher::enrichTournament(self::$db, $api, $tournamentId);

        $this->assertSame(1, $report['tp_clubs_set']);
        $this->assertSame(55, (int) self::$db->query("SELECT club_id FROM jef_tournament_players WHERE id = {$tpId}")->fetchColumn());
        // No belgian id was resolvable, so it stays null.
        $this->assertNull(self::$db->query("SELECT belgian_id FROM jef_players WHERE id = {$playerId}")->fetchColumn() ?: null);
    }

    public function testFidelessPlayerIsLeftUntouched(): void
    {
        $tournamentId = $this->seedTournament();
        $playerId = $this->seedPlayer(null, 'Sans', 'Fide');
        $this->seedParticipation($tournamentId, $playerId, null);

        $api = new FrbeApiStub();
        $report = PlayerClubEnricher::enrichTournament(self::$db, $api, $tournamentId);

        $this->assertSame(0, $report['tp_clubs_set']);
        $this->assertNull(self::$db->query("SELECT belgian_id FROM jef_players WHERE id = {$playerId}")->fetchColumn() ?: null);
        $this->assertNull(self::$db->query("SELECT club_id FROM jef_tournament_players")->fetchColumn() ?: null);
    }

    public function testRunReportsAndDryRunRollsBack(): void
    {
        $tournamentId = $this->seedTournament();
        $fidePlayer = $this->seedPlayer(233595, 'Engelen', 'Joseph');
        $this->seedParticipation($tournamentId, $fidePlayer, 1985);
        $this->seedPlayer(null, 'Sans', 'Fide'); // not in any tournament, no fide

        $api = new FrbeApiStub(
            clubs: [['idclub' => 109, 'name_long' => 'KSK 109', 'name_short' => 'K109', 'enabled' => true]],
            fideToBel: [233595 => 3131],
            members: [3131 => ['idclub' => 109]],
        );

        $dry = PlayerClubEnricher::run(self::$db, $api, true);
        $this->assertSame(1, $dry['clubs_loaded']);
        $this->assertSame(1, $dry['belgian_ids_resolved']);
        $this->assertSame(1, $dry['tp_clubs_set']);
        $this->assertSame(1, $dry['players_skipped_no_fide']);
        // Dry run committed nothing.
        $this->assertSame(0, (int) self::$db->query("SELECT COUNT(*) FROM jef_clubs")->fetchColumn());
        $this->assertNull(self::$db->query("SELECT belgian_id FROM jef_players WHERE id = {$fidePlayer}")->fetchColumn() ?: null);

        $live = PlayerClubEnricher::run(self::$db, $api, false);
        $this->assertSame(1, $live['tp_clubs_set']);
        $this->assertSame(109, (int) self::$db->query("SELECT club_id FROM jef_tournament_players")->fetchColumn());
        $this->assertSame(3131, (int) self::$db->query("SELECT belgian_id FROM jef_players WHERE id = {$fidePlayer}")->fetchColumn());
    }

    private function seedTournament(): int
    {
        self::$db->exec("INSERT INTO jef_seasons (year) VALUES (2026)");
        $seasonId = (int) self::$db->lastInsertId();
        self::$db->prepare(
            "INSERT INTO jef_tournaments
             (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, 'T1', '2026-01-31', 1, 1, 1)"
        )->execute([$seasonId]);
        return (int) self::$db->lastInsertId();
    }

    private function seedPlayer(?int $fideId, string $last, string $first): int
    {
        self::$db->prepare(
            "INSERT INTO jef_players (fide_id, last_name, first_name, birth_date)
             VALUES (?, ?, ?, '2010-01-01')"
        )->execute([$fideId, $last, $first]);
        return (int) self::$db->lastInsertId();
    }

    private function seedParticipation(int $tournamentId, int $playerId, ?int $rating): int
    {
        self::$db->prepare(
            "INSERT INTO jef_tournament_players
             (tournament_id, player_id, starting_rank, final_rank, points, rating, rounds_data)
             VALUES (?, ?, 1, 1, 5.0, ?, '[]')"
        )->execute([$tournamentId, $playerId, $rating]);
        return (int) self::$db->lastInsertId();
    }
}
