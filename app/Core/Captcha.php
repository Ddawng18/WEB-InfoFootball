<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CAPTCHA nhẹ dựa trên session: hỏi một phép cộng ngẫu nhiên.
 * Đủ để chặn đăng ký tự động cơ bản mà không cần GD/ảnh.
 */
final class Captcha
{
    public static function question(): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $_SESSION['ff_captcha'] = (string) ($a + $b);
        return $a . ' + ' . $b . ' = ?';
    }

    /** @param mixed $value */
    public static function check($value): bool
    {
        $expected = isset($_SESSION['ff_captcha']) ? (string) $_SESSION['ff_captcha'] : null;
        unset($_SESSION['ff_captcha']);
        if ($expected === null || !is_string($value)) {
            return false;
        }
        return hash_equals($expected, trim($value));
    }
}
