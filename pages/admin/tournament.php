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

$stmt = $db->prepare(
    "SELECT id, name, location, organizer, address, info_url, registration_url
     FROM jef_tournaments WHERE id = ?"
);
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

    $location = trim($_POST['location'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $infoUrl = trim($_POST['info_url'] ?? '');
    $registrationUrl = trim($_POST['registration_url'] ?? '');

    if (mb_strlen($location) > 200 || mb_strlen($organizer) > 200) {
        $_SESSION['flash_error'] = 'Le lieu et l\'organisateur ne peuvent pas depasser 200 caracteres.';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    if (mb_strlen($address) > 500 || mb_strlen($infoUrl) > 500 || mb_strlen($registrationUrl) > 500) {
        $_SESSION['flash_error'] = 'L\'adresse et les URLs ne peuvent pas depasser 500 caracteres.';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    if ($infoUrl !== '' && (!filter_var($infoUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $infoUrl))) {
        $_SESSION['flash_error'] = 'L\'URL d\'information n\'est pas valide (doit commencer par http:// ou https://).';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    if ($registrationUrl !== '' && (!filter_var($registrationUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $registrationUrl))) {
        $_SESSION['flash_error'] = 'L\'URL d\'inscription n\'est pas valide (doit commencer par http:// ou https://).';
        header('Location: /admin/tournament?id=' . $id);
        exit;
    }

    $update = $db->prepare(
        "UPDATE jef_tournaments
         SET name = ?, location = ?, organizer = ?, address = ?, info_url = ?, registration_url = ?
         WHERE id = ?"
    );
    $update->execute([
        $name,
        $location === '' ? null : $location,
        $organizer === '' ? null : $organizer,
        $address === '' ? null : $address,
        $infoUrl === '' ? null : $infoUrl,
        $registrationUrl === '' ? null : $registrationUrl,
        $id,
    ]);

    $_SESSION['flash_success'] = 'Tournoi mis a jour.';
    header('Location: /admin');
    exit;
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/tournament.html.php', [
    'pageTitle' => 'Modifier le tournoi - Administration JEF',
    'tournament' => $tournament,
    'csrfToken' => $csrfToken,
], 'admin/layout.php');
