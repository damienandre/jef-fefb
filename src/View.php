<?php

declare(strict_types=1);

namespace Jef;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): void
    {
        extract($data);

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
