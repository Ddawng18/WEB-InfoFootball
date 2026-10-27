<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\FootballProductRepository;
use App\Repositories\SyncRepository;

/**
 * Điều phối thu thập sản phẩm bóng đá: TTL, nhật ký đồng bộ, lọc bóng đá,
 * chuẩn hóa, upsert và ẩn sản phẩm cũ.
 */
final class FootballProductFeedService
{
    private FootballProductProvider $provider;
    private FootballProductRepository $products;
    private SyncRepository $sync;

    public function __construct(
        ?FootballProductProvider $provider = null,
        ?FootballProductRepository $products = null,
        ?SyncRepository $sync = null
    ) {
        $this->provider = $provider ?? self::createProvider();
        $this->products = $products ?? new FootballProductRepository();
        $this->sync = $sync ?? new SyncRepository();
    }

    public static function createProvider(): FootballProductProvider
    {
        return new TikiFootballProductProvider();
    }

    /**
     * @param int|null $ttl null = dùng TTL trong config; -1 = bắt buộc chạy lại.
     * @return array<string, mixed>
     */
    public function run(?int $ttl = null): array
    {
        @set_time_limit(120);
        $ttl ??= max(0, Config::int('san_pham', 'ttl', 1800));
        $start = microtime(true);
        $retentionDays = min(30, max(1, Config::int('san_pham', 'retention_days', 7)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $retentionDays * 86400);
        $begun = $this->sync->batDau('SAN_PHAM', $ttl);
        if ($begun['ma'] === 'CHUA_HET_HAN') {
            return ['ma' => 'CHUA_HET_HAN', 'so_ban_ghi' => 0,
                    'thong_bao' => 'Chưa hết hạn kiểm tra giá sản phẩm (TTL ' . $ttl . 's).'];
        }
        if ($begun['ma'] === 'DANG_CHAY') {
            return ['ma' => 'DANG_CHAY', 'so_ban_ghi' => 0,
                    'thong_bao' => 'Một tiến trình cập nhật sản phẩm đang chạy.'];
        }
        if ($begun['ma'] !== 'OK' || $begun['nhat_ky_id'] === null) {
            return ['ma' => 'LOI_HE_THONG', 'so_ban_ghi' => 0,
                    'thong_bao' => 'Không tạo được nhật ký cập nhật sản phẩm.'];
        }

        $logId = $begun['nhat_ky_id'];
        $received = 0;
        $kept = 0;
        $providerName = $this->provider->name();

        try {
            $limit = min(600, max(1, Config::int('san_pham', 'max_san_pham', 400)));
            $maxSeconds = min(120, max(5, Config::int('san_pham', 'max_seconds', 60)));
            $deadline = $start + $maxSeconds;

            $fetchError = '';
            try {
                $items = $this->provider->fetch($limit, $deadline);
            } catch (\Throwable $e) {
                $items = [];
                $fetchError = $e->getMessage();
            }
            $received = count($items);
            $sources = [];
            foreach ($items as $item) {
                if ($this->products->upsert($item) === 'OK') {
                    $kept++;
                    $sources[(string) $item['nguon']] = true;
                }
            }

            foreach (array_keys($sources) as $source) {
                $this->products->hideOlder($source, $cutoff);
            }

            $status = $kept > 0 ? 'THANH_CONG' : 'RONG';
            $message = 'Nhận ' . $received . ', giữ ' . $kept . ' sản phẩm sau lọc';
            $errors = method_exists($this->provider, 'errors') ? $this->provider->errors() : [];
            if ($fetchError !== '') {
                array_unshift($errors, $fetchError);
            }
            if ($errors !== []) {
                $message .= '; lỗi: ' . mb_substr(implode(' | ', $errors), 0, 280, 'UTF-8');
            }
            $this->sync->ketThuc($logId, $status, $kept, max(0, $received - $kept), $message);
            return [
                'ma' => $status,
                'so_ban_ghi' => $kept,
                'nguon' => $providerName,
                'thong_bao' => $message,
            ];
        } catch (\Throwable $e) {
            $message = 'Nhận ' . $received . ', giữ ' . $kept . ' sản phẩm sau lọc; lỗi: '
                . mb_substr($e->getMessage(), 0, 300, 'UTF-8');
            $this->sync->ketThuc($logId, 'LOI', $kept, max(0, $received - $kept), $message);
            return ['ma' => 'LOI', 'so_ban_ghi' => $kept,
                    'nguon' => $providerName, 'thong_bao' => $message];
        }
    }
}
