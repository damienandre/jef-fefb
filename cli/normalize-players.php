<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Jef\Database;
use Jef\PlayerNormalizer;

$scriptName = array_shift($argv) ?? 'cli/normalize-players.php';

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

$mergedByEvidence = ['fide' => [], 'name+dob' => []];
foreach ($report['merged'] as $m) {
    $mergedByEvidence[$m['evidence']][] = $m;
}

if (!empty($mergedByEvidence['fide'])) {
    echo "\nClusters merged (FIDE-anchored):\n";
    foreach ($mergedByEvidence['fide'] as $m) {
        printMergeLine($m);
    }
}

if (!empty($mergedByEvidence['name+dob'])) {
    echo "\nClusters merged (name + birth_date only — review on dry-run):\n";
    foreach ($mergedByEvidence['name+dob'] as $m) {
        printMergeLine($m);
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
printf("  %-28s%d\n", 'rows normalized:',            count($report['renamed']));
printf("  %-28s%d\n", 'clusters merged (FIDE):',     count($mergedByEvidence['fide']));
printf("  %-28s%d\n", 'clusters merged (name+dob):', count($mergedByEvidence['name+dob']));
printf("  %-28s%d\n", 'clusters skipped:',           count($report['skipped']));
printf("  %-28s%d\n", 'seasons recalculated:',       count($report['seasons_recalculated']));
echo $dryRun
    ? "\n[DRY RUN] No changes were committed. Re-run without --dry-run to apply.\n"
    : "\nDone.\n";

/**
 * @param array{canonical_id:int, duplicate_ids:int[], tournaments_moved:int, evidence:string, tp_overlaps_dropped:array} $m
 */
function printMergeLine(array $m): void
{
    $dups = implode(', ', array_map(fn($i) => '#' . $i, $m['duplicate_ids']));
    printf(
        "  canonical #%d  <-  duplicates [%s]  (%d tournament row(s) moved)\n",
        $m['canonical_id'],
        $dups,
        $m['tournaments_moved']
    );
    foreach ($m['tp_overlaps_dropped'] as $o) {
        printf(
            "      DROPPED tp tournament=%d player=%d points=%.1f rank=%s  (kept canonical points=%.1f rank=%s)\n",
            $o['tournament_id'],
            $o['dropped_player_id'],
            $o['dropped_points'],
            $o['dropped_rank'] ?? '?',
            $o['kept_points'],
            $o['kept_rank'] ?? '?'
        );
    }
}
