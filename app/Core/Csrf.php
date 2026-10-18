<?php
declare(strict_types=1);

namespace App\Core;

/** Bảo vệ cơ bản cho các form/endpoint POST bằng token CSRF theo phiên. */
final class Csrf
{
    public const KEY = '_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="' . self::KEY . '" value="' . self::token() . '">';
    }

    public static function check(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION[self::KEY])
            && hash_equals((string) $_SESSION[self::KEY], $token);
    }
}
