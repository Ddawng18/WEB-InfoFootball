<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\SyncRepository;

/**
 * Khung đầm (điểm duy nhất gọi API ngoài) rồi ghi vào CSDL qua routine.
 * Cơ chế: TTL + khóa chống chạy trùng; API lỗi thì giữ nguyên dữ liệu cũ.
 */
final class SyncService
{
    private FootballDataProvider $provider;
    private SyncRepository $repo;

    public function __construct(?FootballDataProvider $provider = null, ?SyncRepository $repo = null)
    {
        $this->provider = $provider ?? self::taoProvider();
        $this->repo = $repo ?? new SyncRepository();
    }

    public static function taoProvider(): FootballDataProvider
    {
        return match (strtolower(Config::string('api', 'provider', 'sample'))) {
            'livescore' => new LiveScoreApiClient(),
            'apifootball' => new ApiFootballClient(),
            default => new SampleDataProvider(),
        };
    }

    private function ttl(string $loai): int
    {
        return match ($loai) {
            'LIVE' => Config::int('sync', 'ttl_live', 60),
            'FIXTURES' => Config::int('sync', 'ttl_fixtures', 21600),
            default => Config::int('sync', 'ttl_catalog', 86400),
        };
    }

    /**
     * Chạy đồng bộ một loại. Trả về tóm tắt để API/Admin hiển thị.
     * @return array<string, mixed>
     */
    public function run(string $loai): array
    {
        $ttl = $this->ttl($loai);
        $bat = $this->repo->batDau($loai, $ttl);

        if ($bat['ma'] === 'CHUA_HET_HAN') {
            return ['ma' => 'CHUA_HET_HAN', 'loai' => $loai, 'nhat_ky_id' => null,
                    'thong_bao' => 'Chưa hết hạn đồng bộ (TTL ' . $ttl . 's).'];
        }
        if ($bat['ma'] === 'DANG_CHAY') {
            return ['ma' => 'DANG_CHAY', 'loai' => $loai, 'nhat_ky_id' => $bat['nhat_ky_id'],
                    'thong_bao' => 'Một tiến trình đồng bộ đang chạy.'];
        }
        if ($bat['ma'] !== 'OK' || $bat['nhat_ky_id'] === null) {
            return ['ma' => 'LOI_HE_THONG', 'loai' => $loai, 'nhat_ky_id' => null,
                    'thong_bao' => 'Không tạo được dòng nhật ký đồng bộ.'];
        }

        $nhatKyId = $bat['nhat_ky_id'];

        try {
            $bundle = $this->lay($loai);
            $coDuLieu = $this->coDuLieu($bundle);
            $thongKe = $this->ghiVaoDb($bundle);
        } catch (\Throwable $e) {
            $this->repo->ketThuc($nhatKyId, 'LOI', 0, 0, mb_substr($e->getMessage(), 0, 400, 'UTF-8'));
            return ['ma' => 'LOI', 'loai' => $loai, 'nhat_ky_id' => $nhatKyId,
                    'thong_bao' => 'Lỗi khi đồng bộ: ' . $e->getMessage()];
        }

        $trangThai = $coDuLieu ? 'THANH_CONG' : 'RONG';
        $thongBao = $trangThai === 'RONG'
            ? 'Nguồn trả về rỗng; giữ nguyên dữ liệu cũ.'
            : 'Đã đồng bộ ' . $thongKe['so_ban_ghi'] . ' bản ghi, bỏ qua ' . $thongKe['so_bo_qua'] . '.';

        $this->repo->ketThuc($nhatKyId, $trangThai, $thongKe['so_ban_ghi'], $thongKe['so_bo_qua'], $thongBao);

        return [
            'ma' => $trangThai,
            'loai' => $loai,
            'nhat_ky_id' => $nhatKyId,
            'so_ban_ghi' => $thongKe['so_ban_ghi'],
            'so_bo_qua' => $thongKe['so_bo_qua'],
            'thong_bao' => $thongBao,
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function lay(string $loai): array
    {
        return match ($loai) {
            'LIVE' => $this->provider->fetchLive(),
            'FIXTURES' => $this->provider->fetchFixtures(null),
            default => $this->provider->fetchLeagues(),
        };
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $bundle
     */
    private function coDuLieu(array $bundle): bool
    {
        foreach (['countries', 'leagues', 'teams', 'matches'] as $k) {
            if (!empty($bundle[$k])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $bundle
     * @return array{so_ban_ghi:int, so_bo_qua:int}
     */
    private function ghiVaoDb(array $bundle): array
    {
        $nguon = $this->provider->name();
        $soBanGhi = 0;
        $soBoQua = 0;

        foreach ($bundle['countries'] ?? [] as $c) {
            if ($this->repo->upsertQuocGia((string) $c['ma'], (string) $c['ten']) === 'OK') {
                $soBanGhi++;
            }
        }

        foreach ($bundle['leagues'] ?? [] as $l) {
            $ma = $this->repo->upsertGiaiDau(
                $nguon,
                (string) $l['ma'],
                (string) $l['ten'],
                isset($l['ma_quoc_gia']) ? (string) $l['ma_quoc_gia'] : null,
                (string) ($l['mua_giai'] ?? '')
            );
            $ma === 'OK' ? $soBanGhi++ : $soBoQua++;
        }

        foreach ($bundle['teams'] ?? [] as $t) {
            $ma = $this->repo->upsertDoiBong(
                $nguon,
                (string) $t['ma'],
                (string) $t['ten'],
                isset($t['ma_quoc_gia']) ? (string) $t['ma_quoc_gia'] : null,
                isset($t['logo_url']) ? (string) $t['logo_url'] : ''
            );
            $ma === 'OK' ? $soBanGhi++ : $soBoQua++;
        }

        foreach ($bundle['matches'] ?? [] as $m) {
            $kq = $this->repo->upsertTranDau(
                $nguon,
                (string) $m['ma'],
                (string) $m['ma_giai'],
                (string) $m['ma_doi_nha'],
                (string) $m['ma_doi_khach'],
                (string) $m['thoi_gian_utc'],
                (string) $m['trang_thai'],
                (int) ($m['phut'] ?? 0),
                (int) ($m['ti_so_nha'] ?? 0),
                (int) ($m['ti_so_khach'] ?? 0)
            );
            if (($kq['ma'] ?? '') === 'OK' && ($kq['hanh_dong'] ?? '') !== 'BO_QUA') {
                $soBanGhi++;
            } else {
                $soBoQua++;
            }
        }

        return ['so_ban_ghi' => $soBanGhi, 'so_bo_qua' => $soBoQua];
    }
}
