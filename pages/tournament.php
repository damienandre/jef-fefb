<?php

declare(strict_types=1);

use Jef\Database;
use Jef\Ranking\AgeCategory;
use Jef\View;

$db = Database::get();

$tournamentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($tournamentId <= 0) {
    require __DIR__ . '/404.php';
    return;
}

// Get tournament
$stmt = $db->prepare(
    "SELECT t.id, t.season_id, t.name, t.location, t.organizer, t.address,
            t.date_start, t.date_end, t.round_count, t.player_count, t.sort_order,
            t.info_url, t.registration_url,
            t.trf_raw IS NOT NULL AS is_completed,
            s.year as season_year
     FROM jef_tournaments t
     JOIN jef_seasons s ON s.id = t.season_id
     WHERE t.id = ?"
);
$stmt->execute([$tournamentId]);
$tournament = $stmt->fetch();

if (!$tournament) {
    require __DIR__ . '/404.php';
    return;
}

// Get tournament players with per-round data
$playersStmt = $db->prepare(
    "SELECT tp.starting_rank, tp.final_rank, tp.points, tp.rounds_data,
            p.first_name, p.last_name, p.birth_date
     FROM jef_tournament_players tp
     JOIN jef_players p ON p.id = tp.player_id
     WHERE tp.tournament_id = ?
     ORDER BY tp.points DESC, tp.starting_rank DESC"
);
$playersStmt->execute([$tournamentId]);
$players = $playersStmt->fetchAll();

// Decode rounds_data and build player name lookup by starting_rank
$playerNamesByRank = [];
foreach ($players as &$player) {
    $player['rounds'] = json_decode($player['rounds_data'], true) ?: [];
    $player['rounds_by_num'] = array_column($player['rounds'], null, 'round');
    $playerNamesByRank[$player['starting_rank']] = $player['first_name'] . ' ' . $player['last_name'];
    $player['category'] = $player['birth_date'] !== null
        ? (AgeCategory::determine(new \DateTimeImmutable($player['birth_date']), (int) $tournament['season_year']) ?? '')
        : '';
}
unset($player);

$displayRankByStartingRank = [];
foreach ($players as $index => $player) {
    $displayRankByStartingRank[$player['starting_rank']] = $index + 1;
}

View::render('tournament.html.php', [
    'pageTitle' => $tournament['name'] . ' - Circuit JEF',
    'tournament' => $tournament,
    'players' => $players,
    'playerNamesByRank' => $playerNamesByRank,
    'displayRankByStartingRank' => $displayRankByStartingRank,
], 'layout.php');
