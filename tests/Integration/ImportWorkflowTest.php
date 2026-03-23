<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use Jef\ImportService;
use PHPUnit\Framework\TestCase;

final class ImportWorkflowTest extends TestCase
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

    public function testFullImportCycle(): void
    {
        $trfContent = file_get_contents(__DIR__ . '/../fixtures/sample.trf');

        $result = ImportService::import(self::$db, 2026, 1, $trfContent);

        $this->assertSame(123, $result['player_count']);
        $this->assertSame('JEF 2026 Etape 1 Rounds 1 - 9', $result['tournament_name']);

        // Verify season created
        $season = self::$db->query("SELECT * FROM jef_seasons WHERE year = 2026")->fetch();
        $this->assertNotFalse($season);

        // Verify tournament created
        $tournament = self::$db->query("SELECT * FROM jef_tournaments WHERE name = 'JEF 2026 Etape 1 Rounds 1 - 9'")->fetch();
        $this->assertNotFalse($tournament);
        $this->assertSame(123, (int) $tournament['player_count']);

        // Verify players created
        $playerCount = (int) self::$db->query("SELECT COUNT(*) FROM jef_players")->fetchColumn();
        $this->assertSame(123, $playerCount);

        // Verify rankings calculated
        $rankingCount = (int) self::$db->query(
            "SELECT COUNT(*) FROM jef_circuit_rankings WHERE ranking_type = 'general'"
        )->fetchColumn();
        $this->assertSame(123, $rankingCount);

        // Verify rank 1 player has highest circuit points
        $rank1 = self::$db->query(
            "SELECT total_points FROM jef_circuit_rankings WHERE ranking_type = 'general' AND `rank` = 1"
        )->fetchColumn();
        $this->assertSame(25.0, (float) $rank1);
    }

    public function testReimportReplacesData(): void
    {
        $trfContent = file_get_contents(__DIR__ . '/../fixtures/sample.trf');

        // First import
        ImportService::import(self::$db, 2026, 1, $trfContent);

        // Reimport same sort_order
        $result = ImportService::import(self::$db, 2026, 1, $trfContent);

        $this->assertSame(123, $result['player_count']);

        // Should still have 10 players (not 20)
        $playerCount = (int) self::$db->query("SELECT COUNT(*) FROM jef_players")->fetchColumn();
        $this->assertSame(123, $playerCount);

        // Should still have 1 tournament
        $tourCount = (int) self::$db->query("SELECT COUNT(*) FROM jef_tournaments")->fetchColumn();
        $this->assertSame(1, $tourCount);
    }

    public function testInvalidFileRollsBackTransaction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $invalidContent = file_get_contents(__DIR__ . '/../fixtures/invalid.trf');
        ImportService::import(self::$db, 2026, 1, $invalidContent);

        // No data should be written
        $seasonCount = (int) self::$db->query("SELECT COUNT(*) FROM jef_seasons")->fetchColumn();
        $this->assertSame(0, $seasonCount);
    }
}
