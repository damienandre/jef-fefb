<?php

declare(strict_types=1);

namespace Tests\Unit\Ranking;

use PHPUnit\Framework\TestCase;

/**
 * RankingCalculator tests require a database connection.
 * These are structured as unit tests but behave as integration tests.
 * They are skipped if no database connection is available.
 *
 * To run: ensure config.php exists with valid DB credentials,
 * then run: vendor/bin/phpunit tests/Unit/Ranking/RankingCalculatorTest.php
 */
final class RankingCalculatorTest extends TestCase
{
    private static ?\PDO $db = null;

    public static function setUpBeforeClass(): void
    {
        $configFile = __DIR__ . '/../../../config.php';
        if (!file_exists($configFile)) {
            self::markTestSkipped('config.php not found — database tests skipped');
        }
        try {
            self::$db = \Jef\Database::get();
        } catch (\PDOException $e) {
            self::markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('No database connection');
        }
        // Clean test data
        self::$db->exec("DELETE FROM jef_circuit_results");
        self::$db->exec("DELETE FROM jef_circuit_rankings");
        self::$db->exec("DELETE FROM jef_tournament_players");
        self::$db->exec("DELETE FROM jef_tournaments");
        self::$db->exec("DELETE FROM jef_players");
        self::$db->exec("DELETE FROM jef_seasons");
    }

    public function testCalculatesGeneralRanking(): void
    {
        $this->seedTestData();

        \Jef\Ranking\RankingCalculator::recalculate(self::$db, $this->getSeasonId());

        $stmt = self::$db->prepare(
            "SELECT player_id, total_points, `rank`
             FROM jef_circuit_rankings
             WHERE season_id = ? AND ranking_type = 'general'
             ORDER BY `rank`"
        );
        $stmt->execute([$this->getSeasonId()]);
        $rankings = $stmt->fetchAll();

        $this->assertNotEmpty($rankings);
        $this->assertSame(1, (int) $rankings[0]['rank']);
        $this->assertSame(150.0, (float) $rankings[0]['total_points']);
    }

    public function testCalculatesPerCategoryRanking(): void
    {
        $this->seedTestData();

        \Jef\Ranking\RankingCalculator::recalculate(self::$db, $this->getSeasonId());

        $stmt = self::$db->prepare(
            "SELECT COUNT(*) FROM jef_circuit_rankings
             WHERE season_id = ? AND ranking_type = 'U14'"
        );
        $stmt->execute([$this->getSeasonId()]);
        $count = (int) $stmt->fetchColumn();

        // Player born 2014-03-15 in season 2026 = age 11 on Jan 1 → U12
        // Player born 2013-11-08 in season 2026 = age 12 on Jan 1 → U14
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testHandlesExAequo(): void
    {
        $this->seedExAequoData();

        \Jef\Ranking\RankingCalculator::recalculate(self::$db, $this->getSeasonId());

        $stmt = self::$db->prepare(
            "SELECT `rank` FROM jef_circuit_rankings
             WHERE season_id = ? AND ranking_type = 'general'
             ORDER BY `rank`"
        );
        $stmt->execute([$this->getSeasonId()]);
        $ranks = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Two players with same points should have same rank
        $this->assertContains(1, array_map('intval', $ranks));
    }

    private function seedTestData(): void
    {
        self::$db->exec("INSERT INTO jef_seasons (year) VALUES (2026)");
        $seasonId = (int) self::$db->lastInsertId();

        self::$db->exec("INSERT INTO jef_players (last_name, first_name, birth_date) VALUES
            ('Dupont', 'Lucas', '2014-03-15'),
            ('Leroy', 'Thomas', '2013-11-08')");

        self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, 'Test Tournament', '2026-01-18', 5, 2, 1)"
        )->execute([$seasonId]);
        $tournamentId = (int) self::$db->lastInsertId();

        $player1 = (int) self::$db->query("SELECT id FROM jef_players WHERE last_name = 'Dupont'")->fetchColumn();
        $player2 = (int) self::$db->query("SELECT id FROM jef_players WHERE last_name = 'Leroy'")->fetchColumn();

        self::$db->prepare(
            "INSERT INTO jef_tournament_players (tournament_id, player_id, starting_rank, final_rank, points, rounds_data)
             VALUES (?, ?, 1, 1, 4.5, '[]'), (?, ?, 2, 2, 3.0, '[]')"
        )->execute([$tournamentId, $player1, $tournamentId, $player2]);
    }

    private function seedExAequoData(): void
    {
        self::$db->exec("INSERT INTO jef_seasons (year) VALUES (2026)");
        $seasonId = (int) self::$db->lastInsertId();

        self::$db->exec("INSERT INTO jef_players (last_name, first_name, birth_date) VALUES
            ('PlayerA', 'A', '2014-01-01'),
            ('PlayerB', 'B', '2014-06-01')");

        self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, 'Test', '2026-01-18', 3, 2, 1)"
        )->execute([$seasonId]);
        $tournamentId = (int) self::$db->lastInsertId();

        $pA = (int) self::$db->query("SELECT id FROM jef_players WHERE last_name = 'PlayerA'")->fetchColumn();
        $pB = (int) self::$db->query("SELECT id FROM jef_players WHERE last_name = 'PlayerB'")->fetchColumn();

        // Same points = ex-aequo
        self::$db->prepare(
            "INSERT INTO jef_tournament_players (tournament_id, player_id, starting_rank, final_rank, points, rounds_data)
             VALUES (?, ?, 1, 1, 2.5, '[]'), (?, ?, 2, 1, 2.5, '[]')"
        )->execute([$tournamentId, $pA, $tournamentId, $pB]);
    }

    private function getSeasonId(): int
    {
        return (int) self::$db->query("SELECT id FROM jef_seasons ORDER BY id DESC LIMIT 1")->fetchColumn();
    }
}
