<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\PartnerProductRepository;
use App\Repositories\SyncRepository;

final class SouvenirFeedService
{
    private PartnerProductProvider $provider;
    private PartnerProductRepository $products;
    private SyncRepository $sync;

    public function __construct(
        ?PartnerProductProvider $provider = null,
        ?PartnerProductRepository $products = null,
        ?SyncRepository $sync = null
    ) {
        $this->provider = $provider ?? self::createProvider();
        $this->products = $products ?? new PartnerProductRepository();
        $this->sync = $sync ?? new SyncRepository();
    }

    public static function createProvider(): PartnerProductProvider
    {
        return strtolower(Config::string('accesstrade', 'provider', 'sample')) === 'accesstrade'
            ? new AccessTradeClient()
            : new SamplePartnerProvider();
    }

    public function run(): array
    {
        $ttl = max(0, Config::int('sync', 'ttl_souvenir_feed', 10800));
        $start = microtime(true);
        $cutoff = gmdate('Y-m-d H:i:s');
        $begun = $this->sync->batDau('SOUVENIR_FEED', $ttl);
        if ($begun['ma'] === 'CHUA_HET_HAN') {
            return ['ma' => 'CHUA_HET_HAN', 'so_ban_ghi' => 0];
        }
        if ($begun['ma'] === 'DANG_CHAY') {
            return ['ma' => 'DANG_CHAY', 'so_ban_ghi' => 0];
        }
        if ($begun['ma'] !== 'OK' || $begun['nhat_ky_id'] === null) {
            return ['ma' => 'LOI_HE_THONG', 'so_ban_ghi' => 0];
        }

        $logId = $begun['nhat_ky_id'];
        $received = 0;
        $kept = 0;
        try {
            $requestLimit = min(5, max(0, Config::int('accesstrade', 'max_yeu_cau', 5)));
            $productLimit = min(60, max(0, Config::int('accesstrade', 'max_san_pham', 60)));
            $pageSize = min(200, max(1, Config::int('accesstrade', 'page_size', 100)));
            $domains = array_values(array_filter(array_map(
                static fn (string $domain): string => strtolower(trim($domain)),
                explode(',', Config::string('accesstrade', 'domains', 'lazada.vn'))
            ), static fn (string $domain): bool => preg_match('/^[a-z0-9.-]{1,100}$/', $domain) === 1));
            if ($this->provider instanceof AccessTradeClient) {
                $this->provider->setDeadline($start + 20);
            }

            $requestCount = 0;
            foreach ($domains as $domain) {
                for ($page = 1; $requestCount < $requestLimit && $kept < $productLimit; $page++) {
                    if (microtime(true) - $start >= 20) {
                        throw new \RuntimeException('Đã hết thời gian đồng bộ AccessTrade.');
                    }
                    if ($requestCount > 0) {
                        usleep(300000);
                    }
                    if (microtime(true) - $start >= 20) {
                        throw new \RuntimeException('Đã hết thời gian đồng bộ AccessTrade.');
                    }
                    $items = $this->provider->fetch($page, $pageSize, $domain);
                    $requestCount++;
                    $pageReceived = $this->provider instanceof AccessTradeClient
                        ? $this->provider->receivedCount()
                        : count($items);
                    $received += $pageReceived;
                    foreach ($items as $item) {
                        if ($kept >= $productLimit) {
                            break;
                        }
                        if (microtime(true) - $start >= 20) {
                            throw new \RuntimeException('Đã hết thời gian đồng bộ AccessTrade.');
                        }
                        if ($this->products->upsert($this->provider->name(), $item) === 'OK') {
                            $kept++;
                        }
                    }
                    if ($pageReceived < $pageSize) {
                        break;
                    }
                }
                if ($requestCount >= $requestLimit || $kept >= $productLimit) {
                    break;
                }
            }

            if (microtime(true) - $start >= 20) {
                throw new \RuntimeException('Đã hết thời gian đồng bộ AccessTrade.');
            }
            if ($kept > 0) {
                $this->products->hideOlder($this->provider->name(), $cutoff);
            }
            $status = $kept > 0 ? 'THANH_CONG' : 'RONG';
            $message = 'Nhận ' . $received . ', giữ ' . $kept . ' sản phẩm sau lọc';
            $this->sync->ketThuc($logId, $status, $kept, max(0, $received - $kept), $message);
            return ['ma' => $status, 'so_ban_ghi' => $kept, 'thong_bao' => $message];
        } catch (\Throwable $e) {
            $message = 'Nhận ' . $received . ', giữ ' . $kept . ' sản phẩm sau lọc; lỗi: '
                . mb_substr($e->getMessage(), 0, 300, 'UTF-8');
            $this->sync->ketThuc($logId, 'LOI', $kept, max(0, $received - $kept), $message);
            return ['ma' => 'LOI', 'so_ban_ghi' => $kept, 'thong_bao' => $message];
        }
    }
}
