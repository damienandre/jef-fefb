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
        \Jef\Url::redirect('/admin/add-stage');
    }

    $seasonYear = (int) ($_POST['season_year'] ?? date('Y'));
    $sortOrder = (int) ($_POST['sort_order'] ?? 1);
    $name = trim($_POST['name'] ?? '');
    $dateStart = trim($_POST['date_start'] ?? '');
    $dateEnd = trim($_POST['date_end'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $infoUrl = trim($_POST['info_url'] ?? '');
    $registrationUrl = trim($_POST['registration_url'] ?? '');

    $_SESSION['old_input'] = $_POST;

    // Validation
    if ($name === '') {
        $_SESSION['flash_error'] = 'Le nom de l\'étape ne peut pas être vide.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if (mb_strlen($name) > 200) {
        $_SESSION['flash_error'] = 'Le nom de l\'étape ne peut pas dépasser 200 caractères.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    $parsedDateStart = $dateStart !== '' ? \DateTime::createFromFormat('Y-m-d', $dateStart) : false;
    if (!$parsedDateStart || $parsedDateStart->format('Y-m-d') !== $dateStart) {
        $_SESSION['flash_error'] = 'La date de début est obligatoire et doit être une date valide.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if ($dateEnd !== '') {
        $parsedDateEnd = \DateTime::createFromFormat('Y-m-d', $dateEnd);
        if (!$parsedDateEnd || $parsedDateEnd->format('Y-m-d') !== $dateEnd) {
            $_SESSION['flash_error'] = 'La date de fin doit être une date valide.';
            \Jef\Url::redirect('/admin/add-stage');
        }

        if ($dateEnd < $dateStart) {
            $_SESSION['flash_error'] = 'La date de fin ne peut pas être antérieure à la date de début.';
            \Jef\Url::redirect('/admin/add-stage');
        }
    }

    if ($seasonYear < 2000 || $seasonYear > 2100) {
        $_SESSION['flash_error'] = 'L\'année de la saison doit être entre 2000 et 2100.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if ($sortOrder < 1 || $sortOrder > 20) {
        $_SESSION['flash_error'] = 'Le numéro de l\'étape doit être entre 1 et 20.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if (mb_strlen($location) > 200 || mb_strlen($organizer) > 200) {
        $_SESSION['flash_error'] = 'Le lieu et l\'organisateur ne peuvent pas dépasser 200 caractères.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if (mb_strlen($address) > 500 || mb_strlen($infoUrl) > 500 || mb_strlen($registrationUrl) > 500) {
        $_SESSION['flash_error'] = 'L\'adresse et les URLs ne peuvent pas dépasser 500 caractères.';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if ($infoUrl !== '' && (!filter_var($infoUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $infoUrl))) {
        $_SESSION['flash_error'] = 'L\'URL d\'information n\'est pas valide (doit commencer par http:// ou https://).';
        \Jef\Url::redirect('/admin/add-stage');
    }

    if ($registrationUrl !== '' && (!filter_var($registrationUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $registrationUrl))) {
        $_SESSION['flash_error'] = 'L\'URL d\'inscription n\'est pas valide (doit commencer par http:// ou https://).';
        \Jef\Url::redirect('/admin/add-stage');
    }

    try {
        $db->beginTransaction();

        // Get or create season
        $stmt = $db->prepare("SELECT id FROM jef_seasons WHERE year = ?");
        $stmt->execute([$seasonYear]);
        $seasonId = $stmt->fetchColumn();

        if (!$seasonId) {
            $db->prepare("INSERT INTO jef_seasons (year) VALUES (?)")->execute([$seasonYear]);
            $seasonId = (int) $db->lastInsertId();
        }

        $insert = $db->prepare(
            "INSERT INTO jef_tournaments
             (season_id, name, location, organizer, address, info_url, registration_url,
              date_start, date_end, round_count, player_count, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)"
        );
        $insert->execute([
            $seasonId,
            $name,
            $location === '' ? null : $location,
            $organizer === '' ? null : $organizer,
            $address === '' ? null : $address,
            $infoUrl === '' ? null : $infoUrl,
            $registrationUrl === '' ? null : $registrationUrl,
            $dateStart,
            $dateEnd === '' ? null : $dateEnd,
            $sortOrder,
        ]);

        $db->commit();

        unset($_SESSION['old_input']);
        $_SESSION['flash_success'] = sprintf('Étape « %s » ajoutée avec succès.', $name);
        \Jef\Url::redirect('/admin');
    } catch (\PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if (str_contains($e->getMessage(), 'uk_season_order') || $e->getCode() === '23000') {
            $_SESSION['flash_error'] = sprintf(
                'Une étape avec le numéro %d existe déjà pour la saison %d.',
                $sortOrder,
                $seasonYear
            );
        } else {
            $_SESSION['flash_error'] = 'Erreur lors de l\'ajout de l\'étape.';
        }
        \Jef\Url::redirect('/admin/add-stage');
    }
}

$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);

$csrfToken = Auth::generateCsrfToken();

View::render('admin/add-stage.html.php', [
    'pageTitle' => 'Ajouter une étape - Administration JEF',
    'csrfToken' => $csrfToken,
    'currentYear' => (int) date('Y'),
    'old' => $oldInput,
], 'admin/layout.php');
