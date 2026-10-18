<?php
declare(strict_types=1);

/**
 * Bootstrap dùng chung cho mọi entry point (index.php, api/*.php, cron).
 * - Đăng ký autoload cho namespace App\*
 * - Nạp cấu hình
 * - Khởi động session
 */

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = APP_ROOT . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once APP_ROOT . '/app/Core/Config.php';
require_once APP_ROOT . '/app/helpers.php';

App\Core\Config::load(APP_ROOT . '/config.ini');

date_default_timezone_set('UTC');

if (session_status() === PHP_SESSION_NONE) {
    // Bảo mật cơ bản cho cookie phiên
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
