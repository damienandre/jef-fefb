<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    \Jef\Url::redirect('/admin');
}

$stmt = $db->prepare(
    "SELECT id, name, location, organizer, address, info_url, registration_url
     FROM jef_tournaments WHERE id = ?"
);
$stmt->execute([$id]);
$tournament = $stmt->fetch();

if (!$tournament) {
    \Jef\Url::redirect('/admin');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez réessayer.';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $_SESSION['flash_error'] = 'Le nom du tournoi ne peut pas être vide.';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
    }

    if (mb_strlen($name) > 200) {
        $_SESSION['flash_error'] = 'Le nom du tournoi ne peut pas dépasser 200 caractères.';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
    }

    $location = trim($_POST['location'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $infoUrl = trim($_POST['info_url'] ?? '');
    $registrationUrl = trim($_POST['registration_url'] ?? '');

    if (mb_strlen($location) > 200 || mb_strlen($organizer) > 200) {
        $_SESSION['flash_error'] = 'Le lieu et l\'organisateur ne peuvent pas dépasser 200 caractères.';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
    }

    if (mb_strlen($address) > 500 || mb_strlen($infoUrl) > 500 || mb_strlen($registrationUrl) > 500) {
        $_SESSION['flash_error'] = 'L\'adresse et les URLs ne peuvent pas dépasser 500 caractères.';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
    }

    if ($infoUrl !== '' && (!filter_var($infoUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $infoUrl))) {
        $_SESSION['flash_error'] = 'L\'URL d\'information n\'est pas valide (doit commencer par http:// ou https://).';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
    }

    if ($registrationUrl !== '' && (!filter_var($registrationUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $registrationUrl))) {
        $_SESSION['flash_error'] = 'L\'URL d\'inscription n\'est pas valide (doit commencer par http:// ou https://).';
        \Jef\Url::redirect('/admin/tournament?id=' . $id);
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

    $_SESSION['flash_success'] = 'Tournoi mis à jour.';
    \Jef\Url::redirect('/admin');
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/tournament.html.php', [
    'pageTitle' => 'Modifier le tournoi - Administration JEF',
    'tournament' => $tournament,
    'csrfToken' => $csrfToken,
], 'admin/layout.php');
