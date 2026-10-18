<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Đọc cấu hình từ config.ini (key/secret, DB, TTL...).
 * Không commit config.ini; chỉ commit config.example.ini.
 */
final class Config
{
    /** @var array<string, array<string, mixed>> */
    private static array $data = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException(
                'Thiếu file cấu hình: ' . $path .
                '. Hãy sao chép config.example.ini thành config.ini rồi điền key.'
            );
        }
        $parsed = parse_ini_file($path, true, INI_SCANNER_TYPED);
        self::$data = is_array($parsed) ? $parsed : [];
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $section, string $key, $default = null)
    {
        return self::$data[$section][$key] ?? $default;
    }

    public static function int(string $section, string $key, int $default): int
    {
        $value = self::get($section, $key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function string(string $section, string $key, string $default = ''): string
    {
        $value = self::get($section, $key, $default);
        return $value === null ? $default : (string) $value;
    }

    /** @return array<string, mixed> */
    public static function section(string $section): array
    {
        return self::$data[$section] ?? [];
    }
}
