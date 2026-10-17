<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private const ROOT = __DIR__ . '/../Views/';

    /** Renderiza una vista dentro de un layout ('app', 'auth' o null para fragmentos). */
    public static function render(string $view, array $data = [], ?string $layout = 'app'): void
    {
        $data['errors'] ??= Flash::errors();
        $content = self::capture($view, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        $title = $data['title'] ?? '';
        $flash = Flash::pull();
        extract($data, EXTR_SKIP);
        require self::ROOT . 'layouts/' . $layout . '.php';
    }

    public static function capture(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require self::ROOT . $view . '.php';
        return (string) ob_get_clean();
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require self::ROOT . $view . '.php';
    }
}
