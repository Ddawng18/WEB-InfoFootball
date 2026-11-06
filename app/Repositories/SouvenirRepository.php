<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

final class SouvenirRepository extends BaseRepository
{
    public function getCategories(): array
    {
        return $this->call(RoutineName::DS_DANH_MUC_LUU_NIEM);
    }

    public function getProducts(int $categoryId, string $sort, int $page, int $perPage): array
    {
        $allowedSorts = ['moi_nhat', 'gia_tang', 'gia_giam'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'moi_nhat';
        }

        $page = max(1, $page);
        $perPage = min(48, max(1, $perPage));
        $rows = $this->call(RoutineName::DS_SAN_PHAM_LUU_NIEM, [
            $categoryId,
            $sort,
            $page,
            $perPage,
        ]);

        return [
            'items' => $rows,
            'tong' => $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows)),
        ];
    }

    public function getAdminProducts(int $categoryId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(1, $perPage));
        $rows = $this->call(RoutineName::ADMIN_DS_SAN_PHAM_LUU_NIEM, [
            $categoryId,
            $page,
            $perPage,
        ]);

        return [
            'items' => $rows,
            'tong' => $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows)),
        ];
    }

    public function getProductById(int $id): ?array
    {
        $rows = $this->call(RoutineName::ADMIN_CHI_TIET_SAN_PHAM_LUU_NIEM, [$id]);
        return $rows[0] ?? null;
    }

    public function addProduct(
        int $categoryId,
        string $name,
        string $description,
        int $price,
        string $imageUrl,
        int $inStock,
        int $visible,
        int $accountId
    ): void {
        $this->call(RoutineName::ADMIN_THEM_SAN_PHAM_LUU_NIEM, [
            $categoryId, $name, $description, $price, $imageUrl, $inStock, $visible, $accountId,
        ]);
    }

    public function updateProduct(
        int $id,
        int $categoryId,
        string $name,
        string $description,
        int $price,
        string $imageUrl,
        int $inStock,
        int $visible,
        int $accountId
    ): void {
        $this->call(RoutineName::ADMIN_SUA_SAN_PHAM_LUU_NIEM, [
            $id, $categoryId, $name, $description, $price, $imageUrl, $inStock, $visible, $accountId,
        ]);
    }

    public function changePrice(int $id, int $price, int $accountId): void
    {
        $this->call(RoutineName::ADMIN_DOI_GIA_LUU_NIEM, [$id, $price, $accountId]);
    }

    public function deleteProduct(int $id, int $accountId): ?array
    {
        $rows = $this->call(RoutineName::ADMIN_XOA_SAN_PHAM_LUU_NIEM, [$id, $accountId]);
        return $rows[0] ?? null;
    }

    public function getPriceHistory(int $productId): array
    {
        return $this->call(RoutineName::DS_LICH_SU_GIA_LUU_NIEM, [$productId]);
    }
}
