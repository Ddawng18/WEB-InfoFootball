<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\CatalogRepository;
use App\Repositories\SyncRepository;

/**
 * Nghiệp vụ tin tức: cào tin từ VnExpress rồi lưu vào bảng tin_tuc qua routine.
 * Dùng chung cơ chế TTL + nhật ký đồng bộ (loại 'NEWS') để tránh cào quá thường xuyên.
 */
final class NewsService
{
    private VnExpressCrawler $crawler;
    private CatalogRepository $repo;
    private SyncRepository $sync;

    public function __construct(
        ?VnExpressCrawler $crawler = null,
        ?CatalogRepository $repo = null,
        ?SyncRepository $sync = null
    ) {
        $this->crawler = $crawler ?? new VnExpressCrawler();
        $this->repo = $repo ?? new CatalogRepository();
        $this->sync = $sync ?? new SyncRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function run(int $gioiHan = 30): array
    {
        $ttl = Config::int('sync', 'ttl_news', 10800);
        $bat = $this->sync->batDau('NEWS', $ttl);

        if ($bat['ma'] === 'CHUA_HET_HAN') {
            return ['ma' => 'CHUA_HET_HAN', 'so_ban_ghi' => 0,
                    'thong_bao' => 'Chưa hết hạn cào tin (TTL ' . $ttl . 's).'];
        }
        if ($bat['ma'] === 'DANG_CHAY') {
            return ['ma' => 'DANG_CHAY', 'so_ban_ghi' => 0, 'thong_bao' => 'Một tiến trình cào tin đang chạy.'];
        }
        if ($bat['ma'] !== 'OK' || $bat['nhat_ky_id'] === null) {
            return ['ma' => 'LOI_HE_THONG', 'so_ban_ghi' => 0, 'thong_bao' => 'Không tạo được nhật ký cào tin.'];
        }

        $nhatKyId = $bat['nhat_ky_id'];

        try {
            $items = $this->crawler->fetch($gioiHan);
        } catch (\Throwable $e) {
            $this->sync->ketThuc($nhatKyId, 'LOI', 0, 0, mb_substr($e->getMessage(), 0, 400, 'UTF-8'));
            return ['ma' => 'LOI', 'so_ban_ghi' => 0, 'thong_bao' => 'Lỗi cào tin: ' . $e->getMessage()];
        }

        $soBanGhi = 0;
        foreach ($items as $it) {
            $ma = $this->repo->upsertTinTuc(
                VnExpressCrawler::NGUON,
                (string) $it['ma'],
                (string) $it['tieu_de'],
                (string) $it['tom_tat'],
                (string) $it['anh_url'],
                (string) $it['duong_dan'],
                $it['ngay_dang'] !== null ? (string) $it['ngay_dang'] : null,
                (string) $it['danh_muc'],
                (int) $it['thu_tu']
            );
            if ($ma === 'OK') {
                $soBanGhi++;
            }
        }

        $trangThai = $soBanGhi === 0 ? 'RONG' : 'THANH_CONG';
        $thongBao = $trangThai === 'RONG'
            ? 'Không lấy được tin nào từ VnExpress.'
            : 'Đã cào ' . $soBanGhi . ' tin từ VnExpress.';

        $this->sync->ketThuc($nhatKyId, $trangThai, $soBanGhi, 0, $thongBao);

        return ['ma' => $trangThai, 'so_ban_ghi' => $soBanGhi, 'nhat_ky_id' => $nhatKyId, 'thong_bao' => $thongBao];
    }
}
