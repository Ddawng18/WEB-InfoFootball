<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\JsonResponse;
use App\Core\Time;
use App\Core\Validator;
use App\Models\FootballMatch;
use App\Repositories\FootballProductRepository;
use App\Services\AuthService;
use App\Services\CalendarService;
use App\Services\FavoriteService;
use App\Services\FootballProductFeedService;
use App\Services\MatchService;
use App\Services\NewsService;

/** Endpoint JSON cho AJAX: matches, match, favorite, products, calendar, notifications, admin/sync. */
final class ApiController
{
    private MatchService $matches;
    private AuthService $auth;
    private FavoriteService $favorites;
    private CalendarService $calendar;

    public function __construct()
    {
        $this->matches = new MatchService();
        $this->auth = new AuthService();
        $this->favorites = new FavoriteService();
        $this->calendar = new CalendarService();
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
            // Đồng bộ LIVE theo TTL (có backoff khi lỗi) để AJAX 30 giây thực sự thấy tỉ số mới.
            $sync = $this->matches->dongBoNgay('LIVE');
            // Tin tức cũng được cào theo TTL (backoff bên trong NewsService).
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
            $perPage = 20;
            $res = $this->matches->getHomeList($tt, $giaiHome, $trang, $perPage);
            $dem = $this->matches->getHomeCounts($giaiHome);

            $capNhat = '';
            foreach ($res['items'] as $m) {
                if ($m->getCapNhatLuc() > $capNhat) {
                    $capNhat = $m->getCapNhatLuc();
                }
            }
            $loiApi = in_array($sync['ma'], ['LOI', 'LOI_HE_THONG'], true);
            $thongBao = $res['tong'] === 0
                ? 'Không có trận nào'
                : ($loiApi ? (string) ($sync['thong_bao'] ?? '') : '');

            JsonResponse::ok(['items' => array_map([$this, 'payload'], $res['items'])], [
                'tong' => $res['tong'],
                'trang' => $trang,
                'moi_trang' => $perPage,
                'cap_nhat_luc' => Time::toDisplay($capNhat),
                'du_lieu_cu' => $loiApi,
                'thong_bao' => $thongBao,
                'dem' => $dem,
                'chu_ky_giay' => 30,
            ]);
            return;
        }

        $homNay = (new \DateTime('now', new \DateTimeZone(Time::tz())))->format('Y-m-d');
        $sync = $this->matches->dongBoNgay(
            $ngay === null || $ngay === $homNay ? 'LIVE' : 'FIXTURES'
        );
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

        $loiApi = in_array($sync['ma'], ['LOI', 'LOI_HE_THONG'], true);
        JsonResponse::ok($items, [
            'tong' => $res['tong'],
            'trang' => $trang,
            'cap_nhat_luc' => Time::toDisplay($capNhat),
            'du_lieu_cu' => $loiApi,
            'thong_bao' => $res['tong'] === 0
                ? 'Không có trận nào'
                : ($loiApi ? (string) ($sync['thong_bao'] ?? '') : ''),
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
        $tran = $this->matches->getDetailFresh($id);
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

    /** POST api/calendar.php (tran, trong_lich, nhac) - cần đăng nhập + CSRF. */
    public function calendar(): void
    {
        $taiKhoanId = $this->auth->taiKhoanId();
        if ($taiKhoanId === null) {
            JsonResponse::error('Vui lòng đăng nhập để dùng lịch và nhắc trận.', ['can_dang_nhap' => true], 401);
            return;
        }
        $action = Validator::chuoi($_GET['action'] ?? $_POST['action'] ?? '', 20);
        if ($action === 'states') {
            $ids = [];
            $raw = $_GET['ids'] ?? '';
            if (is_string($raw)) {
                foreach (explode(',', $raw) as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            }
            $ids = array_slice(array_values(array_unique($ids)), 0, 50);
            JsonResponse::ok(['items' => $this->calendar->trangThaiNhieu($taiKhoanId, $ids)]);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            JsonResponse::error('Chỉ hỗ trợ POST.', [], 405);
            return;
        }
        if (!Csrf::check($_POST['_csrf'] ?? null)) {
            JsonResponse::error('Token CSRF không hợp lệ.', [], 419);
            return;
        }
        $tranId = Validator::soNguyenDuong($_POST['tran'] ?? null);
        if ($tranId === null) {
            JsonResponse::error('Thiếu trận đấu.', [], 422);
            return;
        }
        $trongLich = ($_POST['trong_lich'] ?? '0') === '1';
        $nhac = ($_POST['nhac'] ?? '0') === '1';
        $kq = $this->calendar->dat(
            $taiKhoanId,
            $tranId,
            $trongLich,
            $nhac
        );
        if ($kq['ma'] !== 'OK') {
            JsonResponse::error($kq['thong_bao'], [], 400);
            return;
        }
        JsonResponse::ok([
            'trong_lich' => $kq['trong_lich'],
            'nhac' => $kq['nhac'],
        ], ['thong_bao' => $kq['thong_bao']]);
    }

    /** GET/POST api/notifications.php - danh sách và đánh dấu đã đọc. */
    public function notifications(): void
    {
        $taiKhoanId = $this->auth->taiKhoanId();
        if ($taiKhoanId === null) {
            JsonResponse::error('Vui lòng đăng nhập để xem thông báo.', ['can_dang_nhap' => true], 401);
            return;
        }
        $action = Validator::chuoi($_POST['action'] ?? $_GET['action'] ?? '', 20);
        if ($action === 'read') {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                JsonResponse::error('Chỉ hỗ trợ POST.', [], 405);
                return;
            }
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                JsonResponse::error('Token CSRF không hợp lệ.', [], 419);
                return;
            }
            $id = Validator::soNguyenDuong($_POST['id'] ?? null);
            $soDong = $this->calendar->danhDauDaDoc($taiKhoanId, $id);
            JsonResponse::ok(['so_dong' => $soDong], [
                'chua_doc' => $this->calendar->demChuaDoc($taiKhoanId),
                'thong_bao' => 'Đã đánh dấu đã đọc.',
            ]);
            return;
        }

        $res = $this->calendar->thongBao($taiKhoanId, 30);
        JsonResponse::ok(['items' => $res['items']], [
            'tong' => $res['tong'],
            'chua_doc' => $res['chua_doc'],
        ]);
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
        if (!in_array($loai, ['LIVE', 'FIXTURES', 'CATALOG', 'NEWS', 'SAN_PHAM'], true)) {
            $loai = 'LIVE';
        }
        if ($loai === 'NEWS') {
            $kq = (new NewsService())->run(30, true);
        } elseif ($loai === 'SAN_PHAM') {
            try {
                $kq = (new FootballProductFeedService())->run(-1);
            } catch (\Throwable $e) {
                JsonResponse::error('Không làm mới được sản phẩm: ' . $e->getMessage(), [], 500);
                return;
            }
        } else {
            $kq = $this->matches->dongBoNgay($loai, true);
        }
        JsonResponse::ok($kq, ['thong_bao' => $kq['thong_bao'] ?? '']);
    }

    /** GET api/products.php?action=prices - cập nhật giá hiện tại cho lưới sản phẩm. */
    public function productPrices(): void
    {
        $rawCategory = $_GET['danh_muc'] ?? null;
        $rawKeyword = $_GET['q'] ?? null;
        $category = is_string($rawCategory) ? Validator::chuoi($rawCategory, 120) : '';
        $keyword = is_string($rawKeyword) ? Validator::chuoi($rawKeyword, 100) : '';
        $requestedDomain = $_GET['ten_mien'] ?? '';
        $domain = is_string($requestedDomain)
            && preg_match('/^[a-z0-9.-]{1,100}$/', $requestedDomain) === 1
            ? strtolower($requestedDomain)
            : '';

        $items = [];
        try {
            $items = (new FootballProductRepository())->getPrices($category, $keyword, $domain);
        } catch (\Throwable $e) {
            error_log('Không đọc được giá sản phẩm: ' . $e->getMessage());
        }

        $data = array_map(static function (array $row): array {
            return [
                'ma' => (int) $row['ma'],
                'gia_goc' => (int) $row['gia_goc'],
                'gia_hien_tai' => (int) $row['gia_hien_tai'],
                'phan_tram_giam' => (int) $row['phan_tram_giam'],
                'so_tien_giam' => (int) $row['so_tien_giam'],
                'con_hang' => (int) $row['con_hang'],
                'lan_kiem_tra' => Time::toDisplay((string) ($row['lan_kiem_tra'] ?? '')),
            ];
        }, $items);

        JsonResponse::ok($data, [
            'cap_nhat_luc' => Time::toDisplay(Time::nowUtc()),
            'chu_ky_giay' => 60,
            'thong_bao' => '',
        ]);
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
            'trang_thai_nguon' => $m->getTrangThaiNguon(),
            'co_live' => $m->getCoLive(),
            'trang_thai_nhan' => nhan_trang_thai($m->getTrangThai()),
            'phut' => $m->getPhut(),
            'ti_so_nha' => $m->getTiSoNha(),
            'ti_so_khach' => $m->getTiSoKhach(),
        ];
    }
}
