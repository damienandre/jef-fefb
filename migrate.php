<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Jef\Database;

$db = Database::get();

// Ensure schema_migrations table exists
$db->exec("CREATE TABLE IF NOT EXISTS jef_schema_migrations (
    version INT UNSIGNED NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Get current version
$stmt = $db->query("SELECT COALESCE(MAX(version), 0) FROM jef_schema_migrations");
$currentVersion = (int) $stmt->fetchColumn();

echo "Current schema version: {$currentVersion}\n";

// Find pending migration files
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$applied = 0;

foreach ($files as $file) {
    $filename = basename($file);
    if (!preg_match('/^(\d+)_/', $filename, $matches)) {
        continue;
    }
    $version = (int) $matches[1];

    if ($version <= $currentVersion) {
        continue;
    }

    echo "Applying migration {$filename}...\n";

    $sql = file_get_contents($file);

    try {
        $db->exec($sql);
        $db->prepare("INSERT INTO jef_schema_migrations (version) VALUES (?)")->execute([$version]);
        $applied++;
        echo "  Done.\n";
    } catch (\PDOException $e) {
        echo "  FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($applied === 0) {
    echo "No pending migrations.\n";
} else {
    echo "Applied {$applied} migration(s).\n";
}
