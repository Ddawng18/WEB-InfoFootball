<?php
declare(strict_types=1);

/**
 * Router cho PHP built-in server (php -S ... router.php).
 * Mục đích: built-in server không đọc .htaccess, nên router chặn các thư mục
 * không được phục vụ trực tiếp (app, database, config, tools, views)
 * và chỉ cho phép tài nguyên tĩnh trong public/, entry point index.php và api/*.php.
 *
 * Chạy: php -S 127.0.0.1:8099 router.php
 */

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';
if ($uri === '') {
    $uri = '/';
}

$blockedDir = '#^/(?:app|database|tools|config|views)(?:/|$)#i';
$blockedFile = '#^/(?:config\.ini|config\.example\.ini|README\.md|\.gitignore|\.git)(?:/|$)#i';
if (preg_match($blockedDir, $uri) === 1
    || preg_match($blockedFile, $uri) === 1
    || str_contains($uri, '..')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    return true;
}

$root = __DIR__;
$file = $root . str_replace('/', DIRECTORY_SEPARATOR, $uri);

if (str_starts_with($uri, '/public/') && is_file($file)) {
    return false; // để built-in server phục vụ file tĩnh
}

if ($uri === '/' || $uri === '/index.php') {
    require $root . '/index.php';
    return true;
}

if (preg_match('#^/api/[A-Za-z0-9_./-]+\.php$#', $uri) === 1 && is_file($file)) {
    require $file;
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo '404 Not Found';
return true;
