<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\ImportService;
use Jef\View;

Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez reessayer.';
        header('Location: /admin/import');
        exit;
    }

    $seasonYear = (int) ($_POST['season_year'] ?? date('Y'));
    $sortOrder = (int) ($_POST['sort_order'] ?? 1);

    if (empty($_FILES['trf_file']['tmp_name']) || $_FILES['trf_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_error'] = 'Veuillez selectionner un fichier TRF valide.';
        header('Location: /admin/import');
        exit;
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['trf_file']['size'] > $maxSize) {
        $_SESSION['flash_error'] = 'Le fichier est trop volumineux (max 5 Mo).';
        header('Location: /admin/import');
        exit;
    }

    $trfContent = file_get_contents($_FILES['trf_file']['tmp_name']);

    try {
        $result = ImportService::import(Database::get(), $seasonYear, $sortOrder, $trfContent);
        $_SESSION['flash_success'] = sprintf(
            'Tournoi "%s" importe avec succes (%d joueurs). Classements recalcules.',
            $result['tournament_name'],
            $result['player_count']
        );
        header('Location: /admin');
        exit;
    } catch (\InvalidArgumentException $e) {
        $_SESSION['flash_error'] = 'Erreur de format TRF : ' . $e->getMessage();
        header('Location: /admin/import');
        exit;
    } catch (\Throwable) {
        $_SESSION['flash_error'] = 'Erreur interne lors de l\'import.';
        header('Location: /admin/import');
        exit;
    }
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/import.html.php', [
    'pageTitle' => 'Importer TRF - Administration JEF',
    'csrfToken' => $csrfToken,
    'currentYear' => (int) date('Y'),
], 'admin/layout.php');
