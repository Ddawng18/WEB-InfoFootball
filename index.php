<?php
declare(strict_types=1);

/**
 * Front controller cho các trang HTML.
 * Định tuyến đơn giản qua query ?r=... (ví dụ index.php?r=tran&id=1).
 */

require __DIR__ . '/app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\PageController;
use App\Core\Config;
use App\Core\View;

$route = isset($_GET['r']) && is_string($_GET['r']) ? $_GET['r'] : 'home';

try {
    if (str_starts_with($route, 'admin')) {
        (new AdminController())->dispatch($route);
    } else {
        (new PageController())->dispatch($route);
    }
} catch (\Throwable $e) {
    http_response_code(500);
    $chiTiet = Config::string('app', 'debug', '0') === '1' ? (string) $e : '';
    try {
        View::render('error', [
            'title' => 'Lỗi hệ thống',
            'thong_bao' => 'Đã xảy ra lỗi khi xử lý yêu cầu.',
            'chi_tiet' => $chiTiet,
            'user' => null,
        ]);
    } catch (\Throwable $e2) {
        echo 'Lỗi hệ thống.';
    }
}
