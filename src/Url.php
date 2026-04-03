<?php

declare(strict_types=1);

namespace Jef;

final class Url
{
    private static ?string $basePath = null;

    public static function basePath(): string
    {
        if (self::$basePath === null) {
            $config = require __DIR__ . '/../config.php';
            self::$basePath = rtrim($config['base_path'] ?? '', '/');
        }
        return self::$basePath;
    }

    public static function path(string $path = '/'): string
    {
        assert($path !== '' && $path[0] === '/', 'Path must start with /');
        return self::basePath() . $path;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . self::path($path));
        exit;
    }
}
