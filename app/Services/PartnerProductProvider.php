<?php
declare(strict_types=1);

namespace App\Services;

interface PartnerProductProvider
{
    public function fetch(int $trang, int $gioiHan, string $tenMien): array;

    public function name(): string;
}
