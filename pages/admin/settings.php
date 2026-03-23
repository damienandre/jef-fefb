<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

Auth::requireAuth();

$db = Database::get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Session invalide. Veuillez reessayer.';
        header('Location: /admin/settings');
        exit;
    }

    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/jpeg', 'image/svg+xml'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['logo']['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            $_SESSION['flash_error'] = 'Format de fichier non accepte. Utilisez PNG, JPG ou SVG.';
            header('Location: /admin/settings');
            exit;
        }

        $maxSize = 2 * 1024 * 1024; // 2MB
        if ($_FILES['logo']['size'] > $maxSize) {
            $_SESSION['flash_error'] = 'Le fichier est trop volumineux (max 2 Mo).';
            header('Location: /admin/settings');
            exit;
        }

        $ext = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
        };
        $filename = 'logo.' . $ext;
        $uploadDir = __DIR__ . '/../../public/uploads/';
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination)) {
            $stmt = $db->prepare(
                "INSERT INTO jef_settings (`key`, `value`) VALUES ('logo_path', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
            );
            $stmt->execute([$filename]);
            $_SESSION['flash_success'] = 'Logo mis a jour avec succes.';
        } else {
            $_SESSION['flash_error'] = 'Erreur lors de l\'enregistrement du fichier.';
        }
    }

    header('Location: /admin/settings');
    exit;
}

$logoStmt = $db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
$logoStmt->execute(['logo_path']);
$currentLogo = $logoStmt->fetchColumn();

$csrfToken = Auth::generateCsrfToken();

View::render('admin/settings.html.php', [
    'pageTitle' => 'Parametres - Administration JEF',
    'csrfToken' => $csrfToken,
    'currentLogo' => $currentLogo,
], 'admin/layout.php');
