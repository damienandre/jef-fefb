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
        header('Location: /admin/settings');
        exit;
    }

    // Save FEFB URL
    $fefbUrl = trim($_POST['fefb_url'] ?? '');
    $stmt = $db->prepare(
        "INSERT INTO jef_settings (`key`, `value`) VALUES ('fefb_url', ?)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $stmt->execute([$fefbUrl]);

    if (!empty($_FILES['logo']['tmp_name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/png', 'image/jpeg'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['logo']['tmp_name']);

        if (!in_array($mimeType, $allowedTypes, true)) {
            $_SESSION['flash_error'] = 'Format de fichier non accepté. Utilisez PNG ou JPG.';
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
            $_SESSION['flash_success'] = 'Logo mis à jour avec succès.';
        } else {
            $_SESSION['flash_error'] = 'Erreur lors de l\'enregistrement du fichier.';
        }
    }

    if (empty($_SESSION['flash_error']) && empty($_SESSION['flash_success'])) {
        $_SESSION['flash_success'] = 'Paramètres mis à jour avec succès.';
    }

    header('Location: /admin/settings');
    exit;
}

$logoStmt = $db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
$logoStmt->execute(['logo_path']);
$currentLogo = $logoStmt->fetchColumn();

$urlStmt = $db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
$urlStmt->execute(['fefb_url']);
$currentFefbUrl = $urlStmt->fetchColumn() ?: '';

$csrfToken = Auth::generateCsrfToken();

View::render('admin/settings.html.php', [
    'pageTitle' => 'Paramètres - Administration JEF',
    'csrfToken' => $csrfToken,
    'currentLogo' => $currentLogo,
    'currentFefbUrl' => $currentFefbUrl,
], 'admin/layout.php');
