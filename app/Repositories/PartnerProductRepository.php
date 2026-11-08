<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

final class PartnerProductRepository extends BaseRepository
{
    public function upsert(string $source, array $item): string
    {
        return $this->callMa(RoutineName::DOI_TAC_UPSERT_SAN_PHAM, [
            $source,
            $item['ma_ngoai'],
            $item['ten'],
            $item['mo_ta'],
            $item['gia_goc'],
            $item['gia_hien_tai'],
            $item['anh_url'],
            $item['lien_ket_mua'],
            $item['lien_ket_goc'],
            $item['danh_muc_goc'],
            $item['sku'],
            $item['ten_mien'],
            $item['chien_dich'],
            $item['ngay_nguon'],
        ]);
    }

    public function hideOlder(string $source, string $cutoff): int
    {
        $rows = $this->call(RoutineName::DOI_TAC_AN_SAN_PHAM_CU, [$source, $cutoff]);
        return (int) ($rows[0]['so_dong_an'] ?? 0);
    }

    public function getProducts(string $domain, string $sort, int $page, int $perPage): array
    {
        $allowedSorts = ['moi_nhat', 'gia_tang', 'gia_giam'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'moi_nhat';
        }
        $page = max(1, $page);
        $perPage = min(48, max(1, $perPage));
        $rows = $this->call(RoutineName::DS_SAN_PHAM_DOI_TAC, [$domain, $sort, $page, $perPage]);
        return [
            'items' => $rows,
            'tong' => $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows)),
        ];
    }

    public function getDomains(): array
    {
        return $this->call(RoutineName::DS_DOI_TAC_TEN_MIEN);
    }
}
