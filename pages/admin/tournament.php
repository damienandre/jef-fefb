<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: /admin');
    exit;
}

$stmt = $db->prepare("SELECT id, name FROM jef_tournaments WHERE id = ?");
$stmt->execute([$id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    header('Location: /admin');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez reessayer.';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $_SESSION['flash_error'] = 'Le nom du tournoi ne peut pas etre vide.';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    if (mb_strlen($name) > 200) {
        $_SESSION['flash_error'] = 'Le nom du tournoi ne peut pas depasser 200 caracteres.';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    $update = $db->prepare("UPDATE jef_tournaments SET name = ? WHERE id = ?");
    $update->execute([$name, $id]);

    $_SESSION['flash_success'] = 'Nom du tournoi mis a jour.';
    header('Location: /admin');
    exit;
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/tournament.html.php', [
    'pageTitle' => 'Modifier le tournoi - Administration JEF',
    'tournament' => $tournament,
    'csrfToken' => $csrfToken,
], 'admin/layout.php');
