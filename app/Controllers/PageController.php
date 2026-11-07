<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Time;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CatalogRepository;
use App\Repositories\CartRepository;
use App\Repositories\SouvenirRepository;
use App\Services\AuthService;
use App\Services\FavoriteService;
use App\Services\MatchService;
use App\Services\NewsService;
use App\Services\SyncService;

/** Điều khiển các trang HTML (View). Chọn view, truyền dữ liệu từ Service. */
final class PageController
{
    private MatchService $matches;
    private CatalogRepository $catalog;
    private SouvenirRepository $souvenirs;
    private CartRepository $cart;
    private AuthService $auth;
    private FavoriteService $favorites;

    public function __construct()
    {
        $this->matches = new MatchService();
        $this->catalog = new CatalogRepository();
        $this->souvenirs = new SouvenirRepository();
        $this->cart = new CartRepository();
        $this->auth = new AuthService();
        $this->favorites = new FavoriteService();
    }

    public function dispatch(string $route): void
    {
        match ($route) {
            'home' => $this->home(),
            'lich' => $this->lich(),
            'tran' => $this->tran(),
            'giai' => $this->giai(),
            'doi' => $this->doi(),
            'doi-chitiet' => $this->doiChiTiet(),
            'yeuthich' => $this->yeuThich(),
            'dangnhap' => $this->dangNhap(),
            'dangky' => $this->dangKy(),
            'dangxuat' => $this->dangXuat(),
            'tintuc' => $this->tinTuc(),
            'tintuc-chitiet' => $this->tinTucChiTiet(),
            'souvenirs' => $this->souvenirs(),
            'giohang' => $this->gioHang(),
            default => $this->khongTimThay(),
        };
    }

    private function home(): void
    {
        // Đồng bộ tỉ số (TTL) + cào tin (nhịp 30s của ứng dụng)
        $sync = new SyncService();
        $kqSync = $sync->run('LIVE');
        try {
            (new NewsService())->run(20);
        } catch (\Throwable $e) {
            // bỏ qua lỗi cào tin
        }

        $tt = $_GET['tt'] ?? 'all';
        if (!in_array($tt, ['all', 'live', 'finished', 'scheduled'], true)) {
            $tt = 'all';
        }
        $giaiId = Validator::soNguyenDuong($_GET['giai'] ?? null);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 20;
        $trangGiai = Validator::soNguyenDuong($_GET['lg'] ?? null) ?? 1;
        $moiGiai = 15;

        $base = ['giai_id' => $giaiId];
        $filter = $base + ['sap_xep' => 'GIAI'];
        if ($tt === 'live') {
            $filter['nhom'] = 'DANG';
        } elseif ($tt === 'finished') {
            $filter['trang_thai'] = 'KET_THUC';
        } elseif ($tt === 'scheduled') {
            $filter['trang_thai'] = 'SAP_DIEN_RA';
        }
        $filter['gioi_han'] = $moiTrang;
        $filter['bo_qua'] = ($trang - 1) * $moiTrang;
        $res = $this->matches->getList($filter);
        $homeLogos = [];
        if (strtolower(\App\Core\Config::string('api', 'provider', 'sample')) === 'apifootball') {
            $apiFootball = new \App\Services\ApiFootballClient();
            foreach ($res['items'] as $match) {
                $homeLogos[$match->getId()]['home'] = $this->cacheMatchLogo($match->getDoiNhaLogo() ?: $apiFootball->findTeamLogo($match->getDoiNhaTen()));
                $homeLogos[$match->getId()]['away'] = $this->cacheMatchLogo($match->getDoiKhachLogo() ?: $apiFootball->findTeamLogo($match->getDoiKhachTen()));
            }
        }

        $dem = [
            'all' => $this->matches->getList($base + ['gioi_han' => 1])['tong'],
            'live' => $this->matches->getList($base + ['nhom' => 'DANG', 'gioi_han' => 1])['tong'],
            'finished' => $this->matches->getList($base + ['trang_thai' => 'KET_THUC', 'gioi_han' => 1])['tong'],
            'scheduled' => $this->matches->getList($base + ['trang_thai' => 'SAP_DIEN_RA', 'gioi_han' => 1])['tong'],
        ];

        $capNhat = '';
        foreach ($res['items'] as $m) {
            if ($m->getCapNhatLuc() > $capNhat) {
                $capNhat = $m->getCapNhatLuc();
            }
        }

        $loiApi = in_array($kqSync['ma'], ['LOI', 'LOI_HE_THONG'], true);

        $allowedLeagueIds = array_map('intval', explode(',', \App\Core\Config::string('api', 'league_ids', '39,140,2,78,135,61,88,94,98,340')));
        $allLeagues = $this->catalog->getLeagues(null, null, null, 1000, 0)['items'];
        $featuredLeagues = array_values(array_filter($allLeagues, static fn ($league) =>
            strtolower(\App\Core\Config::string('api', 'provider', 'sample')) !== 'apifootball'
            || in_array((int) $league->getMaApi(), $allowedLeagueIds, true)
        ));
        $lgRes = ['items' => array_slice($featuredLeagues, ($trangGiai - 1) * $moiGiai, $moiGiai), 'tong' => count($featuredLeagues)];

        View::render('home', [
            'title' => 'Bảng tỉ số',
            'items' => $res['items'],
            'home_logos' => $homeLogos,
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'tt' => $tt,
            'giai_id' => $giaiId,
            'dem' => $dem,
            'cap_nhat_luc' => $capNhat,
            'du_lieu_cu' => $loiApi,
            'thong_bao' => $kqSync['thong_bao'] ?? '',
            'leagues' => $lgRes['items'],
            'tong_giai' => $lgRes['tong'],
            'trang_giai' => $trangGiai,
            'moi_giai' => $moiGiai,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function lich(): void
    {
        $ngay = Validator::ngayYmd($_GET['ngay'] ?? null) ?? (new \DateTime('now', new \DateTimeZone(Time::tz())))->format('Y-m-d');
        $giaiId = Validator::soNguyenDuong($_GET['giai'] ?? null);
        $trangThai = Validator::maTrangThai($_GET['tt'] ?? null);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;

        // Đồng bộ theo TTL khi người dùng xem lịch
        $this->matches->dongBoNgay('FIXTURES');

        $res = $this->matches->getSchedule($ngay, $giaiId, $trangThai, $trang);
        View::render('schedule', [
            'title' => 'Lịch thi đấu',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'ngay' => $ngay,
            'giai_id' => $giaiId,
            'trang_thai' => $trangThai,
            'trang' => $trang,
            'leagues' => array_values(array_filter($this->catalog->getLeagues(null, null, null, 1000, 0)['items'], static fn ($league) =>
                strtolower(\App\Core\Config::string('api', 'provider', 'sample')) !== 'apifootball'
                || in_array((int) $league->getMaApi(), array_map('intval', explode(',', \App\Core\Config::string('api', 'league_ids', '39,140,2,78,135,61,88,94,98,340'))), true)
            )),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function tran(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? null);
        $tran = $id !== null ? $this->matches->getDetail($id) : null;
        if ($tran === null) {
            $this->khongTimThay('Không tìm thấy trận đấu.');
            return;
        }
        $matchDetails = ['events' => [], 'lineups' => [], 'statistics' => []];
        $homeSourceLogo = $tran->getDoiNhaLogo();
        $awaySourceLogo = $tran->getDoiKhachLogo();
        if (strtolower(\App\Core\Config::string('api', 'provider', 'sample')) === 'apifootball') {
            try {
                $apiFootball = new \App\Services\ApiFootballClient();
                $matchDetails = $apiFootball->fetchMatchDetails($tran->getMaApi());
                if ($homeSourceLogo === '') { $homeSourceLogo = $apiFootball->findTeamLogo($tran->getDoiNhaTen()); }
                if ($awaySourceLogo === '') { $awaySourceLogo = $apiFootball->findTeamLogo($tran->getDoiKhachTen()); }
            } catch (\Throwable $e) {
                error_log('Không tải được chi tiết trận từ API-Football: ' . $e->getMessage());
            }
        }
        $homeLogo = $this->cacheMatchLogo($homeSourceLogo);
        $awayLogo = $this->cacheMatchLogo($awaySourceLogo);
        View::render('match', [
            'title' => $tran->getDoiNhaTen() . ' vs ' . $tran->getDoiKhachTen(),
            'tran' => $tran,
            'su_kien' => $this->matches->getEvents($id),
            'match_details' => $matchDetails,
            'home_logo_local' => $homeLogo,
            'away_logo_local' => $awayLogo,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function cacheMatchLogo(string $url): string
    {
        if (!str_starts_with($url, 'https://media.api-sports.io/football/teams/')) {
            return $url;
        }
        $name = basename(parse_url($url, PHP_URL_PATH) ?: '');
        if (!preg_match('/^\d+\.png$/', $name)) { return ''; }
        $dir = APP_ROOT . '/public/images/teams';
        $path = $dir . '/' . $name;
        if (!is_file($path)) {
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $image = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5], 'https' => ['timeout' => 5]]));
            if ($image !== false && strlen($image) < 500000 && str_starts_with($image, "\x89PNG\r\n\x1a\n")) {
                file_put_contents($path, $image);
            }
        }
        return is_file($path) ? asset('images/teams/' . $name) : '';
    }

    private function giai(): void
    {
        $quocGia = Validator::soNguyenDuong($_GET['qg'] ?? null);
        $khuVuc = Validator::chuoi($_GET['kv'] ?? null, 60);
        $tuKhoa = Validator::chuoi($_GET['q'] ?? null, 100);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 9;
        $res = $this->catalog->getLeagues(
            $quocGia,
            $khuVuc !== '' ? $khuVuc : null,
            $tuKhoa !== '' ? $tuKhoa : null,
            $moiTrang,
            ($trang - 1) * $moiTrang
        );
        View::render('leagues', [
            'title' => 'Giải đấu',
            'leagues' => $res['items'],
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'khu_vuc' => $khuVuc,
            'tu_khoa' => $tuKhoa,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function doi(): void
    {
        $giaiId = Validator::soNguyenDuong($_GET['giai'] ?? null);
        $tuKhoa = Validator::chuoi($_GET['q'] ?? null, 100);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 12;
        $res = $this->catalog->getTeams(
            $giaiId,
            $tuKhoa !== '' ? $tuKhoa : null,
            $moiTrang,
            ($trang - 1) * $moiTrang
        );

        // Tập đội đã yêu thích để tô đầy tim ngay khi tải trang
        $favIds = [];
        if ($this->auth->daDangNhap()) {
            $favRes = $this->favorites->danhSach((int) $this->auth->taiKhoanId(), 1000, 0);
            foreach ($favRes['items'] as $row) {
                $favIds[] = (int) $row['doi_bong_id'];
            }
        }

        View::render('teams', [
            'title' => 'Đội bóng',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'tu_khoa' => $tuKhoa,
            'gioi_id' => $giaiId,
            'fav_ids' => $favIds,
            'leagues' => $this->catalog->getLeagues()['items'],
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function doiChiTiet(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? null);
        $doi = $id !== null ? $this->catalog->getTeamById($id) : null;
        if ($doi === null) {
            $this->khongTimThay('Không tìm thấy đội bóng.');
            return;
        }
        $sapToi = $this->matches->getList(['doi_id' => $id, 'trang_thai' => 'SAP_DIEN_RA', 'gioi_han' => 5]);
        $ganDay = $this->matches->getList(['doi_id' => $id, 'trang_thai' => 'KET_THUC', 'gioi_han' => 5]);

        View::render('team', [
            'title' => $doi->getTen(),
            'doi' => $doi,
            'sap_toi' => $sapToi['items'],
            'gan_day' => $ganDay['items'],
            'la_yeu_thich' => $this->auth->daDangNhap()
                ? $this->favorites->laYeuThich((int) $this->auth->taiKhoanId(), $id)
                : false,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function yeuThich(): void
    {
        if (!$this->auth->daDangNhap()) {
            $this->chuyenTrang(route('dangnhap', ['msg' => 'can-dang-nhap']));
            return;
        }
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 10;
        $res = $this->favorites->danhSach((int) $this->auth->taiKhoanId(), $moiTrang, ($trang - 1) * $moiTrang);
        View::render('favorites', [
            'title' => 'Đội yêu thích',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function dangNhap(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('dangnhap', ['msg' => 'csrf']));
                return;
            }
            $kq = $this->auth->dangNhap(
                Validator::chuoi($_POST['username'] ?? '', 50),
                (string) ($_POST['password'] ?? ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            );
            if ($kq['ma'] === 'OK') {
                $this->chuyenTrang($this->auth->laAdmin() ? route('admin') : route('home'));
            } else {
                $this->chuyenTrang(route('dangnhap', ['msg' => $kq['ma']]));
            }
            return;
        }
        View::render('auth/login', [
            'title' => 'Đăng nhập',
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 60),
            'user' => null,
        ]);
    }

    private function dangKy(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('dangky', ['msg' => 'csrf']));
                return;
            }
            $kq = $this->auth->dangKy(
                Validator::chuoi($_POST['username'] ?? '', 50),
                Validator::chuoi($_POST['email'] ?? '', 120),
                Validator::chuoi($_POST['ho_ten'] ?? '', 120),
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password2'] ?? '')
            );
            if ($kq['ma'] === 'OK') {
                $this->chuyenTrang(route('dangnhap', ['msg' => 'done']));
            } else {
                $this->chuyenTrang(route('dangky', ['msg' => $kq['ma']]));
            }
            return;
        }
        View::render('auth/register', [
            'title' => 'Đăng ký',
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 60),
            'user' => null,
        ]);
    }

    private function dangXuat(): void
    {
        $this->auth->dangXuat();
        $this->chuyenTrang(route('home'));
    }

    private function tinTuc(): void
    {
        $news = new NewsService();
        $ketQua = $news->run(30); // TTL-gated: cào tin mới nếu hết hạn
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 9;
        $res = $this->catalog->getNews($moiTrang, ($trang - 1) * $moiTrang);
        View::render('news', [
            'title' => 'Tin tức',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'crawl' => $ketQua,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function tinTucChiTiet(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? null);
        $tin = $id !== null ? $this->catalog->getNewsById($id) : null;
        if ($tin === null) {
            $this->khongTimThay('Không tìm thấy tin tức.');
            return;
        }
        View::render('news_detail', [
            'title' => (string) $tin['tieu_de'],
            'tin' => $tin,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function souvenirs(): void
    {
        $categories = $this->souvenirs->getCategories();
        $categoryIds = array_map(static fn (array $category): int => (int) $category['ma'], $categories);
        $requestedCategory = Validator::soNguyenKhongAm($_GET['danh_muc'] ?? null, 0);
        $categoryId = in_array($requestedCategory, $categoryIds, true) ? $requestedCategory : 0;
        $sort = $_GET['sap_xep'] ?? 'moi_nhat';
        if (!is_string($sort) || !in_array($sort, ['moi_nhat', 'gia_tang', 'gia_giam'], true)) {
            $sort = 'moi_nhat';
        }
        $page = min(1000000, Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1);
        $perPage = 12;
        $result = $this->souvenirs->getProducts($categoryId, $sort, $page, $perPage);

        View::render('souvenirs', [
            'title' => 'Lưu niệm',
            'categories' => $categories,
            'category_id' => $categoryId,
            'sort' => $sort,
            'items' => $result['items'],
            'tong' => $result['tong'],
            'trang' => $page,
            'moi_trang' => $perPage,
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 40),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function gioHang(): void
    {
        if (!$this->auth->daDangNhap()) {
            $this->chuyenTrang(route('dangnhap', ['msg' => 'can-dang-nhap']));
            return;
        }

        $accountId = (int) $this->auth->taiKhoanId();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $token = is_string($_POST[Csrf::KEY] ?? null) ? $_POST[Csrf::KEY] : null;
            if (!Csrf::check($token)) {
                $this->chuyenTrang($this->cartPostUrl('csrf'));
                return;
            }

            $action = is_string($_POST['hanh_dong'] ?? null) ? $_POST['hanh_dong'] : '';
            try {
                if ($action === 'them' || $action === 'capnhat') {
                    $productId = $this->cartIntegerInput($_POST['ma_san_pham'] ?? null, 1, PHP_INT_MAX);
                    $quantity = $this->cartIntegerInput($_POST['so_luong'] ?? null, 1, 10);
                    if ($productId === null) {
                        $this->chuyenTrang($this->cartPostUrl('product_not_found'));
                        return;
                    }
                    if ($quantity === null) {
                        $this->chuyenTrang($this->cartPostUrl('invalid_quantity'));
                        return;
                    }
                    $result = $action === 'them'
                        ? $this->cart->add($accountId, $productId, $quantity)
                        : $this->cart->update($accountId, $productId, $quantity);
                    $message = match ($result) {
                        'OK' => $action === 'them' ? 'added' : 'updated',
                        'GIOI_HAN_SO_LUONG' => 'quantity_capped',
                        default => 'cart_error',
                    };
                } elseif ($action === 'xoa') {
                    $productId = $this->cartIntegerInput($_POST['ma_san_pham'] ?? null, 1, PHP_INT_MAX);
                    if ($productId === null) {
                        $this->chuyenTrang($this->cartPostUrl('product_not_found'));
                        return;
                    }
                    $result = $this->cart->delete($accountId, $productId);
                    $message = $result === 'OK'
                        ? 'deleted'
                        : ($result === 'KHONG_CO_TRONG_GIO' ? 'cart_item_missing' : 'cart_error');
                } elseif ($action === 'xoahet') {
                    $result = $this->cart->clear($accountId);
                    $message = $result === 'OK' ? 'cleared' : 'cart_error';
                } else {
                    $message = 'invalid_action';
                }
            } catch (\Throwable $e) {
                error_log('Không thể xử lý giỏ hàng: ' . $e->getMessage());
                $message = $this->cartExceptionMessage($e);
            }
            $this->chuyenTrang($this->cartPostUrl($message));
            return;
        }

        $message = Validator::chuoi($_GET['msg'] ?? '', 40);
        try {
            $items = $this->cart->getItems($accountId);
        } catch (\Throwable $e) {
            error_log('Không thể đọc giỏ hàng: ' . $e->getMessage());
            $items = [];
            $message = $this->cartExceptionMessage($e);
        }

        View::render('cart', [
            'title' => 'Giỏ hàng',
            'items' => $items,
            'msg' => $message,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function cartIntegerInput($value, int $min, int $max): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        if (!preg_match('/^[0-9]+$/D', $value)) {
            return null;
        }
        $number = (int) $value;
        return $number >= $min && $number <= $max ? $number : null;
    }

    private function cartExceptionMessage(\Throwable $exception): string
    {
        $detail = $exception->getMessage();
        if (strpos($detail, 'Tài khoản không tồn tại hoặc không hoạt động') !== false) {
            return 'account_inactive';
        }
        if (strpos($detail, 'Số lượng phải từ 1 đến 10') !== false) {
            return 'invalid_quantity';
        }
        if (strpos($detail, 'Sản phẩm không tồn tại') !== false) {
            return 'product_not_found';
        }
        if (strpos($detail, 'Sản phẩm đang ẩn hoặc đã hết hàng') !== false) {
            return 'unavailable';
        }
        if (strpos($detail, 'Giỏ hàng chỉ có thể chứa tối đa 20') !== false) {
            return 'cart_full';
        }
        if (strpos($detail, 'Sản phẩm không có trong giỏ hàng') !== false) {
            return 'cart_item_missing';
        }
        return 'cart_error';
    }

    private function cartPostUrl(string $message): string
    {
        if (array_key_exists('danh_muc', $_POST)
            && array_key_exists('sap_xep', $_POST)
            && array_key_exists('trang', $_POST)) {
            $categoryId = $this->cartIntegerInput($_POST['danh_muc'], 0, PHP_INT_MAX) ?? 0;
            $sort = is_string($_POST['sap_xep'] ?? null)
                && in_array($_POST['sap_xep'], ['moi_nhat', 'gia_tang', 'gia_giam'], true)
                ? $_POST['sap_xep']
                : 'moi_nhat';
            $page = min(1000000, $this->cartIntegerInput($_POST['trang'], 1, PHP_INT_MAX) ?? 1);
            return route('souvenirs', [
                'danh_muc' => $categoryId,
                'sap_xep' => $sort,
                'trang' => $page,
                'msg' => $message,
            ]);
        }
        return route('giohang', ['msg' => $message]);
    }

    private function khongTimThay(string $thongBao = 'Trang bạn tìm không tồn tại.'): void
    {
        http_response_code(404);
        View::render('error', [
            'title' => 'Không tìm thấy',
            'thong_bao' => $thongBao,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function chuyenTrang(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
