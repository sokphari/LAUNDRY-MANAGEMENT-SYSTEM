<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $root = dirname(__DIR__, 2) . '/resources/views/';
        $viewPath = $root . $view . '.php';
        $layoutPath = $root . 'layouts/' . $layout . '.php';

        if (!is_file($viewPath) || !is_file($layoutPath)) {
            throw new \RuntimeException('View template not found.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;
        $content = (string) ob_get_clean();
        require $layoutPath;
    }
}
