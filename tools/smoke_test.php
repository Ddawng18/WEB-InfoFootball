<?php
declare(strict_types=1);

/**
 * Smoke test tầng nghiệp vụ: kết nối DB, gọi routine qua Repository/Service.
 * Chạy: php tools/smoke_test.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Services\AuthService;
use App\Services\MatchService;
use App\Services\SyncService;
use App\Repositories\CatalogRepository;
use App\Core\Time;

function line(string $s): void { echo $s . PHP_EOL; }

line('== Bắt đầu smoke test ==');

$sync = new SyncService();
$r = $sync->run('CATALOG');
line('Sync CATALOG: ma=' . ($r['ma'] ?? '?') . ' so_ban_ghi=' . ($r['so_ban_ghi'] ?? 0) . ' | ' . ($r['thong_bao'] ?? ''));

$match = new MatchService();
$home = $match->getHome([], 5);
line('Trang chủ: đang đá=' . $home['dang_da']['tong']
    . ', sắp đá=' . $home['sap_da']['tong']
    . ', kết thúc=' . $home['ket_thuc']['tong']
    . ' (mỗi trang ' . count($home['dang_da']['items']) . ' dòng)'
    . ', cập nhật=' . Time::toDisplay($home['cap_nhat_luc'])
    . ', dữ liệu cũ=' . ($home['du_lieu_cu'] ? 'true' : 'false'));

$one = $match->getDetail(1);
line('Chi tiết trận 1: ' . ($one ? (string) $one : 'KHONG TIM THAY'));

$list = $match->getList(['trang_thai' => 'KET_THUC', 'gioi_han' => 10, 'bo_qua' => 0]);
line('Trận KET_THUC: ' . count($list['items']) . '/' . $list['tong']);

$cat = new CatalogRepository();
$res = $cat->getLeagues(null, null, null, 9, 0);
line('Giải đấu (trang 1, 9/trang): ' . count($res['items']) . '/' . $res['tong']
    . ' -> ' . implode(', ', array_map(static fn($l) => $l->getTen(), $res['items'])));

$team = $cat->getTeamById(1);
line('Đội 1: ' . ($team ? $team->getTen() . ' (W' . $team->getThang() . '/D' . $team->getHoa() . '/L' . $team->getThua() . ')' : 'KHONG TIM THAY'));

$auth = new AuthService();
$login = $auth->dangNhap('admin', 'Admin@123', '127.0.0.1');
line('Đăng nhập admin: ' . $login['ma'] . ' | ' . $login['thong_bao']);

line('== Smoke test xong ==');
