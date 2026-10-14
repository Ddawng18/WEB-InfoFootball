<?php
declare(strict_types=1);

/** Kiểm thử cào tin VnExpress. Chạy: php tools/news_check.php */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\NewsService;
use App\Repositories\CatalogRepository;

function ln(string $s): void { echo $s . PHP_EOL; }

$kq = (new NewsService())->run(30);
ln('Cào tin: ' . ($kq['ma'] ?? '?') . ' | số=' . ($kq['so_ban_ghi'] ?? 0) . ' | ' . ($kq['thong_bao'] ?? ''));

$items = (new CatalogRepository())->getNews(5, 0)['items'];
ln('5 tin đầu:');
foreach ($items as $t) {
    ln('  #' . $t['tin_id'] . ' [' . ($t['nguon'] ?? '') . '] ' . $t['tieu_de']);
    ln('      ảnh: ' . (($t['anh_url'] ?? '') !== '' ? 'có' : 'không') . ' | link: ' . ($t['duong_dan'] ?? ''));
}
