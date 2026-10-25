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

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    // Bảo mật cookie phiên: HttpOnly + SameSite + Secure khi chạy HTTPS.
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_httponly', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
