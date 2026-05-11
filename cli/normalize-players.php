<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Jef\Database;
use Jef\PlayerNormalizer;

$dryRun = in_array('--dry-run', $argv, true) || in_array('-n', $argv, true);

$db = Database::get();

echo "Player normalization" . ($dryRun ? " [DRY RUN]" : "") . "\n";
echo str_repeat('=', 60) . "\n";

$report = PlayerNormalizer::run($db, $dryRun);

if (!empty($report['renamed'])) {
    echo "\nNames normalized:\n";
    foreach ($report['renamed'] as $r) {
        printf(
            "  #%d  %s, %s  ->  %s, %s\n",
            $r['id'],
            $r['before_last'], $r['before_first'],
            $r['after_last'], $r['after_first']
        );
    }
}

if (!empty($report['merged'])) {
    echo "\nClusters merged:\n";
    foreach ($report['merged'] as $m) {
        $dups = implode(', ', array_map(fn($i) => '#' . $i, $m['duplicate_ids']));
        printf(
            "  canonical #%d  <-  duplicates [%s]  (%d tournament row(s) moved)\n",
            $m['canonical_id'],
            $dups,
            $m['tournaments_moved']
        );
    }
}

if (!empty($report['skipped'])) {
    echo "\nClusters SKIPPED (need manual review):\n";
    foreach ($report['skipped'] as $s) {
        $ids = implode(', ', array_map(fn($i) => '#' . $i, $s['ids']));
        printf(
            "  %s, %s (%s) ids=[%s]: %s\n",
            $s['last_name'], $s['first_name'], $s['birth_date'] ?? '?',
            $ids,
            $s['reason']
        );
    }
}

if (!empty($report['seasons_recalculated'])) {
    echo "\nSeasons recalculated: "
        . implode(', ', $report['seasons_recalculated']) . "\n";
}

echo "\nSummary:\n";
echo "  rows normalized:      " . count($report['renamed']) . "\n";
echo "  clusters merged:      " . count($report['merged']) . "\n";
echo "  clusters skipped:     " . count($report['skipped']) . "\n";
echo "  seasons recalculated: " . count($report['seasons_recalculated']) . "\n";
echo $dryRun
    ? "\n[DRY RUN] No changes were committed. Re-run without --dry-run to apply.\n"
    : "\nDone.\n";
