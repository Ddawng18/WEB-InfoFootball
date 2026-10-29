<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CalendarRepository;

/** Lịch cá nhân, nhắc trận và thông báo trong web của từng tài khoản. */
final class CalendarService
{
    private CalendarRepository $repo;

    public function __construct(?CalendarRepository $repo = null)
    {
        $this->repo = $repo ?? new CalendarRepository();
    }

    /** @return array{ma: string, thong_bao: string, trong_lich: bool, nhac: bool} */
    public function dat(int $taiKhoanId, int $tranDauId, bool $trongLich, bool $nhac): array
    {
        $truoc = $this->repo->kiemTra($taiKhoanId, $tranDauId);
        $kq = $this->repo->dat($taiKhoanId, $tranDauId, $trongLich, $nhac);
        $ma = (string) ($kq['ma'] ?? 'LOI');
        if ($ma === 'OK') {
            if ($truoc['nhac'] !== $nhac) {
                $thongBao = $nhac ? 'Đã bật nhắc trận.' : 'Đã tắt nhắc trận.';
            } elseif ($truoc['trong_lich'] !== $trongLich) {
                $thongBao = $trongLich ? 'Đã thêm vào lịch.' : 'Đã bỏ khỏi lịch.';
            } else {
                $thongBao = 'Đã cập nhật lịch.';
            }
        } else {
            $thongBao = match ($ma) {
                'KHONG_TON_TAI' => 'Không tìm thấy trận đấu.',
                'CHUA_DIEN_RA' => 'Chỉ có thể thêm lịch hoặc nhắc cho trận sắp diễn ra.',
                default => 'Không thể cập nhật lịch, vui lòng thử lại.',
            };
        }
        return [
            'ma' => $ma,
            'thong_bao' => $thongBao,
            'trong_lich' => (bool) ($kq['trong_lich'] ?? 0),
            'nhac' => (bool) ($kq['nhac'] ?? 0),
        ];
    }

    /** @return array{trong_lich: bool, nhac: bool, da_nhac: bool} */
    public function kiemTra(int $taiKhoanId, int $tranDauId): array
    {
        return $this->repo->kiemTra($taiKhoanId, $tranDauId);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function danhSach(int $taiKhoanId, string $loc, int $gioiHan, int $boQua): array
    {
        return $this->repo->danhSach($taiKhoanId, $loc, $gioiHan, $boQua);
    }

    /**
     * @param array<int, int|string> $tranDauIds
     * @return array<int, array{trong_lich: bool, nhac: bool}>
     */
    public function trangThaiNhieu(int $taiKhoanId, array $tranDauIds): array
    {
        return $this->repo->trangThaiNhieu($taiKhoanId, $tranDauIds);
    }

    /** @return array<int, array<string, mixed>> */
    public function doiDaThem(int $taiKhoanId, int $gioiHan = 24): array
    {
        return $this->repo->doiDaThem($taiKhoanId, $gioiHan);
    }

    /** Tạo thông báo cho các trận đã tới giờ bóng lăn (chạy khi có request). */
    public function dongBoNhac(int $taiKhoanId): void
    {
        if ($taiKhoanId > 0) {
            $this->repo->taoNhacDenHan(gmdate('Y-m-d H:i:s'));
        }
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int, chua_doc: int} */
    public function thongBao(int $taiKhoanId, int $gioiHan = 30, int $boQua = 0): array
    {
        $this->dongBoNhac($taiKhoanId);
        $res = $this->repo->thongBao($taiKhoanId, $gioiHan, $boQua);
        $res['chua_doc'] = $this->repo->demChuaDoc($taiKhoanId);
        return $res;
    }

    public function danhDauDaDoc(int $taiKhoanId, ?int $thongBaoId = null): int
    {
        return $this->repo->danhDauDaDoc($taiKhoanId, $thongBaoId);
    }

    public function demChuaDoc(int $taiKhoanId): int
    {
        return $this->repo->demChuaDoc($taiKhoanId);
    }
}
