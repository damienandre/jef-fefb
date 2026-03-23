<?php

declare(strict_types=1);

use Jef\Database;
use Jef\Ranking\AgeCategory;
use Jef\View;

$db = Database::get();

$selectedYear = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');
$selectedCategory = $_GET['categorie'] ?? 'general';

// Get available years
$yearsStmt = $db->query("SELECT year FROM jef_seasons ORDER BY year DESC");
$availableYears = $yearsStmt->fetchAll(\PDO::FETCH_COLUMN);

if (empty($availableYears)) {
    $availableYears = [$selectedYear];
}

if (!in_array($selectedYear, $availableYears, true)) {
    $selectedYear = $availableYears[0] ?? (int) date('Y');
}

// Get season
$seasonStmt = $db->prepare("SELECT id FROM jef_seasons WHERE year = ?");
$seasonStmt->execute([$selectedYear]);
$seasonId = $seasonStmt->fetchColumn();

$rankings = [];
$tournaments = [];

if ($seasonId) {
    // Get tournaments for this season
    $tourStmt = $db->prepare(
        "SELECT id, name, sort_order FROM jef_tournaments WHERE season_id = ? ORDER BY sort_order"
    );
    $tourStmt->execute([$seasonId]);
    $tournaments = $tourStmt->fetchAll();

    // Get rankings
    $rankStmt = $db->prepare(
        "SELECT cr.`rank`, cr.total_points, cr.player_id,
                p.first_name, p.last_name, p.birth_date
         FROM jef_circuit_rankings cr
         JOIN jef_players p ON p.id = cr.player_id
         WHERE cr.season_id = ? AND cr.ranking_type = ?
         ORDER BY cr.`rank` ASC"
    );
    $rankStmt->execute([$seasonId, $selectedCategory]);
    $rankings = $rankStmt->fetchAll();

    // Get circuit results for per-tournament columns
    if (!empty($rankings) && !empty($tournaments)) {
        $playerIds = array_column($rankings, 'player_id');
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));

        $resStmt = $db->prepare(
            "SELECT player_id, tournament_id, tournament_rank, circuit_points
             FROM jef_circuit_results
             WHERE season_id = ? AND ranking_type = ? AND player_id IN ({$placeholders})"
        );
        $resStmt->execute(array_merge([$seasonId, $selectedCategory], $playerIds));
        $results = $resStmt->fetchAll();

        // Index by player_id => tournament_id => result
        $resultsByPlayer = [];
        foreach ($results as $r) {
            $resultsByPlayer[$r['player_id']][$r['tournament_id']] = $r;
        }

        // Attach to rankings
        foreach ($rankings as &$row) {
            $row['category'] = AgeCategory::determine(
                new \DateTimeImmutable($row['birth_date']),
                $selectedYear
            ) ?? '—';
            $row['tournament_results'] = [];
            foreach ($tournaments as $t) {
                $row['tournament_results'][$t['id']] = $resultsByPlayer[$row['player_id']][$t['id']] ?? null;
            }
        }
        unset($row);
    }
}

$allCategories = AgeCategory::all();

View::render('rankings.html.php', [
    'pageTitle' => 'Classement du Circuit JEF',
    'rankings' => $rankings,
    'tournaments' => $tournaments,
    'selectedYear' => $selectedYear,
    'selectedCategory' => $selectedCategory,
    'availableYears' => $availableYears,
    'allCategories' => $allCategories,
    'seasonId' => $seasonId,
], 'layout.php');
