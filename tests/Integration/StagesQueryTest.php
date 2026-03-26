<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use PHPUnit\Framework\TestCase;

final class StagesQueryTest extends TestCase
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
    }

    private function seedSeason(int $year = 2026): int
    {
        self::$db->prepare(
            "INSERT INTO jef_seasons (year, status) VALUES (?, 'active')"
        )->execute([$year]);

        return (int) self::$db->lastInsertId();
    }

    private function seedTournament(int $seasonId, int $sortOrder, array $extra = []): int
    {
        $name = $extra['name'] ?? "Tournoi $sortOrder";
        $trfRaw = $extra['trf_raw'] ?? null;
        $organizer = $extra['organizer'] ?? null;
        $registrationUrl = $extra['registration_url'] ?? null;

        $stmt = self::$db->prepare(
            "INSERT INTO jef_tournaments
             (season_id, name, date_start, round_count, player_count, sort_order, trf_raw, organizer, registration_url)
             VALUES (?, ?, '2026-03-01', 5, 20, ?, ?, ?, ?)"
        );
        $stmt->execute([$seasonId, $name, $sortOrder, $trfRaw, $organizer, $registrationUrl]);

        return (int) self::$db->lastInsertId();
    }

    public function testStagesOrderedBySortOrder(): void
    {
        $seasonId = $this->seedSeason();
        $this->seedTournament($seasonId, 3, ['name' => 'Third']);
        $this->seedTournament($seasonId, 1, ['name' => 'First']);
        $this->seedTournament($seasonId, 2, ['name' => 'Second']);

        $stmt = self::$db->prepare(
            "SELECT name, sort_order FROM jef_tournaments WHERE season_id = ? ORDER BY sort_order ASC"
        );
        $stmt->execute([$seasonId]);
        $stages = $stmt->fetchAll();

        $this->assertCount(3, $stages);
        $this->assertSame('First', $stages[0]['name']);
        $this->assertSame('Second', $stages[1]['name']);
        $this->assertSame('Third', $stages[2]['name']);
    }

    public function testCompletionFlagReflectsTrfRaw(): void
    {
        $seasonId = $this->seedSeason();
        $this->seedTournament($seasonId, 1, ['name' => 'Completed', 'trf_raw' => 'content']);
        $this->seedTournament($seasonId, 2, ['name' => 'Upcoming']);

        $stmt = self::$db->prepare(
            "SELECT name, trf_raw IS NOT NULL AS is_completed
             FROM jef_tournaments WHERE season_id = ? ORDER BY sort_order"
        );
        $stmt->execute([$seasonId]);
        $stages = $stmt->fetchAll();

        $this->assertEquals(1, $stages[0]['is_completed']);
        $this->assertEquals(0, $stages[1]['is_completed']);
    }

    public function testStageInfoFieldsReturnedCorrectly(): void
    {
        $seasonId = $this->seedSeason();
        $this->seedTournament($seasonId, 1, [
            'organizer' => 'Club ABC',
            'registration_url' => 'https://example.com/register',
        ]);

        $stmt = self::$db->prepare(
            "SELECT organizer, registration_url, trf_raw IS NOT NULL AS is_completed
             FROM jef_tournaments WHERE season_id = ?"
        );
        $stmt->execute([$seasonId]);
        $row = $stmt->fetch();

        $this->assertSame('Club ABC', $row['organizer']);
        $this->assertSame('https://example.com/register', $row['registration_url']);
        $this->assertEquals(0, $row['is_completed']);
    }

    public function testStagesFilteredBySeason(): void
    {
        $season1 = $this->seedSeason(2025);
        $season2 = $this->seedSeason(2026);
        $this->seedTournament($season1, 1, ['name' => 'S1']);
        $this->seedTournament($season2, 1, ['name' => 'S2']);

        $stmt = self::$db->prepare(
            "SELECT name FROM jef_tournaments WHERE season_id = ?"
        );
        $stmt->execute([$season2]);
        $stages = $stmt->fetchAll();

        $this->assertCount(1, $stages);
        $this->assertSame('S2', $stages[0]['name']);
    }
}
