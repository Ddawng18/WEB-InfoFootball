<?php
declare(strict_types=1);

namespace App\Services;

interface FootballProductProvider
{
    /**
     * @param int $limit Số sản phẩm tối đa cần lấy.
     * @param float $deadline Mốc microtime() phải dừng lại.
     * @return array<int, array<string, mixed>> Danh sách bản ghi đã chuẩn hóa.
     */
    public function fetch(int $limit, float $deadline): array;

    public function name(): string;
}
