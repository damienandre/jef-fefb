<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

$seasons = $db->query(
    "SELECT id, year, status FROM jef_seasons ORDER BY year DESC"
)->fetchAll();

$allTournaments = $db->query(
    "SELECT id, season_id, name, date_start, player_count, sort_order
     FROM jef_tournaments ORDER BY sort_order"
)->fetchAll();

$tournaments = [];
foreach ($allTournaments as $t) {
    $tournaments[$t['season_id']][] = $t;
}

View::render('admin/dashboard.html.php', [
    'pageTitle' => 'Tableau de bord - Administration JEF',
    'seasons' => $seasons,
    'tournaments' => $tournaments,
], 'admin/layout.php');
