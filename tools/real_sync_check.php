<?php
declare(strict_types=1);

/**
 * Kiểm thử đồng bộ thật với API-Football (tốn request của gói Free).
 * Chạy: php tools/real_sync_check.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Services\SyncService;
use App\Repositories\MatchRepository;
use App\Repositories\SyncRepository;

function ln(string $s): void { echo $s . PHP_EOL; }

ln('provider = ' . Config::string('api', 'provider'));
ln('base_url = ' . Config::string('api', 'base_url'));

$sync = new SyncService();

$r1 = $sync->run('LIVE');
ln('Sync LIVE   : ' . ($r1['ma'] ?? '?') . ' | ghi=' . ($r1['so_ban_ghi'] ?? 0) . ' | ' . ($r1['thong_bao'] ?? ''));

$r2 = $sync->run('FIXTURES');
ln('Sync FIXT : ' . ($r2['ma'] ?? '?') . ' | ghi=' . ($r2['so_ban_ghi'] ?? 0) . ' | ' . ($r2['thong_bao'] ?? ''));

$tk = (new SyncRepository())->thongKe();
ln('Thống kê: giải=' . ($tk['so_giai'] ?? 0) . ' đội=' . ($tk['so_doi'] ?? 0) . ' trận=' . ($tk['so_tran'] ?? 0));

$list = (new MatchRepository())->getList(['gioi_han' => 5]);
ln('5 trận đầu (theo giờ):');
foreach ($list['items'] as $m) {
    ln('  - ' . $m->getDoiNhaTen() . ' ' . $m->getTiSoNha() . '-' . $m->getTiSoKhach() . ' '
        . $m->getDoiKhachTen() . ' [' . $m->getTrangThai() . '] ' . $m->getGiaiTen());
}
