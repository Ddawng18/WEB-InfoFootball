<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CatalogRepository;
use App\Repositories\SyncRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/** Khu vực quản trị (F8). Mọi hành động đều kiểm tra vai trò ADMIN. */
final class AdminController
{
    private AuthService $auth;
    private SyncRepository $sync;
    private UserRepository $users;
    private CatalogRepository $catalog;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->sync = new SyncRepository();
        $this->users = new UserRepository();
        $this->catalog = new CatalogRepository();
    }

    public function dispatch(string $route): void
    {
        $this->batBuocAdmin();
        match ($route) {
            'admin' => $this->dashboard(),
            'admin-taikhoan' => $this->taiKhoan(),
            'admin-giai' => $this->cauHinhGiai(),
            default => $this->dashboard(),
        };
    }

    private function dashboard(): void
    {
        View::render('admin/dashboard', [
            'title' => 'Quản trị',
            'thong_ke' => $this->sync->thongKe(),
            'nhat_ky' => $this->sync->nhatKy(20),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function taiKhoan(): void
    {
        $tuKhoa = Validator::chuoi($_GET['q'] ?? '', 100);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 20;
        $res = $this->users->danhSach($tuKhoa, $moiTrang, ($trang - 1) * $moiTrang);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-taikhoan', ['msg' => 'csrf']));
                return;
            }
            $id = Validator::soNguyenDuong($_POST['id'] ?? null);
            $tt = ($_POST['trang_thai'] ?? '') === 'KHOA' ? 'KHOA' : 'HOAT_DONG';
            if ($id !== null) {
                $ma = $this->users->datTrangThai($id, $tt, (int) $this->auth->taiKhoanId());
                $this->chuyenTrang(route('admin-taikhoan', ['msg' => strtolower($ma)]));
                return;
            }
        }

        View::render('admin/users', [
            'title' => 'Quản lý tài khoản',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'tu_khoa' => $tuKhoa,
            'trang' => $trang,
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 40),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function cauHinhGiai(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-giai', ['msg' => 'csrf']));
                return;
            }
            $id = Validator::soNguyenDuong($_POST['id'] ?? null);
            if ($id !== null) {
                $theoDoi = ($_POST['dang_theo_doi'] ?? '0') === '1';
                $qgId = Validator::soNguyenDuong($_POST['quoc_gia_id'] ?? null);
                $khuVuc = Validator::chuoi($_POST['khu_vuc'] ?? '', 60);
                $ma = $this->sync->cauHinhGiaiDau($id, $theoDoi, $qgId, $khuVuc !== '' ? $khuVuc : null);
                $this->chuyenTrang(route('admin-giai', ['msg' => strtolower($ma)]));
                return;
            }
        }
        View::render('admin/leagues', [
            'title' => 'Cấu hình giải đấu',
            'leagues' => $this->catalog->getLeagues(null, null, null, 1000, 0)['items'],
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 40),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function batBuocAdmin(): void
    {
        if (!$this->auth->laAdmin()) {
            $this->chuyenTrang(route('dangnhap', ['msg' => 'can-dang-nhap']));
        }
    }

    private function chuyenTrang(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
