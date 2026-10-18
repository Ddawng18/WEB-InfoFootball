<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Time;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CatalogRepository;
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
    private AuthService $auth;
    private FavoriteService $favorites;

    public function __construct()
    {
        $this->matches = new MatchService();
        $this->catalog = new CatalogRepository();
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

        $lgRes = $this->catalog->getLeagues(null, null, null, $moiGiai, ($trangGiai - 1) * $moiGiai);

        View::render('home', [
            'title' => 'Bảng tỉ số',
            'items' => $res['items'],
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
            'leagues' => $this->catalog->getLeagues()['items'],
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
        View::render('match', [
            'title' => $tran->getDoiNhaTen() . ' vs ' . $tran->getDoiKhachTen(),
            'tran' => $tran,
            'su_kien' => $this->matches->getEvents($id),
            'user' => $this->auth->currentUser(),
        ]);
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
