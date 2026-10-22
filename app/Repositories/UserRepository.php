<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\RoutineName;

/** Truy cập dữ liệu tài khoản (F7, F8). */
final class UserRepository extends BaseRepository
{
    public function dangKy(string $tenDangNhap, string $email, string $hoTen, string $hash): string
    {
        return $this->callMa(RoutineName::DANG_KY_TAI_KHOAN, [$tenDangNhap, $email, $hoTen, $hash]);
    }

    /**
     * Trả về dòng thông tin đăng nhập (có hash) để Service xác thực bằng password_verify.
     * @return array<string, mixed>|null
     */
    public function timTheoTen(string $tenDangNhap): ?array
    {
        $rows = $this->call(RoutineName::LAY_TAI_KHOAN_DANG_NHAP, [$tenDangNhap]);
        return $rows[0] ?? null;
    }

    public function ghiNhanDangNhap(int $taiKhoanId, string $ketQua, string $ip): string
    {
        return $this->callMa(RoutineName::GHI_NHAN_DANG_NHAP, [$taiKhoanId, $ketQua, $ip]);
    }

    /** @return array{items: array<int, array<string, mixed>>, tong: int} */
    public function danhSach(string $tuKhoa, int $gioiHan = 50, int $boQua = 0): array
    {
        $rows = $this->call(RoutineName::ADMIN_DS_TAI_KHOAN, [$tuKhoa, $gioiHan, $boQua]);
        $tong = $rows === [] ? 0 : (int) ($rows[0]['tong'] ?? count($rows));
        return ['items' => $rows, 'tong' => $tong];
    }

    public function datTrangThai(int $id, string $trangThai, int $nguoiThucHien): string
    {
        return $this->callMa(RoutineName::ADMIN_DAT_TRANG_THAI_TAI_KHOAN, [$id, $trangThai, $nguoiThucHien]);
    }
}
