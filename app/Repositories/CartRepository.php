<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

final class CartRepository extends BaseRepository
{
    public function add(int $accountId, int $productId, int $quantity): string
    {
        return $this->callMa(RoutineName::GIO_HANG_THEM, [$accountId, $productId, $quantity]);
    }

    public function getItems(int $accountId): array
    {
        return $this->call(RoutineName::GIO_HANG_DOC, [$accountId]);
    }

    public function update(int $accountId, int $productId, int $quantity): string
    {
        return $this->callMa(RoutineName::GIO_HANG_CAP_NHAT, [$accountId, $productId, $quantity]);
    }

    public function delete(int $accountId, int $productId): string
    {
        return $this->callMa(RoutineName::GIO_HANG_XOA, [$accountId, $productId]);
    }

    public function clear(int $accountId): string
    {
        return $this->callMa(RoutineName::GIO_HANG_XOA_HET, [$accountId]);
    }

    public function countAvailable(int $accountId): int
    {
        $rows = $this->call(RoutineName::GIO_HANG_DEM, [$accountId]);
        return (int) ($rows[0]['tong_so_luong'] ?? 0);
    }
}
