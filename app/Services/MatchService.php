<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Time;
use App\Models\FootballMatch;
use App\Repositories\MatchRepository;

/** Điều phối nghiệp vụ đọc trận đấu cho các trang (F1, F2, F3). */
final class MatchService
{
    private MatchRepository $repo;
    private SyncService $sync;

    public function __construct(?MatchRepository $repo = null, ?SyncService $sync = null)
    {
        $this->repo = $repo ?? new MatchRepository();
        $this->sync = $sync ?? new SyncService();
    }

    /**
     * Dữ liệu trang chủ: 3 nhóm trận, mỗi nhóm phân trang riêng.
     * @param array{dang?:int, sap?:int, kt?:int} $trang
     * @return array<string, mixed>
     */
    public function getHome(array $trang = [], int $moiTrang = 5): array
    {
        $sync = $this->sync->run('LIVE');
        $now = gmdate('Y-m-d H:i:s');

        $pDang = max(1, (int) ($trang['dang'] ?? 1));
        $pSap = max(1, (int) ($trang['sap'] ?? 1));
        $pKt = max(1, (int) ($trang['kt'] ?? 1));

        $dang = $this->repo->getList([
            'nhom' => 'DANG', 'gioi_han' => $moiTrang, 'bo_qua' => ($pDang - 1) * $moiTrang,
        ]);
        $sap = $this->repo->getList([
            'trang_thai' => 'SAP_DIEN_RA', 'tu_utc' => $now,
            'gioi_han' => $moiTrang, 'bo_qua' => ($pSap - 1) * $moiTrang,
        ]);
        $kt = $this->repo->getList([
            'trang_thai' => 'KET_THUC', 'den_utc' => $now, 'sap_xep' => 'DESC',
            'gioi_han' => $moiTrang, 'bo_qua' => ($pKt - 1) * $moiTrang,
        ]);

        $capNhat = '';
        foreach (array_merge($dang['items'], $sap['items'], $kt['items']) as $m) {
            if ($m->getCapNhatLuc() > $capNhat) {
                $capNhat = $m->getCapNhatLuc();
            }
        }

        $nhom = static function (array $res, int $trang, int $moiTrang): array {
            $tong = $res['tong'];
            return [
                'items' => $res['items'],
                'tong' => $tong,
                'trang' => $trang,
                'so_trang' => max(1, (int) ceil($tong / max(1, $moiTrang))),
            ];
        };

        $loiApi = in_array($sync['ma'], ['LOI', 'LOI_HE_THONG'], true);

        return [
            'dang_da' => $nhom($dang, $pDang, $moiTrang),
            'sap_da' => $nhom($sap, $pSap, $moiTrang),
            'ket_thuc' => $nhom($kt, $pKt, $moiTrang),
            'cap_nhat_luc' => $capNhat,
            'du_lieu_cu' => $loiApi,
            'thong_bao' => $sync['thong_bao'] ?? '',
            'sync_ma' => $sync['ma'],
            'moi_trang' => $moiTrang,
        ];
    }

    /**
     * Danh sách trận có lọc + phân trang. $f như MatchRepository::getList.
     * @param array<string, mixed> $f
     * @return array{items: FootballMatch[], tong: int}
     */
    public function getList(array $f): array
    {
        return $this->repo->getList($f);
    }

    /**
     * Bộ lọc dùng chung cho trang chủ (HTML + AJAX) để tránh hai nơi lệch logic.
     * @return array<string, mixed>
     */
    private function homeFilter(string $tt, ?int $giaiId, int $trang, int $perPage): array
    {
        $filter = ['giai_id' => $giaiId, 'sap_xep' => 'GIAI'];
        if ($tt === 'live') {
            $filter['nhom'] = 'DANG';
        } elseif ($tt === 'finished') {
            $filter['trang_thai'] = 'KET_THUC';
        } elseif ($tt === 'scheduled') {
            $filter['trang_thai'] = 'SAP_DIEN_RA';
        }
        $filter['gioi_han'] = $perPage;
        $filter['bo_qua'] = (max(1, $trang) - 1) * $perPage;
        return $filter;
    }

    /** @return array{items: FootballMatch[], tong: int} */
    public function getHomeList(string $tt, ?int $giaiId, int $trang, int $perPage): array
    {
        return $this->repo->getList($this->homeFilter($tt, $giaiId, $trang, $perPage));
    }

    /** @return array{all:int, live:int, finished:int, scheduled:int} */
    public function getHomeCounts(?int $giaiId): array
    {
        $base = ['giai_id' => $giaiId];
        return [
            'all' => $this->repo->getList($base + ['gioi_han' => 1])['tong'],
            'live' => $this->repo->getList($base + ['nhom' => 'DANG', 'gioi_han' => 1])['tong'],
            'finished' => $this->repo->getList($base + ['trang_thai' => 'KET_THUC', 'gioi_han' => 1])['tong'],
            'scheduled' => $this->repo->getList($base + ['trang_thai' => 'SAP_DIEN_RA', 'gioi_han' => 1])['tong'],
        ];
    }

    public function getDetail(int $id): ?FootballMatch
    {
        return $this->repo->getById($id);
    }

    /**
     * Đọc chi tiết trận; nếu trận đang/sắp diễn ra thì đồng bộ LIVE trước
     * để trang chi tiết không hiển thị trạng thái, tỉ số cũ.
     */
    public function getDetailFresh(int $id): ?FootballMatch
    {
        $tran = $this->repo->getById($id);
        if ($tran === null || !$this->canRefreshLive($tran)) {
            return $tran;
        }
        $this->sync->run('LIVE');
        return $this->repo->getById($id);
    }

    private function canRefreshLive(FootballMatch $tran): bool
    {
        if (in_array($tran->getTrangThai(), ['DANG_DA', 'NGHI_GIUA_HIEP'], true)) {
            return true;
        }
        if (in_array($tran->getTrangThai(), ['KET_THUC', 'HUY'], true)) {
            return false;
        }
        $batDau = strtotime($tran->getThoiGianBatDau() . ' UTC');
        if ($batDau === false) {
            return true;
        }
        $now = time();
        return $now >= $batDau - 1800 && $now <= $batDau + 4 * 3600;
    }

    /** @return array<int, array<string, mixed>> */
    public function getEvents(int $id): array
    {
        return $this->repo->getEvents($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function getStandings(int $giaiId): array
    {
        return $this->repo->getStandings($giaiId);
    }

    /**
     * Đồng bộ theo yêu cầu (dùng cho nút "Đồng bộ ngay" và khi lọc lịch).
     * @return array<string, mixed>
     */
    public function dongBoNgay(string $loai = 'FIXTURES', bool $force = false): array
    {
        return $this->sync->run($loai, $force);
    }

    /** Lọc lịch thi đấu theo ngày (Y-m-d theo giờ VN) + giải + trạng thái. */
    public function getSchedule(string $ngay, ?int $giaiId, ?string $trangThai, int $trang = 1, int $moiTrang = 20): array
    {
        [$tu, $den] = Time::ngaySangKhoangUtc($ngay);
        return $this->repo->getList([
            'tu_utc' => $tu,
            'den_utc' => $den,
            'giai_id' => $giaiId,
            'trang_thai' => $trangThai,
            'gioi_han' => $moiTrang,
            'bo_qua' => ($trang - 1) * $moiTrang,
            'sap_xep' => 'GIAI',
        ]);
    }
}
