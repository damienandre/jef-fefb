<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez réessayer.';
        \Jef\Url::redirect('/admin/players');
    }

    $playerId = filter_var($_POST['player_id'] ?? '', FILTER_VALIDATE_INT);
    $fideId = filter_var($_POST['fide_id'] ?? '', FILTER_VALIDATE_INT);

    if (!$playerId) {
        $_SESSION['flash_error'] = 'Joueur invalide.';
        \Jef\Url::redirect('/admin/players');
    }

    if ($fideId === false || $fideId <= 0 || $fideId > 999999999) {
        $_SESSION['flash_error'] = 'L\'ID FIDE doit être un nombre entier positif (max 999 999 999).';
        \Jef\Url::redirect('/admin/players');
    }

    $stmt = $db->prepare("SELECT id FROM jef_players WHERE id = ?");
    $stmt->execute([$playerId]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = 'Joueur introuvable.';
        \Jef\Url::redirect('/admin/players');
    }

    $stmt = $db->prepare("SELECT id FROM jef_players WHERE fide_id = ? AND id != ?");
    $stmt->execute([$fideId, $playerId]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = 'Cet ID FIDE est déjà attribué à un autre joueur.';
        \Jef\Url::redirect('/admin/players');
    }

    $stmt = $db->prepare("UPDATE jef_players SET fide_id = ? WHERE id = ?");
    $stmt->execute([$fideId, $playerId]);

    $_SESSION['flash_success'] = 'ID FIDE mis à jour.';
    \Jef\Url::redirect('/admin/players');
}

$showAll = isset($_GET['all']);

$clubSubquery =
    "(SELECT COALESCE(c.name_short, c.name_long)
        FROM jef_tournament_players tp
        JOIN jef_tournaments t ON t.id = tp.tournament_id
        LEFT JOIN jef_clubs c ON c.idclub = tp.club_id
       WHERE tp.player_id = p.id AND tp.club_id IS NOT NULL
       ORDER BY t.date_start DESC, t.sort_order DESC
       LIMIT 1) AS club_name";

if ($showAll) {
    $players = $db->query(
        "SELECT p.id, p.last_name, p.first_name, p.birth_date, p.fide_id, p.belgian_id,
                {$clubSubquery}
         FROM jef_players p
         ORDER BY p.last_name, p.first_name"
    )->fetchAll();
} else {
    $players = $db->query(
        "SELECT p.id, p.last_name, p.first_name, p.birth_date, p.fide_id, p.belgian_id,
                {$clubSubquery}
         FROM jef_players p
         WHERE p.fide_id IS NULL OR p.fide_id = 0
         ORDER BY p.last_name, p.first_name"
    )->fetchAll();
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/players.html.php', [
    'pageTitle' => 'Joueurs - Administration JEF',
    'players' => $players,
    'showAll' => $showAll,
    'csrfToken' => $csrfToken,
], 'admin/layout.php');
