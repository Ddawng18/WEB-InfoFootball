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

    public function getDetail(int $id): ?FootballMatch
    {
        return $this->repo->getById($id);
    }

    /** @return array<int, array<string, mixed>> */
    public function getEvents(int $id): array
    {
        return $this->repo->getEvents($id);
    }

    /**
     * Đồng bộ theo yêu cầu (dùng cho nút "Đồng bộ ngay" và khi lọc lịch).
     * @return array<string, mixed>
     */
    public function dongBoNgay(string $loai = 'FIXTURES'): array
    {
        return $this->sync->run($loai);
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
        ]);
    }
}
