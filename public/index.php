<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/') ?: '/';

match ($path) {
    '/'               => require __DIR__ . '/../pages/rankings.php',
    '/etapes'         => require __DIR__ . '/../pages/stages.php',
    '/tournoi'        => require __DIR__ . '/../pages/tournament.php',
    '/admin/login'    => require __DIR__ . '/../pages/admin/login.php',
    '/admin'          => require __DIR__ . '/../pages/admin/dashboard.php',
    '/admin/import'   => require __DIR__ . '/../pages/admin/import.php',
    '/admin/tournament' => require __DIR__ . '/../pages/admin/tournament.php',
    '/admin/settings' => require __DIR__ . '/../pages/admin/settings.php',
    '/admin/logout'   => require __DIR__ . '/../pages/admin/logout.php',
    default           => require __DIR__ . '/../pages/404.php',
};
