<?php
declare(strict_types=1);

namespace App\Core;

/** Kết xuất view + layout, dữ liệu được escape qua hàm h() ở view. */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], string $layout = 'layout/main'): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = APP_ROOT . '/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('Thiếu view: ' . $template);
        }
        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        $layoutFile = APP_ROOT . '/views/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }
        require $layoutFile;
    }
}
