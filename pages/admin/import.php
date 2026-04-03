<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\ImportService;
use Jef\View;

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez réessayer.';
        \Jef\Url::redirect('/admin/import');
    }

    $seasonYear = (int) ($_POST['season_year'] ?? date('Y'));
    $sortOrder = (int) ($_POST['sort_order'] ?? 1);

    if (empty($_FILES['trf_file']['tmp_name']) || $_FILES['trf_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = 'Veuillez sélectionner un fichier TRF valide.';
        \Jef\Url::redirect('/admin/import');
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['trf_file']['size'] > $maxSize) {
        $_SESSION['flash_error'] = 'Le fichier est trop volumineux (max 5 Mo).';
        \Jef\Url::redirect('/admin/import');
    }

    $trfContent = file_get_contents($_FILES['trf_file']['tmp_name']);

    try {
        $result = ImportService::import(Database::get(), $seasonYear, $sortOrder, $trfContent);
        $_SESSION['flash_success'] = sprintf(
            'Tournoi "%s" importé avec succès (%d joueurs). Classements recalculés.',
            $result['tournament_name'],
            $result['player_count']
        );
        \Jef\Url::redirect('/admin');
    } catch (\InvalidArgumentException $e) {
        $_SESSION['flash_error'] = 'Erreur de format TRF : ' . $e->getMessage();
        \Jef\Url::redirect('/admin/import');
    } catch (\Throwable) {
        $_SESSION['flash_error'] = 'Erreur interne lors de l\'import.';
        \Jef\Url::redirect('/admin/import');
    }
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/import.html.php', [
    'pageTitle' => 'Importer TRF - Administration JEF',
    'csrfToken' => $csrfToken,
    'currentYear' => (int) date('Y'),
], 'admin/layout.php');
