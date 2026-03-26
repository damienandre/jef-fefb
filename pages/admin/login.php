<?php

declare(strict_types=1);

use Jef\Auth;
use Jef\Database;
use Jef\View;

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session invalide. Veuillez réessayer.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (Auth::login(Database::get(), $username, $password)) {
            header('Location: /admin');
            exit;
        }
        $error = 'Identifiant ou mot de passe incorrect.';
    }
}

$csrfToken = Auth::generateCsrfToken();

View::render('admin/login.html.php', [
    'pageTitle' => 'Connexion - Administration JEF',
    'error' => $error,
    'csrfToken' => $csrfToken,
], 'layout.php');
