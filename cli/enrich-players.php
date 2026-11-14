<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Jef\Database;
use Jef\Frbe\FrbeExport;
use Jef\Frbe\FrbeExportDownloader;
use Jef\PlayerClubEnricher;

$scriptName = array_shift($argv) ?? 'cli/enrich-players.php';

$known = ['--dry-run' => true, '-n' => true];
$dryRun = false;
foreach ($argv as $arg) {
    if (!isset($known[$arg])) {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        fwrite(STDERR, "Usage: php {$scriptName} [--dry-run|-n]\n");
        exit(2);
    }
    $dryRun = true;
}

$db = Database::get();
// Always fetch a fresh export: this is the full backfill / safety net.
$downloader = new FrbeExportDownloader();
$api = FrbeExport::live($downloader, forceRefresh: true);

echo "Player club & rating enrichment" . ($dryRun ? " [DRY RUN]" : "") . "\n";
echo str_repeat('=', 60) . "\n";
echo "Downloading the FRBE player export — this may take a moment...\n";

$report = PlayerClubEnricher::run($db, $api, $dryRun);

if ($api->error() !== null) {
    fwrite(STDERR, "\nWarning: the FRBE player export could not be loaded ("
        . $api->error() . "); nothing was enriched.\n");
} elseif ($downloader->refreshError() !== null) {
    fwrite(STDERR, "\nWarning: the FRBE player export could not be refreshed ("
        . $downloader->refreshError() . "); an older cached copy was used.\n");
}
if ($api->error() === null && $report['clubs_loaded'] === 0) {
    fwrite(STDERR, "\nWarning: 0 clubs loaded from the FRBE player export; "
        . "enrichment results below are likely incomplete.\n");
}

echo "\nSummary:\n";
printf("  %-28s%d\n", 'clubs loaded:',            $report['clubs_loaded']);
printf("  %-28s%d\n", 'belgian ids resolved:',    $report['belgian_ids_resolved']);
printf("  %-28s%d\n", 'tournament clubs set:',    $report['tp_clubs_set']);
printf("  %-28s%d\n", 'players without FIDE id:', $report['players_skipped_no_fide']);

echo $dryRun
    ? "\n[DRY RUN] No changes were committed. Re-run without --dry-run to apply.\n"
    : "\nDone.\n";
