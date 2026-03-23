<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

$seasons = $db->query(
    "SELECT s.id, s.year, s.status,
            (SELECT COUNT(*) FROM jef_tournaments t WHERE t.season_id = s.id) as tournament_count
     FROM jef_seasons s
     ORDER BY s.year DESC"
)->fetchAll();

$tournaments = [];
foreach ($seasons as $season) {
    $stmt = $db->prepare(
        "SELECT id, name, date_start, player_count, sort_order
         FROM jef_tournaments WHERE season_id = ? ORDER BY sort_order"
    );
    $stmt->execute([$season['id']]);
    $tournaments[$season['id']] = $stmt->fetchAll();
}

View::render('admin/dashboard.html.php', [
    'pageTitle' => 'Tableau de bord - Administration JEF',
    'seasons' => $seasons,
    'tournaments' => $tournaments,
], 'admin/layout.php');
