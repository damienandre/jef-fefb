<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use PHPUnit\Framework\TestCase;

final class DashboardQueryTest extends TestCase
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

    public function testSeasonsOrderedByYearDesc(): void
    {
        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2024, 'finished')");
        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2026, 'active')");
        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2025, 'finished')");

        $seasons = self::$db->query(
            "SELECT id, year, status FROM jef_seasons ORDER BY year DESC"
        )->fetchAll();

        $this->assertCount(3, $seasons);
        $this->assertSame(2026, (int) $seasons[0]['year']);
        $this->assertSame(2025, (int) $seasons[1]['year']);
        $this->assertSame(2024, (int) $seasons[2]['year']);
    }

    public function testTournamentsGroupedBySeason(): void
    {
        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2026, 'active')");
        $season2026 = (int) self::$db->lastInsertId();

        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2025, 'finished')");
        $season2025 = (int) self::$db->lastInsertId();

        $stmt = self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, ?, ?, 0, 0, ?)"
        );
        $stmt->execute([$season2026, 'Étape 1 (2026)', '2026-01-15', 1]);
        $stmt->execute([$season2026, 'Étape 2 (2026)', '2026-02-15', 2]);
        $stmt->execute([$season2025, 'Étape 1 (2025)', '2025-01-15', 1]);

        $allTournaments = self::$db->query(
            "SELECT id, season_id, name, date_start, player_count, sort_order
             FROM jef_tournaments ORDER BY sort_order"
        )->fetchAll();

        $tournaments = [];
        foreach ($allTournaments as $t) {
            $tournaments[$t['season_id']][] = $t;
        }

        $this->assertCount(2, $tournaments[$season2026]);
        $this->assertCount(1, $tournaments[$season2025]);
        $this->assertSame('Étape 1 (2026)', $tournaments[$season2026][0]['name']);
        $this->assertSame('Étape 2 (2026)', $tournaments[$season2026][1]['name']);
    }

    public function testTournamentsOrderedBySortOrder(): void
    {
        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2026, 'active')");
        $seasonId = (int) self::$db->lastInsertId();

        $stmt = self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, ?, ?, 0, 0, ?)"
        );
        $stmt->execute([$seasonId, 'Étape 3', '2026-03-01', 3]);
        $stmt->execute([$seasonId, 'Étape 1', '2026-01-01', 1]);
        $stmt->execute([$seasonId, 'Étape 2', '2026-02-01', 2]);

        $tournaments = self::$db->query(
            "SELECT name, sort_order FROM jef_tournaments ORDER BY sort_order"
        )->fetchAll();

        $this->assertSame(1, (int) $tournaments[0]['sort_order']);
        $this->assertSame(2, (int) $tournaments[1]['sort_order']);
        $this->assertSame(3, (int) $tournaments[2]['sort_order']);
    }

    public function testEmptyDashboard(): void
    {
        $seasons = self::$db->query(
            "SELECT id, year, status FROM jef_seasons ORDER BY year DESC"
        )->fetchAll();

        $this->assertCount(0, $seasons);
    }
}
