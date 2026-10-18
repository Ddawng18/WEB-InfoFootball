<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Captcha;
use App\Core\Csrf;
use App\Core\Time;
use App\Core\Validator;
use App\Core\View;
use App\Models\FootballMatch;
use App\Repositories\CatalogRepository;
use App\Repositories\FootballProductRepository;
use App\Repositories\NewsRepository;
use App\Services\AuthService;
use App\Services\CalendarService;
use App\Services\FavoriteService;
use App\Services\MatchService;
use App\Services\NewsService;
use App\Services\SyncService;

/** Điều khiển các trang HTML (View). Chọn view, truyền dữ liệu từ Service. */
final class PageController
{
    private MatchService $matches;
    private CatalogRepository $catalog;
    private NewsRepository $news;
    private AuthService $auth;
    private FavoriteService $favorites;
    private CalendarService $calendar;

    public function __construct()
    {
        $this->matches = new MatchService();
        $this->catalog = new CatalogRepository();
        $this->news = new NewsRepository();
        $this->auth = new AuthService();
        $this->favorites = new FavoriteService();
        $this->calendar = new CalendarService();
    }

    public function dispatch(string $route): void
    {
        if ($this->auth->daDangNhap()) {
            try {
                $this->calendar->dongBoNhac((int) $this->auth->taiKhoanId());
            } catch (\Throwable $e) {
                error_log('Không tạo được thông báo nhắc trận: ' . $e->getMessage());
            }
        }

        match ($route) {
            'home' => $this->home(),
            'lich' => $this->lich(),
            'tran' => $this->tran(),
            'giai' => $this->giai(),
            'doi' => $this->doi(),
            'doi-chitiet' => $this->doiChiTiet(),
            'bxh' => $this->bangXepHang(),
            'yeuthich' => $this->yeuThich(),
            'lich-ca-nhan' => $this->lichCaNhan(),
            'dangnhap' => $this->dangNhap(),
            'dangky' => $this->dangKy(),
            'dangxuat' => $this->dangXuat(),
            'tintuc' => $this->tinTuc(),
            'tintuc-chitiet' => $this->tinTucChiTiet(),
            'san-pham' => $this->sanPham(),
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

        $res = $this->matches->getHomeList($tt, $giaiId, $trang, $moiTrang);
        $dem = $this->matches->getHomeCounts($giaiId);

        $capNhat = '';
        foreach ($res['items'] as $m) {
            if ($m->getCapNhatLuc() > $capNhat) {
                $capNhat = $m->getCapNhatLuc();
            }
        }

        $loiApi = in_array($kqSync['ma'], ['LOI', 'LOI_HE_THONG'], true);

        $allLeagues = $this->catalog->getLeagues(null, null, null, 1000, 0)['items'];
        $lgRes = ['items' => array_slice($allLeagues, ($trangGiai - 1) * $moiGiai, $moiGiai), 'tong' => count($allLeagues)];
        $spotlight = $this->buildSpotlight($res['items']);

        View::render('home', [
            'title' => 'Bảng tỉ số',
            'items' => $res['items'],
            'spotlight' => $spotlight,
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

    /**
     * Chọn trận tâm điểm từ dữ liệu thật (không hard-code).
     * @param FootballMatch[] $items
     * @return array<string, mixed>|null
     */
    private function buildSpotlight(array $items): ?array
    {
        $now = time();
        $featured = null;
        $featuredKickoff = PHP_INT_MAX;
        $live = null;
        $latestFinished = null;
        $latestFinishedKickoff = 0;

        foreach ($items as $candidate) {
            $kickoff = strtotime($candidate->getThoiGianBatDau() . ' UTC');
            if ($kickoff === false) {
                continue;
            }
            if ($candidate->getTrangThai() === 'SAP_DIEN_RA' && $kickoff >= $now && $kickoff < $featuredKickoff) {
                $featured = $candidate;
                $featuredKickoff = $kickoff;
            }
            if ($candidate->isDangDienRa() && $kickoff <= $now && $kickoff >= $now - 3 * 60 * 60) {
                $live = $candidate;
            }
            if ($candidate->getTrangThai() === 'KET_THUC' && $kickoff <= $now && $kickoff > $latestFinishedKickoff) {
                $latestFinished = $candidate;
                $latestFinishedKickoff = $kickoff;
            }
        }

        $match = $featured ?? $live ?? $latestFinished ?? ($items[0] ?? null);
        if (!$match instanceof FootballMatch) {
            return null;
        }
        $kickoff = strtotime($match->getThoiGianBatDau() . ' UTC');
        if ($kickoff === false) {
            return null;
        }

        $status = $match->getTrangThai();
        $upcoming = $status === 'SAP_DIEN_RA' && $kickoff > $now;
        $lich = $this->calendarFlags($match->getId());
        $label = match ($status) {
            'DANG_DA', 'NGHI_GIUA_HIEP' => 'TRẬN ĐẤU ĐANG DIỄN RA',
            'CHO_CAP_NHAT' => 'TRẬN ĐẤU CHỜ CẬP NHẬT',
            'KET_THUC' => 'TRẬN ĐẤU GẦN NHẤT',
            'SAP_DIEN_RA' => 'TRẬN ĐẤU TÂM ĐIỂM SẮP TỚI',
            default => 'TRẬN ĐẤU TÂM ĐIỂM',
        };

        return [
            'home' => $match->getDoiNhaTen(),
            'home_logo' => $match->getDoiNhaLogo(),
            'away' => $match->getDoiKhachTen(),
            'away_logo' => $match->getDoiKhachLogo(),
            'league' => $match->getGiaiTen(),
            'kickoff' => $match->getThoiGianBatDau(),
            'status' => $status,
            'live' => $match->isDangDienRa(),
            'pending' => $status === 'CHO_CAP_NHAT',
            'co_live' => $match->getCoLive(),
            'minute' => $match->getPhut(),
            'home_score' => $match->getTiSoNha(),
            'away_score' => $match->getTiSoKhach(),
            'href' => route('tran', ['id' => $match->getId()]),
            'link_label' => 'Xem chi tiết trận',
            'countdown' => $upcoming ? $kickoff : null,
            'label' => $label,
            'tran_dau_id' => $match->getId(),
            'trong_lich' => $lich['trong_lich'],
            'nhac' => $lich['nhac'],
            'da_nhac' => $lich['da_nhac'],
        ];
    }

    /** @return array{trong_lich: bool, nhac: bool, da_nhac: bool} */
    private function calendarFlags(int $tranDauId): array
    {
        if (!$this->auth->daDangNhap() || $tranDauId <= 0) {
            return ['trong_lich' => false, 'nhac' => false, 'da_nhac' => false];
        }
        try {
            return $this->calendar->kiemTra((int) $this->auth->taiKhoanId(), $tranDauId);
        } catch (\Throwable $e) {
            error_log('Không đọc được lịch cá nhân: ' . $e->getMessage());
            return ['trong_lich' => false, 'nhac' => false, 'da_nhac' => false];
        }
    }

    private function lich(): void
    {
        $ngay = Validator::ngayYmd($_GET['ngay'] ?? null) ?? (new \DateTime('now', new \DateTimeZone(Time::tz())))->format('Y-m-d');
        $giaiId = Validator::soNguyenDuong($_GET['giai'] ?? null);
        $trangThai = Validator::maTrangThai($_GET['tt'] ?? null);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;

        // Hôm nay cần tỉ số/trạng thái live; ngày khác chỉ cần lịch thi đấu.
        $homNay = (new \DateTime('now', new \DateTimeZone(Time::tz())))->format('Y-m-d');
        $this->matches->dongBoNgay($ngay === $homNay ? 'LIVE' : 'FIXTURES');

        $res = $this->matches->getSchedule($ngay, $giaiId, $trangThai, $trang);
        View::render('schedule', [
            'title' => 'Lịch thi đấu',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'ngay' => $ngay,
            'giai_id' => $giaiId,
            'trang_thai' => $trangThai,
            'trang' => $trang,
            'leagues' => $this->catalog->getLeagues(null, null, null, 1000, 0)['items'],
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function tran(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? null);
        $tran = $id !== null ? $this->matches->getDetailFresh($id) : null;
        if ($tran === null) {
            $this->khongTimThay('Không tìm thấy trận đấu.');
            return;
        }
        $matchDetails = ['events' => [], 'lineups' => [], 'statistics' => [], 'h2h' => []];
        $lichCaNhan = $this->calendarFlags($id);
        $homeLogo = $tran->getDoiNhaLogo();
        $awayLogo = $tran->getDoiKhachLogo();
        $provider = \App\Services\SyncService::taoProvider();
        if (method_exists($provider, 'fetchMatchDetails')) {
            try {
                $matchDetails = $provider->fetchMatchDetails($tran->getMaApi());
            } catch (\Throwable $e) {
                error_log('Không tải được chi tiết trận từ API bóng đá: ' . $e->getMessage());
            }
        }

        $suKien = $this->matches->getEvents($id);
        if (isset($matchDetails['events']) && is_array($matchDetails['events']) && $matchDetails['events'] !== []) {
            $suKien = array_map(static function (array $event): array {
                $time = is_array($event['time'] ?? null) ? $event['time'] : [];
                $player = is_array($event['player'] ?? null) ? $event['player'] : [];
                $team = is_array($event['team'] ?? null) ? $event['team'] : [];
                  $loai = trim((string) ($event['type'] ?? ''));
                  $chiTiet = trim((string) ($event['detail'] ?? ''));
                  if ($loai === '') {
                      $loai = $chiTiet !== '' ? $chiTiet : 'Sự kiện';
                  }
                  return [
                      'phut' => (int) ($time['elapsed'] ?? 0),
                      'loai' => $loai,
                      'chi_tiet' => $chiTiet,
                      'cau_thu' => (string) ($player['name'] ?? ''),
                      'doi_ten' => (string) ($team['name'] ?? ''),
                  ];
            }, $matchDetails['events']);
        }
        $statRows = [];
        $statistics = isset($matchDetails['statistics']) && is_array($matchDetails['statistics'])
            ? $matchDetails['statistics']
            : [];
        $homeStats = is_array($statistics[0] ?? null) ? ($statistics[0]['statistics'] ?? []) : [];
        $awayStats = is_array($statistics[1] ?? null) ? ($statistics[1]['statistics'] ?? []) : [];
        $homeStats = is_array($homeStats) ? $homeStats : [];
        $awayStats = is_array($awayStats) ? $awayStats : [];
        foreach ($homeStats as $stat) {
            $tenChiSo = (string) ($stat['type'] ?? '');
            if ($tenChiSo === '') {
                continue;
            }
              foreach ($awayStats as $awayStat) {
                  if (($awayStat['type'] ?? '') === $tenChiSo) {
                      $homeValue = $stat['value'] ?? '—';
                      $awayValue = $awayStat['value'] ?? '—';
                      $homeNumber = (float) preg_replace('/[^0-9.]/', '', (string) $homeValue);
                      $awayNumber = (float) preg_replace('/[^0-9.]/', '', (string) $awayValue);
                      $total = $homeNumber + $awayNumber;
                      $share = $total > 0 ? ($homeNumber / $total) * 100 : 50.0;
                      $statRows[] = [$tenChiSo, $homeValue, $awayValue, $share];
                      break;
                  }
              }
          }

        View::render('match', [
            'title' => $tran->getDoiNhaTen() . ' vs ' . $tran->getDoiKhachTen(),
            'tran' => $tran,
            'su_kien' => $suKien,
            'stat_rows' => $statRows,
              'lineups' => isset($matchDetails['lineups']) && is_array($matchDetails['lineups'])
                  ? $matchDetails['lineups']
                  : [],
              'doi_dau' => isset($matchDetails['h2h']) && is_array($matchDetails['h2h'])
                  ? $matchDetails['h2h']
                  : [],
              'lich_ca_nhan' => $lichCaNhan,
              'home' => $tran->getDoiNhaTen(),
            'away' => $tran->getDoiKhachTen(),
            'home_logo_local' => $homeLogo,
            'away_logo_local' => $awayLogo,
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
        $regions = [];
        try {
            foreach ($this->catalog->getLeagues(null, null, null, 1000, 0)['items'] as $league) {
                $tenKhuVuc = $league->getKhuVuc();
                if ($tenKhuVuc !== '') {
                    $regions[$tenKhuVuc] = true;
                }
            }
        } catch (\Throwable $e) {
            error_log('Không đọc được danh sách khu vực: ' . $e->getMessage());
        }
        $regions = array_keys($regions);
        sort($regions);

        View::render('leagues', [
            'title' => 'Giải đấu',
            'leagues' => $res['items'],
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'khu_vuc' => $khuVuc,
            'regions' => $regions,
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

    private function bangXepHang(): void
    {
        $giaiId = Validator::soNguyenDuong($_GET['giai'] ?? null);
        if ($giaiId === null) {
            $this->khongTimThay('Thiếu giải đấu để xem bảng xếp hạng.');
            return;
        }
        try {
            $rows = $this->matches->getStandings($giaiId);
        } catch (\Throwable $e) {
            error_log('Không đọc được bảng xếp hạng: ' . $e->getMessage());
            $rows = [];
        }
        if ($rows === []) {
            $this->khongTimThay('Chưa có bảng xếp hạng cho giải này.');
            return;
        }
        View::render('standings', [
            'title' => 'Bảng xếp hạng',
            'giai_id' => $giaiId,
            'rows' => $rows,
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

    private function lichCaNhan(): void
    {
        if (!$this->auth->daDangNhap()) {
            $this->chuyenTrang(route('dangnhap', ['msg' => 'can-dang-nhap']));
            return;
        }
        $taiKhoanId = (int) $this->auth->taiKhoanId();
        $loc = Validator::chuoi($_GET['loc'] ?? 'all', 20);
        if (!in_array($loc, ['all', 'upcoming', 'past', 'calendar', 'reminder'], true)) {
            $loc = 'all';
        }
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 12;
        $res = $this->calendar->danhSach($taiKhoanId, $loc, $moiTrang, ($trang - 1) * $moiTrang);
        $thongBao = $this->calendar->thongBao($taiKhoanId, 12);
        View::render('calendar', [
            'title' => 'Lịch của tôi',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'trang' => $trang,
            'moi_trang' => $moiTrang,
            'loc' => $loc,
            'doi_da_them' => $this->calendar->doiDaThem($taiKhoanId, 24),
            'thong_bao' => $thongBao['items'],
            'chua_doc' => $thongBao['chua_doc'],
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
            $tenDangNhap = Validator::chuoi($_POST['username'] ?? '', 50);
            $kq = $this->auth->dangNhap(
                $tenDangNhap,
                (string) ($_POST['password'] ?? ''),
                (string) ($_SERVER['REMOTE_ADDR'] ?? '')
            );
            if ($kq['ma'] === 'OK') {
                $this->chuyenTrang($this->auth->laAdmin() ? route('admin') : route('home'));
            } else {
                $this->chuyenTrang(route('dangnhap', ['msg' => $kq['ma'], 'username' => $tenDangNhap]));
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
            $tenDangNhap = Validator::chuoi($_POST['username'] ?? '', 50);
            $email = Validator::chuoi($_POST['email'] ?? '', 120);
            $hoTen = Validator::chuoi($_POST['ho_ten'] ?? '', 120);
            if (!Captcha::check($_POST['captcha'] ?? null)) {
                $this->chuyenTrang(route('dangky', [
                    'msg' => 'captcha',
                    'username' => $tenDangNhap,
                    'email' => $email,
                    'ho_ten' => $hoTen,
                ]));
                return;
            }
            $kq = $this->auth->dangKy(
                $tenDangNhap,
                $email,
                $hoTen,
                (string) ($_POST['password'] ?? ''),
                (string) ($_POST['password2'] ?? '')
            );
            if ($kq['ma'] === 'OK') {
                $this->chuyenTrang(route('dangnhap', ['msg' => 'done']));
            } else {
                $this->chuyenTrang(route('dangky', [
                    'msg' => $kq['ma'],
                    'username' => $tenDangNhap,
                    'email' => $email,
                    'ho_ten' => $hoTen,
                ]));
            }
            return;
        }
        View::render('auth/register', [
            'title' => 'Đăng ký',
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 60),
            'captcha_question' => Captcha::question(),
            'user' => null,
        ]);
    }

    private function dangXuat(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !Csrf::check($_POST['_csrf'] ?? null)) {
            $this->chuyenTrang(route('home'));
            return;
        }
        $this->auth->dangXuat();
        $this->chuyenTrang(route('home'));
    }

    private function tinTuc(): void
    {
        $news = new NewsService();
        $ketQua = $news->run(30); // TTL-gated: cào tin mới nếu hết hạn
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 9;
        $res = $this->news->getNews($moiTrang, ($trang - 1) * $moiTrang);
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
        $tin = $id !== null ? $this->news->getNewsById($id) : null;
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

    private function sanPham(): void
    {
        $products = new FootballProductRepository();
        $categories = [];
        try {
            $categories = $products->getCategories();
        } catch (\Throwable $e) {
            error_log('Không đọc được danh mục sản phẩm: ' . $e->getMessage());
        }
        $categorySlugs = array_map(
            static fn (array $category): string => (string) $category['slug'],
            $categories
        );
        $rawCategory = $_GET['danh_muc'] ?? null;
        $requestedCategory = is_string($rawCategory) ? Validator::chuoi($rawCategory, 120) : '';
        $categorySlug = in_array($requestedCategory, $categorySlugs, true) ? $requestedCategory : '';

        $requestedSort = $_GET['sap_xep'] ?? 'moi_nhat';
        $sort = is_string($requestedSort)
            && in_array($requestedSort, ['moi_nhat', 'gia_tang', 'gia_giam', 'giam_gia'], true)
            ? $requestedSort
            : 'moi_nhat';
        $rawKeyword = $_GET['q'] ?? null;
        $keyword = is_string($rawKeyword) ? Validator::chuoi($rawKeyword, 100) : '';
        $requestedDomain = $_GET['ten_mien'] ?? '';
        $domain = is_string($requestedDomain)
            && preg_match('/^[a-z0-9.-]{1,100}$/', $requestedDomain) === 1
            ? strtolower($requestedDomain)
            : '';
        $page = min(1000000, Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1);
        $perPage = 12;

        $result = $products->getProducts($categorySlug, $keyword, $domain, $sort, $page, $perPage);
        $domains = [];
        try {
            $domains = $products->getDomains();
        } catch (\Throwable $e) {
            error_log('Không đọc được danh sách sàn: ' . $e->getMessage());
        }
        $totalAll = 0;
        foreach ($categories as $category) {
            $totalAll += (int) ($category['so_san_pham'] ?? 0);
        }

        $capNhat = '';
        foreach ($result['items'] as $item) {
            if ((string) ($item['lan_kiem_tra'] ?? '') > $capNhat) {
                $capNhat = (string) $item['lan_kiem_tra'];
            }
        }

        View::render('san_pham', [
            'title' => 'Sản phẩm bóng đá',
            'items' => $result['items'],
            'tong' => $result['tong'],
            'categories' => $categories,
            'category_slug' => $categorySlug,
            'sort' => $sort,
            'tu_khoa' => $keyword,
            'ten_mien' => $domain,
            'domains' => $domains,
            'tong_tat_ca' => $totalAll,
            'trang' => $page,
            'moi_trang' => $perPage,
            'cap_nhat_luc' => $capNhat,
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
