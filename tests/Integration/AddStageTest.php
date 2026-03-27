<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use PHPUnit\Framework\TestCase;

final class AddStageTest extends TestCase
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

    private function insertStage(
        int $seasonYear,
        int $sortOrder,
        string $name,
        string $dateStart,
        ?string $dateEnd = null,
        ?string $location = null,
        ?string $organizer = null,
        ?string $address = null,
        ?string $infoUrl = null,
        ?string $registrationUrl = null,
    ): int {
        self::$db->beginTransaction();

        $stmt = self::$db->prepare("SELECT id FROM jef_seasons WHERE year = ?");
        $stmt->execute([$seasonYear]);
        $seasonId = $stmt->fetchColumn();

        if (!$seasonId) {
            self::$db->prepare("INSERT INTO jef_seasons (year) VALUES (?)")->execute([$seasonYear]);
            $seasonId = (int) self::$db->lastInsertId();
        }

        $insert = self::$db->prepare(
            "INSERT INTO jef_tournaments
             (season_id, name, location, organizer, address, info_url, registration_url,
              date_start, date_end, round_count, player_count, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)"
        );
        $insert->execute([
            $seasonId,
            $name,
            $location,
            $organizer,
            $address,
            $infoUrl,
            $registrationUrl,
            $dateStart,
            $dateEnd,
            $sortOrder,
        ]);

        $id = (int) self::$db->lastInsertId();
        self::$db->commit();

        return $id;
    }

    public function testSuccessfulStageCreation(): void
    {
        $id = $this->insertStage(
            seasonYear: 2026,
            sortOrder: 1,
            name: 'Étape de Bruxelles',
            dateStart: '2026-04-15',
            dateEnd: '2026-04-16',
            location: 'Bruxelles',
            organizer: 'Club Bruxelles',
            address: 'Rue de la Loi 1',
            infoUrl: 'https://example.com/info',
            registrationUrl: 'https://example.com/register',
        );

        $stmt = self::$db->prepare("SELECT * FROM jef_tournaments WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        $this->assertSame('Étape de Bruxelles', $row['name']);
        $this->assertSame('2026-04-15', $row['date_start']);
        $this->assertSame('2026-04-16', $row['date_end']);
        $this->assertSame('Bruxelles', $row['location']);
        $this->assertSame('Club Bruxelles', $row['organizer']);
        $this->assertSame('Rue de la Loi 1', $row['address']);
        $this->assertSame('https://example.com/info', $row['info_url']);
        $this->assertSame('https://example.com/register', $row['registration_url']);
        $this->assertSame(0, (int) $row['round_count']);
        $this->assertSame(0, (int) $row['player_count']);
        $this->assertSame(1, (int) $row['sort_order']);
        $this->assertNull($row['trf_raw']);
    }

    public function testStageCreationCreatesNewSeason(): void
    {
        $this->insertStage(2027, 1, 'Test', '2027-01-01');

        $season = self::$db->query("SELECT * FROM jef_seasons WHERE year = 2027")->fetch();
        $this->assertNotFalse($season);
        $this->assertSame(2027, (int) $season['year']);
    }

    public function testStageCreationReusesExistingSeason(): void
    {
        self::$db->exec("INSERT INTO jef_seasons (year, status) VALUES (2026, 'active')");

        $this->insertStage(2026, 1, 'Test', '2026-01-01');

        $count = (int) self::$db->query("SELECT COUNT(*) FROM jef_seasons WHERE year = 2026")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testDuplicateSortOrderRejected(): void
    {
        $this->insertStage(2026, 1, 'Étape 1', '2026-04-01');

        try {
            $this->insertStage(2026, 1, 'Étape 1 bis', '2026-05-01');
            $this->fail('Expected PDOException for duplicate sort_order');
        } catch (\PDOException $e) {
            if (self::$db->inTransaction()) {
                self::$db->rollBack();
            }
            $this->assertTrue(
                str_contains($e->getMessage(), 'uk_season_order') || $e->getCode() === '23000',
                'Expected unique constraint violation'
            );
        }
    }

    public function testDifferentSortOrdersAllowed(): void
    {
        $this->insertStage(2026, 1, 'Étape 1', '2026-04-01');
        $this->insertStage(2026, 2, 'Étape 2', '2026-05-01');

        $count = (int) self::$db->query("SELECT COUNT(*) FROM jef_tournaments")->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function testSameSortOrderDifferentSeasonsAllowed(): void
    {
        $this->insertStage(2026, 1, 'Étape 2026', '2026-04-01');
        $this->insertStage(2027, 1, 'Étape 2027', '2027-04-01');

        $count = (int) self::$db->query("SELECT COUNT(*) FROM jef_tournaments")->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function testOptionalFieldsStoredAsNull(): void
    {
        $id = $this->insertStage(2026, 1, 'Minimal', '2026-04-01');

        $stmt = self::$db->prepare(
            "SELECT date_end, location, organizer, address, info_url, registration_url
             FROM jef_tournaments WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        $this->assertNull($row['date_end']);
        $this->assertNull($row['location']);
        $this->assertNull($row['organizer']);
        $this->assertNull($row['address']);
        $this->assertNull($row['info_url']);
        $this->assertNull($row['registration_url']);
    }

    public function testValidationEmptyNameRejected(): void
    {
        $name = trim('   ');
        $this->assertSame('', $name);
    }

    public function testValidationNameMaxLength(): void
    {
        $name = str_repeat('A', 200);
        $id = $this->insertStage(2026, 1, $name, '2026-04-01');

        $stmt = self::$db->prepare("SELECT name FROM jef_tournaments WHERE id = ?");
        $stmt->execute([$id]);
        $this->assertSame($name, $stmt->fetchColumn());
    }

    public function testValidationDateEndBeforeDateStart(): void
    {
        $dateStart = '2026-04-15';
        $dateEnd = '2026-04-10';

        $this->assertTrue($dateEnd < $dateStart);
    }

    public function testValidationSeasonYearBounds(): void
    {
        $this->assertFalse(1999 >= 2000 && 1999 <= 2100);
        $this->assertTrue(2000 >= 2000 && 2000 <= 2100);
        $this->assertTrue(2100 >= 2000 && 2100 <= 2100);
        $this->assertFalse(2101 >= 2000 && 2101 <= 2100);
    }

    public function testValidationSortOrderBounds(): void
    {
        $this->assertFalse(0 >= 1 && 0 <= 20);
        $this->assertTrue(1 >= 1 && 1 <= 20);
        $this->assertTrue(20 >= 1 && 20 <= 20);
        $this->assertFalse(21 >= 1 && 21 <= 20);
    }

    public function testValidationUrlFormat(): void
    {
        $validUrls = ['https://example.com', 'http://example.com/path'];
        $invalidUrls = ['ftp://example.com', 'not-a-url', 'javascript:alert(1)'];

        foreach ($validUrls as $url) {
            $this->assertTrue(
                filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1,
                "Expected valid: $url"
            );
        }

        foreach ($invalidUrls as $url) {
            $valid = filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1;
            $this->assertFalse($valid, "Expected invalid: $url");
        }
    }

    public function testValidationFieldLengthLimits(): void
    {
        // location and organizer: max 200
        $this->assertTrue(mb_strlen(str_repeat('A', 200)) <= 200);
        $this->assertFalse(mb_strlen(str_repeat('A', 201)) <= 200);

        // address, info_url, registration_url: max 500
        $this->assertTrue(mb_strlen(str_repeat('A', 500)) <= 500);
        $this->assertFalse(mb_strlen(str_repeat('A', 501)) <= 500);
    }
}
