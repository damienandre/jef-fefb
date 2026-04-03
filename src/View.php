<?php

declare(strict_types=1);

namespace Jef;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): void
    {
        if ($layout !== null) {
            $data['basePath'] = Url::basePath();

            $db = Database::get();
            $stmt = $db->prepare("SELECT `key`, `value` FROM jef_settings WHERE `key` IN ('logo_path', 'fefb_url')");
            $stmt->execute();
            $settings = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $data['logoPath'] = $settings['logo_path'] ?? null;
            $data['fefbUrl'] = $settings['fefb_url'] ?? null;
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
