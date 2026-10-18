<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\FavoriteRepository;

/** Nghiệp vụ đội bóng yêu thích (S1). */
final class FavoriteService
{
    private FavoriteRepository $repo;

    public function __construct(?FavoriteRepository $repo = null)
    {
        $this->repo = $repo ?? new FavoriteRepository();
    }

    /** @return array{ma:string, thong_bao:string} */
    public function dat(int $taiKhoanId, int $doiBongId, bool $bat): array
    {
        $ma = $this->repo->dat($taiKhoanId, $doiBongId, $bat);
        $thongBao = match ($ma) {
            'OK' => $bat ? 'Đã thêm vào yêu thích.' : 'Đã bỏ yêu thích.',
            'KHONG_TON_TAI' => 'Đội bóng không tồn tại.',
            default => 'Không thể cập nhật, vui lòng thử lại.',
        };
        return ['ma' => $ma, 'thong_bao' => $thongBao];
    }

    public function laYeuThich(int $taiKhoanId, int $doiBongId): bool
    {
        return $this->repo->kiemTra($taiKhoanId, $doiBongId);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function danhSach(int $taiKhoanId, int $gioiHan = 100, int $boQua = 0): array
    {
        return $this->repo->danhSach($taiKhoanId, $gioiHan, $boQua);
    }
}
