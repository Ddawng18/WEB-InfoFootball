<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\User;
use App\Repositories\UserRepository;

/** Nghiệp vụ tài khoản: đăng ký, đăng nhập (khóa sau 5 lần sai), đăng xuất, phiên. */
final class AuthService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    /**
     * @return array{ma:string, thong_bao:string}
     */
    public function dangKy(string $ten, string $email, string $hoTen, string $matKhau, string $matKhau2): array
    {
        if (!Validator::tenDangNhap($ten)) {
            return ['ma' => 'UNVALID_DATA', 'thong_bao' => 'Tên đăng nhập 4-50 ký tự (chữ, số, gạch dưới).'];
        }
        if (!$this->matKhauManh($matKhau)) {
            return ['ma' => 'UNVALID_DATA', 'thong_bao' => 'Mật khẩu tối thiểu 8 ký tự.'];
        }
        if ($matKhau !== $matKhau2) {
            return ['ma' => 'UNVALID_DATA', 'thong_bao' => 'Hai lần nhập mật khẩu không khớp.'];
        }
        if (!Validator::email($email)) {
            return ['ma' => 'UNVALID_DATA', 'thong_bao' => 'Email không đúng định dạng.'];
        }

        $hash = password_hash($matKhau, PASSWORD_DEFAULT);
        $ma = $this->users->dangKy($ten, $email, Validator::chuoi($hoTen, 120), $hash);

        $thongBao = match ($ma) {
            'OK' => 'Đăng ký thành công.',
            'TRUNG_TEN' => 'Tên đăng nhập đã tồn tại.',
            'TRUNG_EMAIL' => 'Email đã được sử dụng.',
            default => 'Không thể đăng ký, vui lòng thử lại.',
        };
        return ['ma' => $ma, 'thong_bao' => $thongBao];
    }

    /**
     * @return array{ma:string, thong_bao:string}
     */
    public function dangNhap(string $ten, string $matKhau, string $ip = ''): array
    {
        $row = $this->users->timTheoTen($ten);
        if ($row === null) {
            // Không ghi nhật ký vì không có FK tài khoản; coi như không tồn tại.
            return ['ma' => 'KHONG_TON_TAI', 'thong_bao' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];
        }

        $tkId = (int) $row['tai_khoan_id'];
        $khoaDen = $row['khoa_den'] ?? null;
        $dangBiKhoa = ($row['trang_thai'] ?? '') === 'KHOA'
            || ($khoaDen !== null && strtotime((string) $khoaDen . ' UTC') > time());

        if ($dangBiKhoa) {
            $this->users->ghiNhanDangNhap($tkId, 'BI_KHOA', $ip);
            return ['ma' => 'BI_KHOA', 'thong_bao' => 'Tài khoản đang bị khóa. Vui lòng thử lại sau.'];
        }

        if (!password_verify($matKhau, (string) $row['mat_khau_hash'])) {
            $this->users->ghiNhanDangNhap($tkId, 'SAI_MAT_KHAU', $ip);
            return ['ma' => 'SAI_MAT_KHAU', 'thong_bao' => 'Tên đăng nhập hoặc mật khẩu không đúng.'];
        }

        $this->users->ghiNhanDangNhap($tkId, 'THANH_CONG', $ip);
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['tai_khoan_id'] = $tkId;
        $_SESSION['vai_tro'] = (string) $row['vai_tro_ma'];
        $_SESSION['ten_dang_nhap'] = (string) $row['ten_dang_nhap'];
        $_SESSION['ho_ten'] = (string) ($row['ho_ten'] ?? '');

        return ['ma' => 'OK', 'thong_bao' => 'Đăng nhập thành công.'];
    }

    public function dangXuat(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public function daDangNhap(): bool
    {
        return isset($_SESSION['tai_khoan_id']);
    }

    public function laAdmin(): bool
    {
        return $this->daDangNhap() && ($_SESSION['vai_tro'] ?? '') === 'ADMIN';
    }

    public function taiKhoanId(): ?int
    {
        return $this->daDangNhap() ? (int) $_SESSION['tai_khoan_id'] : null;
    }

    public function currentUser(): ?User
    {
        if (!$this->daDangNhap()) {
            return null;
        }
        return new User(
            (int) $_SESSION['tai_khoan_id'],
            (string) ($_SESSION['ten_dang_nhap'] ?? ''),
            '',
            (string) ($_SESSION['ho_ten'] ?? ''),
            (string) ($_SESSION['vai_tro'] ?? 'USER'),
            'HOAT_DONG'
        );
    }

    public function matKhauManh(string $matKhau): bool
    {
        return Validator::doDai($matKhau, 8, 72);
    }
}
