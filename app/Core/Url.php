<?php
declare(strict_types=1);

namespace App\Core;

/** Tính URL gốc của ứng dụng (chạy được cả trên htdocs lẫn PHP built-in server). */
final class Url
{
    private static ?string $base = null;

    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $cauHinh = Config::string('app', 'base_url', '');
        if ($cauHinh !== '') {
            return self::$base = rtrim($cauHinh, '/');
        }
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
        $appRoot = realpath(APP_ROOT);
        if ($docRoot && $appRoot && str_starts_with(str_replace('\\', '/', $appRoot), str_replace('\\', '/', $docRoot))) {
            $sub = substr(str_replace('\\', '/', $appRoot), strlen(str_replace('\\', '/', $docRoot)));
            return self::$base = rtrim($sub, '/');
        }
        return self::$base = '';
    }

    /** @param array<string, scalar|null> $params */
    public static function to(string $path, array $params = []): string
    {
        $url = self::base() . '/' . ltrim($path, '/');
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}
