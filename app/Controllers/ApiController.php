<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\JsonResponse;
use App\Core\Time;
use App\Core\Validator;
use App\Models\FootballMatch;
use App\Services\AuthService;
use App\Services\FavoriteService;
use App\Services\MatchService;
use App\Services\NewsService;

/** Endpoint JSON cho AJAX: api/matches.php, api/match.php, api/favorite.php, api/admin/sync.php. */
final class ApiController
{
    private MatchService $matches;
    private AuthService $auth;
    private FavoriteService $favorites;

    public function __construct()
    {
        $this->matches = new MatchService();
        $this->auth = new AuthService();
        $this->favorites = new FavoriteService();
    }

    /** GET api/matches.php */
    public function matches(): void
    {
        $ngay = Validator::ngayYmd($_GET['date'] ?? null);
        $view = Validator::chuoi($_GET['view'] ?? '', 20);
        $giaiId = Validator::soNguyenDuong($_GET['league'] ?? null);
        $trangThai = Validator::maTrangThai($_GET['status'] ?? null);
        $trang = Validator::soNguyenDuong($_GET['page'] ?? null) ?? 1;
        $doiId = Validator::soNguyenDuong($_GET['team'] ?? null);

        if ($ngay === null && $view === 'home') {
            // Nhịp 30 giây của ứng dụng: đồng bộ tin tức (TTL chặn còn 30s) để tin luôn mới.
            try {
                (new NewsService())->run(20);
            } catch (\Throwable $e) {
                // Không để lỗi cào tin ảnh hưởng tới phản hồi trang chủ.
            }

            $tt = $_GET['tt'] ?? 'all';
            if (!in_array($tt, ['all', 'live', 'finished', 'scheduled'], true)) {
                $tt = 'all';
            }
            $giaiHome = Validator::soNguyenDuong($_GET['giai'] ?? null);

            $base = ['giai_id' => $giaiHome];
            $filter = $base + ['sap_xep' => 'GIAI'];
            if ($tt === 'live') {
                $filter['nhom'] = 'DANG';
            } elseif ($tt === 'finished') {
                $filter['trang_thai'] = 'KET_THUC';
            } elseif ($tt === 'scheduled') {
                $filter['trang_thai'] = 'SAP_DIEN_RA';
            }
            $filter['gioi_han'] = 20;
            $filter['bo_qua'] = ($trang - 1) * 20;
            $res = $this->matches->getList($filter);

            $capNhat = '';
            foreach ($res['items'] as $m) {
                if ($m->getCapNhatLuc() > $capNhat) {
                    $capNhat = $m->getCapNhatLuc();
                }
            }

            JsonResponse::ok(['items' => array_map([$this, 'payload'], $res['items'])], [
                'tong' => $res['tong'],
                'trang' => $trang,
                'moi_trang' => 20,
                'cap_nhat_luc' => Time::toDisplay($capNhat),
                'du_lieu_cu' => false,
                'thong_bao' => $res['tong'] === 0 ? 'Không có trận nào' : '',
                'chu_ky_giay' => 30,
            ]);
            return;
        }

        $this->matches->dongBoNgay($ngay !== null ? 'FIXTURES' : 'LIVE');
        if ($ngay !== null) {
            $res = $this->matches->getSchedule($ngay, $giaiId, $trangThai, $trang, 20);
        } else {
            $res = $this->matches->getList([
                'gioi_han' => 20, 'bo_qua' => ($trang - 1) * 20,
                'giai_id' => $giaiId, 'trang_thai' => $trangThai, 'doi_id' => $doiId,
            ]);
        }

        $items = array_map([$this, 'payload'], $res['items']);
        $capNhat = '';
        foreach ($res['items'] as $m) {
            if ($m->getCapNhatLuc() > $capNhat) {
                $capNhat = $m->getCapNhatLuc();
            }
        }

        JsonResponse::ok($items, [
            'tong' => $res['tong'],
            'trang' => $trang,
            'cap_nhat_luc' => Time::toDisplay($capNhat),
            'du_lieu_cu' => false,
            'thong_bao' => $res['tong'] === 0 ? 'Không có trận nào' : '',
        ]);
    }

    /** GET api/match.php?id= */
    public function match(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? null);
        if ($id === null) {
            JsonResponse::error('Thiếu hoặc sai tham số id.', [], 422);
            return;
        }
        $tran = $this->matches->getDetail($id);
        if ($tran === null) {
            JsonResponse::error('Không tìm thấy trận đấu.', [], 404);
            return;
        }
        JsonResponse::ok([
            'tran' => $this->payload($tran),
            'su_kien' => $this->matches->getEvents($id),
        ], ['cap_nhat_luc' => Time::toDisplay($tran->getCapNhatLuc())]);
    }

    /** POST api/favorite.php (doi, bat) - cần đăng nhập + CSRF. */
    public function favorite(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            JsonResponse::error('Chỉ hỗ trợ POST.', [], 405);
            return;
        }
        if (!$this->auth->daDangNhap()) {
            JsonResponse::error('Vui lòng đăng nhập để lưu đội yêu thích.', ['can_dang_nhap' => true], 401);
            return;
        }
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            JsonResponse::error('Token CSRF không hợp lệ.', [], 419);
            return;
        }
        $doiId = Validator::soNguyenDuong($_POST['doi'] ?? null);
        if ($doiId === null) {
            JsonResponse::error('Thiếu đội bóng.', [], 422);
            return;
        }
        $bat = ($_POST['bat'] ?? '1') !== '0';
        $kq = $this->favorites->dat((int) $this->auth->taiKhoanId(), $doiId, $bat);
        if ($kq['ma'] !== 'OK') {
            JsonResponse::error($kq['thong_bao'], [], 400);
            return;
        }
        JsonResponse::ok(['yeu_thich' => $bat], ['thong_bao' => $kq['thong_bao']]);
    }

    /** POST api/admin/sync.php (loai) - chỉ ADMIN. */
    public function adminSync(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            JsonResponse::error('Chỉ hỗ trợ POST.', [], 405);
            return;
        }
        if (!$this->auth->laAdmin()) {
            JsonResponse::error('Chỉ quản trị viên được đồng bộ.', [], 403);
            return;
        }
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            JsonResponse::error('Token CSRF không hợp lệ.', [], 419);
            return;
        }
        $loai = strtoupper(Validator::chuoi($_POST['loai'] ?? 'LIVE', 20));
        if (!in_array($loai, ['LIVE', 'FIXTURES', 'CATALOG', 'NEWS'], true)) {
            $loai = 'LIVE';
        }
        $kq = $loai === 'NEWS'
            ? (new NewsService())->run(30)
            : $this->matches->dongBoNgay($loai);
        JsonResponse::ok($kq, ['thong_bao' => $kq['thong_bao'] ?? '']);
    }

    /** @return array<string, mixed> */
    private function payload(FootballMatch $m): array
    {
        return [
            'tran_dau_id' => $m->getId(),
            'giai_dau_id' => $m->getGiaiDauId(),
            'giai_ten' => $m->getGiaiTen(),
            'doi_nha_id' => $m->getDoiNhaId(),
            'doi_khach_id' => $m->getDoiKhachId(),
            'doi_nha_ten' => $m->getDoiNhaTen(),
            'doi_khach_ten' => $m->getDoiKhachTen(),
            'doi_nha_logo' => $m->getDoiNhaLogo(),
            'doi_khach_logo' => $m->getDoiKhachLogo(),
            'thoi_gian_bat_dau' => $m->getThoiGianBatDau(),
            'thoi_gian_hien_thi' => Time::toDisplay($m->getThoiGianBatDau()),
            'trang_thai' => $m->getTrangThai(),
            'trang_thai_nhan' => nhan_trang_thai($m->getTrangThai()),
            'phut' => $m->getPhut(),
            'ti_so_nha' => $m->getTiSoNha(),
            'ti_so_khach' => $m->getTiSoKhach(),
        ];
    }
}
