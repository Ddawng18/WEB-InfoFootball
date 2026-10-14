<?php
declare(strict_types=1);

namespace App\Core;

/** Chuyển đổi/múi giờ: DB lưu UTC, hiển thị theo múi giờ người dùng. */
final class Time
{
    public static function tz(): string
    {
        return Config::string('app', 'display_timezone', 'Asia/Ho_Chi_Minh');
    }

    /** 'Y-m-d H:i:s' UTC -> 'Y-m-d H:i:s' theo múi giờ hiển thị. */
    public static function toDisplay(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        try {
            $d = new \DateTime($utc, new \DateTimeZone('UTC'));
            $d->setTimezone(new \DateTimeZone(self::tz()));
            return $d->format('Y-m-d H:i');
        } catch (\Exception $e) {
            return (string) $utc;
        }
    }

    /** Nhận ngày 'Y-m-d' theo giờ VN, trả về khoảng [từ, đến) dạng UTC 'Y-m-d H:i:s'. */
    public static function ngaySangKhoangUtc(string $ymd): array
    {
        $tz = new \DateTimeZone(self::tz());
        $tu = new \DateTime($ymd . ' 00:00:00', $tz);
        $den = new \DateTime($ymd . ' 00:00:00', $tz);
        $den->modify('+1 day');
        $tu->setTimezone(new \DateTimeZone('UTC'));
        $den->setTimezone(new \DateTimeZone('UTC'));
        return [$tu->format('Y-m-d H:i:s'), $den->format('Y-m-d H:i:s')];
    }

    public static function nowUtc(): string
    {
        return (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }
}
