<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SafeUrl;

final class PartnerProductMapper
{
    public static function normalize(array $row, string $fallbackDomain, bool $allowLocalImage): ?array
    {
        $id = trim(self::text($row['product_id'] ?? ''));
        $name = trim(self::text($row['name'] ?? ''));
        $image = trim(self::text($row['image'] ?? ''));
        $buyLink = SafeUrl::https(self::text($row['aff_link'] ?? ''));

        if ($id === '' || $name === '' || $buyLink === null) {
            return null;
        }
        if ($allowLocalImage && preg_match('/^img\/[A-Za-z0-9._\/-]+$/', $image) === 1
            && strpos($image, '..') === false) {
            $safeImage = $image;
        } else {
            $safeImage = SafeUrl::https($image);
        }
        if ($safeImage === null) {
            return null;
        }

        $price = self::price($row['price'] ?? null);
        if ($price === null) {
            return null;
        }
        $discount = self::price($row['discount'] ?? null);
        $currentPrice = $discount !== null && $discount > 0 ? $discount : $price;
        $domain = trim(self::text($row['domain'] ?? ''));
        if (preg_match('/^[a-z0-9.-]{1,100}$/', strtolower($domain)) !== 1) {
            $domain = $fallbackDomain;
        }

        $description = self::text($row['desc'] ?? '');
        $sku = trim(self::text($row['sku'] ?? ''));
        $sourceDate = self::date($row['update_time'] ?? null);

        return [
            'ma_ngoai' => mb_substr($id, 0, 100, 'UTF-8'),
            'ten' => mb_substr($name, 0, 200, 'UTF-8'),
            'mo_ta' => mb_substr((string) $description, 0, 2000, 'UTF-8'),
            'gia_goc' => $price,
            'gia_hien_tai' => $currentPrice,
            'anh_url' => $safeImage,
            'lien_ket_mua' => $buyLink,
            'lien_ket_goc' => SafeUrl::https(self::text($row['url'] ?? '')),
            'danh_muc_goc' => mb_substr(trim(self::text($row['cate'] ?? '')), 0, 100, 'UTF-8'),
            'sku' => $sku === '' ? null : mb_substr($sku, 0, 100, 'UTF-8'),
            'ten_mien' => mb_substr(strtolower($domain), 0, 100, 'UTF-8'),
            'chien_dich' => mb_substr(trim(self::text($row['campaign'] ?? '')), 0, 100, 'UTF-8'),
            'ngay_nguon' => $sourceDate,
        ];
    }

    private static function price($value): ?int
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0
            || (float) $value > 100000000) {
            return null;
        }
        return (int) round((float) $value);
    }

    private static function date($value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $date = \DateTime::createFromFormat('!d-m-Y\TH:i:s', $value, new \DateTimeZone('UTC'));
        $errors = \DateTime::getLastErrors();
        if ($date === false || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('d-m-Y\TH:i:s') !== $value) {
            return null;
        }
        return $date->format('Y-m-d H:i:s');
    }

    private static function text($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
