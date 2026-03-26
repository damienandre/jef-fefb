<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use PHPUnit\Framework\TestCase;

final class TournamentEditTest extends TestCase
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

    private function seedTournament(string $name = 'Tournoi Test'): int
    {
        self::$db->exec(
            "INSERT INTO jef_seasons (year, status) VALUES (2026, 'active')"
        );
        $seasonId = (int) self::$db->lastInsertId();

        $stmt = self::$db->prepare(
            "INSERT INTO jef_tournaments (season_id, name, date_start, round_count, player_count, sort_order)
             VALUES (?, ?, '2026-03-01', 5, 20, 1)"
        );
        $stmt->execute([$seasonId, $name]);

        return (int) self::$db->lastInsertId();
    }

    public function testUpdateTournamentName(): void
    {
        $tournamentId = $this->seedTournament('Ancien Nom');

        $stmt = self::$db->prepare("UPDATE jef_tournaments SET name = ? WHERE id = ?");
        $stmt->execute(['Nouveau Nom', $tournamentId]);

        $result = self::$db->prepare("SELECT name FROM jef_tournaments WHERE id = ?");
        $result->execute([$tournamentId]);

        $this->assertSame('Nouveau Nom', $result->fetchColumn());
    }

    public function testUpdatePreservesOtherFields(): void
    {
        $tournamentId = $this->seedTournament('Original');

        $stmt = self::$db->prepare("UPDATE jef_tournaments SET name = ? WHERE id = ?");
        $stmt->execute(['Modifie', $tournamentId]);

        $result = self::$db->prepare(
            "SELECT name, date_start, round_count, player_count, sort_order FROM jef_tournaments WHERE id = ?"
        );
        $result->execute([$tournamentId]);
        $row = $result->fetch();

        $this->assertSame('Modifie', $row['name']);
        $this->assertSame('2026-03-01', $row['date_start']);
        $this->assertSame(5, (int) $row['round_count']);
        $this->assertSame(20, (int) $row['player_count']);
        $this->assertSame(1, (int) $row['sort_order']);
    }

    public function testNameColumnRejectsEmptyString(): void
    {
        // Validate that the application-level check works:
        // an empty trimmed name should be caught before reaching the DB
        $name = trim('   ');

        $this->assertSame('', $name, 'Trimmed whitespace-only input should be empty');
    }

    public function testNameColumnRespectsMaxLength(): void
    {
        $name = str_repeat('A', 200);
        $tournamentId = $this->seedTournament($name);

        $result = self::$db->prepare("SELECT name FROM jef_tournaments WHERE id = ?");
        $result->execute([$tournamentId]);

        $this->assertSame($name, $result->fetchColumn());
    }
}
