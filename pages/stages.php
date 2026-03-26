<?php

declare(strict_types=1);

use Jef\Database;
use Jef\View;

$db = Database::get();

$selectedYear = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');

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

$stages = [];

if ($seasonId) {
    $stmt = $db->prepare(
        "SELECT t.id, t.name, t.location, t.organizer, t.address,
                t.date_start, t.date_end, t.info_url, t.registration_url,
                t.player_count, t.sort_order,
                t.trf_raw IS NOT NULL AS is_completed
         FROM jef_tournaments t
         WHERE t.season_id = ?
         ORDER BY t.sort_order ASC"
    );
    $stmt->execute([$seasonId]);
    $stages = $stmt->fetchAll();
}

$today = date('Y-m-d');

View::render('stages.html.php', [
    'pageTitle' => 'Etapes du Circuit JEF',
    'stages' => $stages,
    'selectedYear' => $selectedYear,
    'availableYears' => $availableYears,
    'today' => $today,
], 'layout.php');
