<?php
declare(strict_types=1);

/**
 * Dọn dữ liệu MẪU (nguon = 'SAMPLE') để chỉ còn dữ liệu thật từ API-Football.
 *
 * Vì sao cần: dữ liệu mẫu có mã riêng (nguon='SAMPLE', ma_api='M001'...), nên đồng bộ thật
 * (nguon='APIFOOTBALL') KHÔNG cập nhật chúng -> các trận "đang đá" của dữ liệu mẫu đứng yên
 * mãi ở một phút cố định.
 *
 * Đây là script bảo trì CSDL (không nằm trong kiểm tra "không SQL trong .php" của ứng dụng).
 * Khôi phục dữ liệu mẫu: đặt config provider = "sample" rồi đồng bộ (hoặc import lại schema+seed).
 *
 * Chạy: php tools/purge_sample.php
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$mysqli = Database::getInstance()->raw();

$buoc = [
    'Sự kiện trận mẫu' => "DELETE FROM su_kien_tran
        WHERE tran_dau_id IN (SELECT tran_dau_id FROM tran_dau WHERE nguon = 'SAMPLE')",
    'Đội yêu thích (đội mẫu)' => "DELETE FROM doi_bong_yeu_thich
        WHERE doi_bong_id IN (SELECT doi_bong_id FROM doi_bong WHERE nguon = 'SAMPLE')",
    'Bảng xếp hạng (giải mẫu)' => "DELETE FROM bang_xep_hang
        WHERE giai_dau_id IN (SELECT giai_dau_id FROM giai_dau WHERE nguon = 'SAMPLE')",
    'Trận mẫu' => "DELETE FROM tran_dau WHERE nguon = 'SAMPLE'",
    'Đội mẫu' => "DELETE FROM doi_bong WHERE nguon = 'SAMPLE'",
    'Giải mẫu' => "DELETE FROM giai_dau WHERE nguon = 'SAMPLE'",
    'Quốc gia mẫu (VN/JP/EN)' => "DELETE FROM quoc_gia WHERE ma_api IN ('VN','JP','EN')",
    'Tin tức mẫu (ADMIN)' => "DELETE FROM tin_tuc WHERE nguon = 'ADMIN'",
    'Danh mục tin mẫu' => "DELETE FROM danh_muc_tin WHERE ten IN ('Giải đấu','Đội bóng')",
    'Nhật ký đồng bộ mẫu' => "DELETE FROM nhat_ky_dong_bo WHERE thong_bao LIKE '%dữ liệu mẫu%'",
];

echo "== Dọn dữ liệu mẫu ==", PHP_EOL;
foreach ($buoc as $nhan => $sql) {
    if ($mysqli->query($sql) === false) {
        echo '  [LỖI] ' . $nhan . ': ' . $mysqli->error . PHP_EOL;
        exit(1);
    }
    echo '  - ' . str_pad($nhan, 26) . ' xóa ' . $mysqli->affected_rows . ' dòng' . PHP_EOL;
}

echo PHP_EOL, '== Còn lại (thật) ==', PHP_EOL;
foreach ([
    'trận' => 'SELECT COUNT(*) c FROM tran_dau',
    'trận đang đá' => "SELECT COUNT(*) c FROM tran_dau WHERE trang_thai IN ('DANG_DA','NGHI_GIUA_HIEP')",
    'đội' => 'SELECT COUNT(*) c FROM doi_bong',
    'giải' => 'SELECT COUNT(*) c FROM giai_dau',
    'quốc gia' => 'SELECT COUNT(*) c FROM quoc_gia',
] as $nhan => $sql) {
    $r = $mysqli->query($sql)->fetch_assoc();
    echo '  - ' . str_pad($nhan, 14) . $r['c'] . PHP_EOL;
}
echo 'Xong.', PHP_EOL;
