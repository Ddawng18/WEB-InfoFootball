<?php
declare(strict_types=1);

namespace App\Core;

/** Kiểm tra dữ liệu đầu vào ở tầng PHP (định dạng, không phải nghiệp vụ CSDL). */
final class Validator
{
    public static function ngayYmd(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        if ($d === false || $d->format('Y-m-d') !== $value) {
            return null;
        }
        return $value;
    }

    public static function soNguyenDuong($value, int $min = 1): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $n = (int) $value;
        return $n >= $min ? $n : null;
    }

    public static function soNguyenKhongAm($value, int $default = 0): int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : $default;
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function doDai(string $value, int $min, int $max): bool
    {
        $len = mb_strlen($value, 'UTF-8');
        return $len >= $min && $len <= $max;
    }

    public static function tenDangNhap(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]{4,50}$/', $value);
    }

    public static function chuoi(?string $value, int $max = 255): string
    {
        $value = trim((string) $value);
        return mb_substr($value, 0, $max, 'UTF-8');
    }

    public static function maTrangThai(?string $value): ?string
    {
        $hopLe = ['SAP_DIEN_RA','DANG_DA','NGHI_GIUA_HIEP','KET_THUC','HOAN','HUY'];
        return $value !== null && in_array($value, $hopLe, true) ? $value : null;
    }
}
