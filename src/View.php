<?php

declare(strict_types=1);

namespace Jef;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): void
    {
        if ($layout !== null) {
            $config = require __DIR__ . '/../config.php';
            $data['baseUrl'] = rtrim($config['base_url'], '/');

            $db = Database::get();
            $stmt = $db->prepare("SELECT `value` FROM jef_settings WHERE `key` = ?");
            $stmt->execute(['logo_path']);
            $data['logoPath'] = $stmt->fetchColumn() ?: null;
        }

        extract($data, EXTR_SKIP);

        if ($layout !== null) {
            ob_start();
            require __DIR__ . '/../templates/' . $template;
            $content = ob_get_clean();
            require __DIR__ . '/../templates/' . $layout;
        } else {
            require __DIR__ . '/../templates/' . $template;
        }
    }
}
