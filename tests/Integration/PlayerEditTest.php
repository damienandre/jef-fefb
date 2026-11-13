<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use Jef\PlayerEditor;
use Jef\Ranking\RankingCalculator;
use PHPUnit\Framework\TestCase;

final class PlayerEditTest extends TestCase
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

    private function seedPlayer(
        string $last = 'Dupont',
        string $first = 'Marie',
        ?string $birthDate = '2017-01-01',
        ?int $fideId = null,
        ?int $belgianId = null
    ): int {
        self::$db->prepare(
            "INSERT INTO jef_players (last_name, first_name, birth_date, fide_id, belgian_id) VALUES (?, ?, ?, ?, ?)"
        )->execute([$last, $first, $birthDate, $fideId, $belgianId]);
        return (int) self::$db->lastInsertId();
    }

    private function seedTournament(int $year = 2026): int
    {
        self::$db->prepare("INSERT INTO jef_seasons (year) VALUES (?)")->execute([$year]);
        $seasonId = (int) self::$db->lastInsertId();
        self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, 'T1', ?, 1, 1, 1)"
        )->execute([$seasonId, "{$year}-01-31"]);
        return (int) self::$db->lastInsertId();
    }

    private function seasonOf(int $tournamentId): int
    {
        return (int) self::$db->query("SELECT season_id FROM jef_tournaments WHERE id = {$tournamentId}")->fetchColumn();
    }

    /** @return list<string> sorted ranking types of a player in a season */
    private function rankingTypes(int $playerId, int $seasonId): array
    {
        $rows = self::$db->query(
            "SELECT ranking_type FROM jef_circuit_rankings WHERE player_id = {$playerId} AND season_id = {$seasonId}"
        )->fetchAll(\PDO::FETCH_COLUMN);
        sort($rows);
        return $rows;
    }

    private function seedParticipation(int $tournamentId, int $playerId): int
    {
        self::$db->prepare(
            "INSERT INTO jef_tournament_players (tournament_id, player_id, starting_rank, final_rank, points, rounds_data)
             VALUES (?, ?, 1, 1, 5.0, '[]')"
        )->execute([$tournamentId, $playerId]);
        return (int) self::$db->lastInsertId();
    }

    private function seedClub(int $idclub, string $name): void
    {
        self::$db->prepare("INSERT INTO jef_clubs (idclub, name_long) VALUES (?, ?)")->execute([$idclub, $name]);
    }

    /** @param array<string, mixed> $input */
    private function edit(int $playerId, array $input): array
    {
        [$data, $errors] = PlayerEditor::validate($input + [
            'last_name' => 'Dupont',
            'first_name' => 'Marie',
            'birth_date' => '2017-01-01',
            'fide_id' => '',
            'belgian_id' => '',
        ]);
        return $errors !== [] ? $errors : PlayerEditor::update(self::$db, $playerId, $data);
    }

    private function fetchPlayer(int $id): array
    {
        $stmt = self::$db->prepare("SELECT * FROM jef_players WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function testUpdatesIdentityFields(): void
    {
        $id = $this->seedPlayer();

        $errors = $this->edit($id, [
            'last_name' => '  Martin ',
            'first_name' => 'Jeanne',
            'birth_date' => '2015-06-30',
            'fide_id' => '12345678',
            'belgian_id' => '54321',
        ]);

        $this->assertSame([], $errors);
        $player = $this->fetchPlayer($id);
        $this->assertSame('Martin', $player['last_name']);
        $this->assertSame('Jeanne', $player['first_name']);
        $this->assertSame('2015-06-30', $player['birth_date']);
        $this->assertSame(12345678, (int) $player['fide_id']);
        $this->assertSame(54321, (int) $player['belgian_id']);
    }

    public function testNamesAreNormalizedToNfc(): void
    {
        $id = $this->seedPlayer();

        // "Hélène" typed with decomposed accents (e + U+0301)
        $this->assertSame([], $this->edit($id, ['first_name' => "He\u{0301}le\u{0300}ne"]));

        $this->assertSame("H\u{00E9}l\u{00E8}ne", $this->fetchPlayer($id)['first_name']);
    }

    public function testEmptyOptionalFieldsAreCleared(): void
    {
        $id = $this->seedPlayer(fideId: 111, belgianId: 222);

        $this->assertSame([], $this->edit($id, ['birth_date' => '']));

        $player = $this->fetchPlayer($id);
        $this->assertNull($player['birth_date']);
        $this->assertNull($player['fide_id']);
        $this->assertNull($player['belgian_id']);
    }

    public function testValidationErrors(): void
    {
        [, $errors] = PlayerEditor::validate([
            'last_name' => '',
            'first_name' => 'Marie',
            'birth_date' => '2017-02-30',
            'fide_id' => 'abc',
            'belgian_id' => '-5',
        ]);

        $this->assertCount(4, $errors);
    }

    public function testFutureBirthDateRejected(): void
    {
        [, $errors] = PlayerEditor::validate([
            'last_name' => 'Dupont',
            'first_name' => 'Marie',
            'birth_date' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
        ]);

        $this->assertCount(1, $errors);
    }

    public function testDuplicateIdsRejected(): void
    {
        $this->seedPlayer('Martin', 'Jean', fideId: 999, belgianId: 888);
        $id = $this->seedPlayer();

        $errors = $this->edit($id, ['fide_id' => '999', 'belgian_id' => '888']);

        $this->assertCount(2, $errors);
        $this->assertNull($this->fetchPlayer($id)['fide_id']);
    }

    public function testUpdatesClubPerParticipation(): void
    {
        $this->seedClub(601, 'Club A');
        $this->seedClub(602, 'Club B');
        $id = $this->seedPlayer();
        $tpId = $this->seedParticipation($this->seedTournament(), $id);

        $this->assertSame([], $this->edit($id, ['clubs' => [$tpId => '602']]));
        $club = self::$db->query("SELECT club_id FROM jef_tournament_players WHERE id = {$tpId}")->fetchColumn();
        $this->assertSame(602, (int) $club);

        $this->assertSame([], $this->edit($id, ['clubs' => [$tpId => '']]));
        $club = self::$db->query("SELECT club_id FROM jef_tournament_players WHERE id = {$tpId}")->fetchColumn();
        $this->assertNull($club);
    }

    public function testRejectsUnknownClubAndForeignParticipation(): void
    {
        $tournamentId = $this->seedTournament();
        $id = $this->seedPlayer();
        $ownTp = $this->seedParticipation($tournamentId, $id);
        $otherTp = $this->seedParticipation($tournamentId, $this->seedPlayer('Martin', 'Jean'));
        $this->seedClub(601, 'Club A');

        $this->assertNotSame([], $this->edit($id, ['clubs' => [$ownTp => '999']]));
        $this->assertNotSame([], $this->edit($id, ['clubs' => [$otherTp => '601']]));

        $club = self::$db->query("SELECT club_id FROM jef_tournament_players WHERE id = {$otherTp}")->fetchColumn();
        $this->assertNull($club);
    }

    public function testBirthDateChangeRecalculatesRankingsInEverySeason(): void
    {
        $id = $this->seedPlayer(birthDate: '2017-01-01'); // U10 in 2025 and 2026
        $t2025 = $this->seedTournament(2025);
        $t2026 = $this->seedTournament(2026);
        $this->seedParticipation($t2025, $id);
        $this->seedParticipation($t2026, $id);
        $s2025 = $this->seasonOf($t2025);
        $s2026 = $this->seasonOf($t2026);
        RankingCalculator::recalculate(self::$db, $s2025);
        RankingCalculator::recalculate(self::$db, $s2026);

        $this->assertSame(['U10', 'general'], $this->rankingTypes($id, $s2025));
        $this->assertSame(['U10', 'general'], $this->rankingTypes($id, $s2026));

        $this->assertSame([], $this->edit($id, ['birth_date' => '2014-01-01']));

        $this->assertSame(['U12', 'general'], $this->rankingTypes($id, $s2025));
        $this->assertSame(['U14', 'general'], $this->rankingTypes($id, $s2026));
    }

    public function testUnchangedBirthDateDoesNotRecalculateRankings(): void
    {
        $id = $this->seedPlayer(birthDate: '2017-01-01');
        $tournamentId = $this->seedTournament();
        $this->seedParticipation($tournamentId, $id);
        $seasonId = $this->seasonOf($tournamentId);
        RankingCalculator::recalculate(self::$db, $seasonId);

        // Sentinel value a recalculation would overwrite.
        self::$db->exec("UPDATE jef_circuit_rankings SET total_points = 999 WHERE player_id = {$id}");

        $this->assertSame([], $this->edit($id, ['first_name' => 'Marion', 'birth_date' => '2017-01-01']));

        $points = self::$db->query(
            "SELECT DISTINCT total_points FROM jef_circuit_rankings WHERE player_id = {$id}"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertEquals([999], $points);
    }

    public function testFindIdentityCollisions(): void
    {
        $id = $this->seedPlayer('Dupond', 'Marie', '2017-01-01');
        $twin = $this->seedPlayer('Dupont', 'Marie', '2017-01-01', fideId: 777);
        $this->seedPlayer('Dupont', 'Marie', '2016-01-01'); // different birth date

        $this->assertSame([], $this->edit($id, ['last_name' => 'Dupont']));

        $collisions = PlayerEditor::findIdentityCollisions(self::$db, $id, [
            'last_name' => 'Dupont', 'first_name' => 'Marie', 'birth_date' => '2017-01-01',
        ]);
        $this->assertSame([['id' => $twin, 'fide_id' => 777]], $collisions);
    }

    public function testFindIdentityCollisionsMatchesNullBirthDates(): void
    {
        $id = $this->seedPlayer('Dupont', 'Marie', null);
        $twin = $this->seedPlayer('Dupont', 'Marie', null);

        $collisions = PlayerEditor::findIdentityCollisions(self::$db, $id, [
            'last_name' => 'Dupont', 'first_name' => 'Marie', 'birth_date' => null,
        ]);
        $this->assertSame([['id' => $twin, 'fide_id' => null]], $collisions);
    }

    public function testFindIdentityCollisionsExcludesSelf(): void
    {
        $id = $this->seedPlayer('Dupont', 'Marie', '2017-01-01');

        $this->assertSame([], PlayerEditor::findIdentityCollisions(self::$db, $id, [
            'last_name' => 'Dupont', 'first_name' => 'Marie', 'birth_date' => '2017-01-01',
        ]));
    }
}
