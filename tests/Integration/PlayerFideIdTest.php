<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use PHPUnit\Framework\TestCase;

final class PlayerFideIdTest extends TestCase
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
    }

    private function seedPlayer(string $lastName, string $firstName, ?int $fideId = null): int
    {
        $stmt = self::$db->prepare(
            "INSERT INTO jef_players (last_name, first_name, fide_id) VALUES (?, ?, ?)"
        );
        $stmt->execute([$lastName, $firstName, $fideId]);

        return (int) self::$db->lastInsertId();
    }

    public function testAssignFideIdToPlayerWithout(): void
    {
        $playerId = $this->seedPlayer('Dupont', 'Marie');

        $stmt = self::$db->prepare("UPDATE jef_players SET fide_id = ? WHERE id = ?");
        $stmt->execute([12345678, $playerId]);

        $result = self::$db->prepare("SELECT fide_id FROM jef_players WHERE id = ?");
        $result->execute([$playerId]);

        $this->assertSame(12345678, (int) $result->fetchColumn());
    }

    public function testFideIdUniquenessConstraint(): void
    {
        $this->seedPlayer('Dupont', 'Marie', 12345678);
        $secondId = $this->seedPlayer('Martin', 'Jean');

        $this->expectException(\PDOException::class);

        $stmt = self::$db->prepare("UPDATE jef_players SET fide_id = ? WHERE id = ?");
        $stmt->execute([12345678, $secondId]);
    }

    public function testQueryPlayersWithoutFideId(): void
    {
        $this->seedPlayer('Dupont', 'Marie');
        $this->seedPlayer('Martin', 'Jean', 12345678);
        $this->seedPlayer('Leroy', 'Luc');

        $players = self::$db->query(
            "SELECT id, last_name FROM jef_players WHERE fide_id IS NULL OR fide_id = 0 ORDER BY last_name"
        )->fetchAll();

        $this->assertCount(2, $players);
        $this->assertSame('Dupont', $players[0]['last_name']);
        $this->assertSame('Leroy', $players[1]['last_name']);
    }

    public function testFideIdZeroTreatedAsNull(): void
    {
        $playerId = $this->seedPlayer('Dupont', 'Marie', 0);

        $players = self::$db->query(
            "SELECT id FROM jef_players WHERE fide_id IS NULL OR fide_id = 0"
        )->fetchAll();

        $this->assertCount(1, $players);
        $this->assertSame($playerId, (int) $players[0]['id']);
    }
}
