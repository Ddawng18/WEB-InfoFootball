<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

/** Truy cập dữ liệu đội bóng yêu thích (S1). */
final class FavoriteRepository extends BaseRepository
{
    public function dat(int $taiKhoanId, int $doiBongId, bool $bat): string
    {
        return $this->callMa(RoutineName::DAT_YEU_THICH_DOI_BONG, [$taiKhoanId, $doiBongId, $bat ? 1 : 0]);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function danhSach(int $taiKhoanId, int $gioiHan = 100, int $boQua = 0): array
    {
        $rows = $this->call(RoutineName::DS_DOI_YEU_THICH, [$taiKhoanId, $gioiHan, $boQua]);
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $rows, 'tong' => $tong];
    }

    public function kiemTra(int $taiKhoanId, int $doiBongId): bool
    {
        $rows = $this->call(RoutineName::KIEM_TRA_YEU_THICH, [$taiKhoanId, $doiBongId]);
        return (int) ($rows[0]['yeu_thich'] ?? 0) === 1;
    }
}
