<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CatalogRepository;
use App\Repositories\NewsRepository;
use App\Repositories\SyncRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\NewsImageService;

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
        if (!$this->batBuocAdmin()) {
            return;
        }
        match ($route) {
            'admin' => $this->dashboard(),
            'admin-taikhoan' => $this->taiKhoan(),
            'admin-giai' => $this->cauHinhGiai(),
            'admin-tintuc' => $this->tinTuc(),
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

    private function tinTuc(): void
    {
        $repo = new NewsRepository();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-tintuc', ['msg' => 'csrf']));
                return;
            }

            $imageService = new NewsImageService();
            $action = Validator::chuoi($_POST['action'] ?? 'save', 20);

            if ($action === 'delete') {
                $id = Validator::soNguyenDuong($_POST['tin_id'] ?? null);
                if ($id === null) {
                    $this->chuyenTrang(route('admin-tintuc', ['msg' => 'delete_error']));
                    return;
                }
                $row = $repo->getNewsById($id);
                if ($row === null || ($row['nguon'] ?? '') !== 'ADMIN') {
                    $this->chuyenTrang(route('admin-tintuc', ['msg' => 'delete_forbidden']));
                    return;
                }
                $ma = $repo->xoaTinTucAdmin($id);
                if ($ma === 'OK') {
                    $imageService->delete((string) ($row['anh_url'] ?? ''));
                    $this->chuyenTrang(route('admin-tintuc', ['msg' => 'deleted']));
                    return;
                }
                $this->chuyenTrang(route('admin-tintuc', ['msg' => 'delete_error']));
                return;
            }

            $maNgoai = Validator::chuoi($_POST['ma_ngoai'] ?? '', 120);
            $tieuDe = Validator::chuoi($_POST['tieu_de'] ?? '', 250);
            $tomTat = Validator::chuoi($_POST['tom_tat'] ?? '', 580);
            $noiDung = trim((string) ($_POST['noi_dung'] ?? ''));
            if (mb_strlen($noiDung, 'UTF-8') > 20000) {
                $noiDung = mb_substr($noiDung, 0, 20000, 'UTF-8');
            }
            $anhUrl = Validator::chuoi($_POST['anh_url'] ?? '', 490);
            $duongDan = Validator::chuoi($_POST['duong_dan'] ?? '', 490);
            $danhMuc = Validator::chuoi($_POST['danh_muc'] ?? 'Bóng đá', 120);

            $upload = $imageService->save($_FILES['anh_file'] ?? []);
            if ($upload['ok']) {
                if ($anhUrl !== '') {
                    $imageService->delete($anhUrl);
                }
                $anhUrl = $upload['url'];
            } elseif ($upload['error'] !== 'NO_FILE') {
                $this->chuyenTrang(route('admin-tintuc', ['msg' => strtolower($upload['error'])]));
                return;
            }

            if ($tieuDe === '') {
                $this->chuyenTrang(route('admin-tintuc', ['msg' => 'unvalid']));
                return;
            }
            if ($maNgoai === '') {
                $maNgoai = 'admin-' . time() . '-' . bin2hex(random_bytes(4));
            }
            if ($tomTat === '') {
                $tomTat = mb_substr(trim(strip_tags($noiDung)), 0, 580, 'UTF-8');
            }
            $ma = $repo->upsertTinTuc(
                'ADMIN', $maNgoai, $tieuDe, $tomTat, $anhUrl, $duongDan, $noiDung,
                null, $danhMuc !== '' ? $danhMuc : 'Bóng đá', 0
            );
            $this->chuyenTrang(route('admin-tintuc', ['msg' => strtolower($ma)]));
            return;
        }

        $res = $repo->getNews(100, 0);
        $adminItems = array_values(array_filter(
            $res['items'],
            static fn (array $row): bool => ($row['nguon'] ?? '') === 'ADMIN'
        ));
        $editing = null;
        $sua = Validator::chuoi($_GET['sua'] ?? '', 120);
        if ($sua !== '') {
            foreach ($adminItems as $row) {
                if ((string) ($row['ma_ngoai'] ?? '') === $sua || (string) ($row['tin_id'] ?? '') === $sua) {
                    $editing = $repo->getNewsById((int) ($row['tin_id'] ?? 0)) ?? $row;
                    break;
                }
            }
        }

        View::render('admin/news', [
            'title' => 'Quản lý tin tức',
            'items' => $adminItems,
            'editing' => $editing,
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

    private function batBuocAdmin(): bool
    {
        if (!$this->auth->daDangNhap()) {
            $this->chuyenTrang(route('dangnhap', ['msg' => 'can-dang-nhap']));
            return false;
        }
        if (!$this->auth->laAdmin()) {
            http_response_code(403);
            View::render('error', [
                'title' => 'Không có quyền truy cập',
                'thong_bao' => 'Bạn không có quyền truy cập khu vực quản trị.',
                'user' => $this->auth->currentUser(),
            ]);
            return false;
        }
        return true;
    }

    private function chuyenTrang(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
