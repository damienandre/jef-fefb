<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use Jef\PlayerEditor;
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

    private function seedTournament(): int
    {
        self::$db->exec("INSERT INTO jef_seasons (year) VALUES (2026)");
        $seasonId = (int) self::$db->lastInsertId();
        self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, 'T1', '2026-01-31', 1, 1, 1)"
        )->execute([$seasonId]);
        return (int) self::$db->lastInsertId();
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

    public function testBirthDateChangeRecalculatesRankings(): void
    {
        $id = $this->seedPlayer(birthDate: '2017-01-01'); // U10 in 2026
        $tournamentId = $this->seedTournament();
        $this->seedParticipation($tournamentId, $id);
        $seasonId = (int) self::$db->query("SELECT season_id FROM jef_tournaments WHERE id = {$tournamentId}")->fetchColumn();
        \Jef\Ranking\RankingCalculator::recalculate(self::$db, $seasonId);

        $types = function () use ($id): array {
            $rows = self::$db->query(
                "SELECT ranking_type FROM jef_circuit_rankings WHERE player_id = {$id}"
            )->fetchAll(\PDO::FETCH_COLUMN);
            sort($rows);
            return $rows;
        };
        $this->assertSame(['U10', 'general'], $types());

        $this->assertSame([], $this->edit($id, ['birth_date' => '2014-01-01'])); // U14 in 2026

        $this->assertSame(['U14', 'general'], $types());
    }
}
