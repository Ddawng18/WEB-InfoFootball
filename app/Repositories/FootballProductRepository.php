<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

/** Truy cập dữ liệu sản phẩm bóng đá (thu thập từ sàn thương mại điện tử). */
final class FootballProductRepository extends BaseRepository
{
    public function upsert(array $item): string
    {
        return $this->callMa(RoutineName::SAN_PHAM_BONG_DA_UPSERT, [
            (string) $item['nguon'],
            (string) $item['ma_ngoai'],
            (string) $item['ten'],
            (string) $item['mo_ta'],
            (string) $item['anh_url'],
            (string) $item['anh_phu'],
            (int) $item['gia_goc'],
            (int) $item['gia_hien_tai'],
            (string) $item['danh_muc_slug'],
            (string) $item['thuong_hieu'],
            (string) ($item['cua_hang'] ?? ''),
            (string) $item['ten_mien'],
            (string) $item['lien_ket_mua'],
            $item['lien_ket_goc'] !== null ? (string) $item['lien_ket_goc'] : null,
            (int) $item['con_hang'],
            $item['ngay_nguon'] !== null ? (string) $item['ngay_nguon'] : null,
        ]);
    }

    public function hideOlder(string $source, string $cutoff): int
    {
        $rows = $this->call(RoutineName::SAN_PHAM_BONG_DA_AN_CU, [$source, $cutoff]);
        return (int) ($rows[0]['so_dong_an'] ?? 0);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function getProducts(string $categorySlug, string $keyword, string $domain, string $sort, int $page, int $perPage): array
    {
        $allowedSorts = ['moi_nhat', 'gia_tang', 'gia_giam', 'giam_gia'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'moi_nhat';
        }
        $page = max(1, $page);
        $perPage = min(48, max(1, $perPage));
        $rows = $this->call(RoutineName::DS_SAN_PHAM_BONG_DA, [
            $categorySlug,
            $keyword,
            $domain,
            $sort,
            $page,
            $perPage,
        ]);
        return [
            'items' => $rows,
            'tong' => $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows)),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getCategories(): array
    {
        return $this->call(RoutineName::DS_DANH_MUC_SAN_PHAM_BONG_DA);
    }

    /** @return array<int, array<string, mixed>> */
    public function getDomains(): array
    {
        return $this->call(RoutineName::DS_TEN_MIEN_SAN_PHAM_BONG_DA);
    }

    /** @return array<int, array<string, mixed>> */
    public function getPrices(string $categorySlug, string $keyword, string $domain): array
    {
        return $this->call(RoutineName::DS_GIA_SAN_PHAM_BONG_DA, [$categorySlug, $keyword, $domain]);
    }
}
