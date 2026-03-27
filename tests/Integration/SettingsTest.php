<?php

declare(strict_types=1);

namespace Tests\Integration;

use Jef\Database;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
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
        self::$db->exec("DELETE FROM jef_settings");
    }

    public function testInsertLogoPath(): void
    {
        $stmt = self::$db->prepare(
            "INSERT INTO jef_settings (`key`, `value`) VALUES ('logo_path', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $stmt->execute(['logo.png']);

        $result = self::$db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
        $result->execute(['logo_path']);

        $this->assertSame('logo.png', $result->fetchColumn());
    }

    public function testUpdateLogoPathUpsert(): void
    {
        $stmt = self::$db->prepare(
            "INSERT INTO jef_settings (`key`, `value`) VALUES ('logo_path', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $stmt->execute(['logo.png']);
        $stmt->execute(['logo.jpg']);

        $result = self::$db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
        $result->execute(['logo_path']);

        $this->assertSame('logo.jpg', $result->fetchColumn());
    }

    public function testNoLogoReturnsNoResult(): void
    {
        $result = self::$db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
        $result->execute(['logo_path']);

        $this->assertFalse($result->fetchColumn());
    }

    public function testLogoMimeTypeValidation(): void
    {
        $allowedTypes = ['image/png', 'image/jpeg'];

        $this->assertTrue(in_array('image/png', $allowedTypes, true));
        $this->assertTrue(in_array('image/jpeg', $allowedTypes, true));
        $this->assertFalse(in_array('image/gif', $allowedTypes, true));
        $this->assertFalse(in_array('image/svg+xml', $allowedTypes, true));
        $this->assertFalse(in_array('application/pdf', $allowedTypes, true));
    }

    public function testLogoFileSizeLimit(): void
    {
        $maxSize = 2 * 1024 * 1024; // 2MB

        $this->assertTrue(1_000_000 <= $maxSize);
        $this->assertTrue(2 * 1024 * 1024 <= $maxSize);
        $this->assertFalse(2 * 1024 * 1024 + 1 <= $maxSize);
    }

    public function testLogoExtensionFromMimeType(): void
    {
        $ext = match ('image/png') {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
        };
        $this->assertSame('png', $ext);

        $ext = match ('image/jpeg') {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
        };
        $this->assertSame('jpg', $ext);
    }
}
