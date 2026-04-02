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
        header('Location: /admin/players');
        exit;
    }

    $playerId = filter_var($_POST['player_id'] ?? '', FILTER_VALIDATE_INT);
    $fideId = filter_var($_POST['fide_id'] ?? '', FILTER_VALIDATE_INT);

    if (!$playerId) {
        $_SESSION['flash_error'] = 'Joueur invalide.';
        header('Location: /admin/players');
        exit;
    }

    if ($fideId === false || $fideId <= 0 || $fideId > 999999999) {
        $_SESSION['flash_error'] = 'L\'ID FIDE doit être un nombre entier positif (max 999 999 999).';
        header('Location: /admin/players');
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM jef_players WHERE id = ?");
    $stmt->execute([$playerId]);
    if (!$stmt->fetch()) {
        $_SESSION['flash_error'] = 'Joueur introuvable.';
        header('Location: /admin/players');
        exit;
    }

    $stmt = $db->prepare("SELECT id FROM jef_players WHERE fide_id = ? AND id != ?");
    $stmt->execute([$fideId, $playerId]);
    if ($stmt->fetch()) {
        $_SESSION['flash_error'] = 'Cet ID FIDE est déjà attribué à un autre joueur.';
        header('Location: /admin/players');
        exit;
    }

    $stmt = $db->prepare("UPDATE jef_players SET fide_id = ? WHERE id = ?");
    $stmt->execute([$fideId, $playerId]);

    $_SESSION['flash_success'] = 'ID FIDE mis à jour.';
    header('Location: /admin/players');
    exit;
}

$showAll = isset($_GET['all']);

if ($showAll) {
    $players = $db->query(
        "SELECT id, last_name, first_name, birth_date, fide_id
         FROM jef_players
         ORDER BY last_name, first_name"
    )->fetchAll();
} else {
    $players = $db->query(
        "SELECT id, last_name, first_name, birth_date, fide_id
         FROM jef_players
         WHERE fide_id IS NULL OR fide_id = 0
         ORDER BY last_name, first_name"
    )->fetchAll();
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/players.html.php', [
    'pageTitle' => 'Joueurs - Administration JEF',
    'players' => $players,
    'showAll' => $showAll,
    'csrfToken' => $csrfToken,
], 'admin/layout.php');
