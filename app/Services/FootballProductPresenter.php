<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\SafeUrl;
use App\Core\Time;

/** Chuẩn bị dữ liệu hiển thị cho một sản phẩm (tách logic khỏi View). */
final class FootballProductPresenter
{
    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function present(array $item): array
    {
        $buyLink = SafeUrl::https((string) ($item['lien_ket_mua'] ?? ''));
        $image = SafeUrl::anh((string) ($item['anh_url'] ?? ''));
        $price = (float) ($item['gia_hien_tai'] ?? 0);
        $original = (float) ($item['gia_goc'] ?? 0);
        $percent = (int) ($item['phan_tram_giam'] ?? 0);
        $saved = (float) ($item['so_tien_giam'] ?? 0);
        $inStock = (int) ($item['con_hang'] ?? 0) === 1;
        $source = strtoupper((string) ($item['ten_mien'] ?? ''));
        $brand = trim((string) ($item['thuong_hieu'] ?? ''));
        $shop = trim((string) ($item['cua_hang'] ?? ''));
        $description = trim((string) ($item['mo_ta'] ?? ''));
        $descriptionShort = mb_substr($description, 0, 220, 'UTF-8');
        $hasMore = mb_strlen($description, 'UTF-8') > 220;
        $updated = Time::toDisplay((string) ($item['lan_kiem_tra'] ?? ''));
        $fallbackImage = asset('img/products/no-image.svg');

        $extraImages = [];
        $decodedImages = json_decode((string) ($item['anh_phu'] ?? ''), true);
        if (is_array($decodedImages)) {
            foreach ($decodedImages as $extra) {
                $safeExtra = SafeUrl::anh((string) $extra);
                if ($safeExtra !== $image
                    && $safeExtra !== $fallbackImage
                    && !in_array($safeExtra, $extraImages, true)) {
                    $extraImages[] = $safeExtra;
                }
                if (count($extraImages) >= 2) {
                    break;
                }
            }
        }

        return [
            'buyLink' => $buyLink,
            'image' => $image,
            'price' => $price,
            'original' => $original,
            'percent' => $percent,
            'saved' => $saved,
            'inStock' => $inStock,
            'source' => $source,
            'brand' => $brand,
            'shop' => $shop,
            'description' => $description,
            'descriptionShort' => $descriptionShort,
            'hasMore' => $hasMore,
            'updated' => $updated,
            'extraImages' => $extraImages,
            'fallbackImage' => $fallbackImage,
        ];
    }
}
