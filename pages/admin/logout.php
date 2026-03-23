<?php

declare(strict_types=1);

use Jef\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/login');
    exit;
}

if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: /admin/login');
    exit;
}

Auth::logout();
header('Location: /admin/login');
exit;
