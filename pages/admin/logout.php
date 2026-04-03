<?php

declare(strict_types=1);

use Jef\Auth;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    \Jef\Url::redirect('/admin/login');
}

if (!Auth::validateCsrfToken($_POST['csrf_token'] ?? '')) {
    \Jef\Url::redirect('/admin/login');
}

Auth::logout();
\Jef\Url::redirect('/admin/login');
