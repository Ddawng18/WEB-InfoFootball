<?php
/**
 * Chân trang (footer): bản quyền, công bố nguồn dữ liệu và thời điểm đồng bộ.
 */
$provider = App\Core\Config::string('api', 'provider', 'espn');
$nhanNguon = ['espn' => 'ESPN'][$provider] ?? $provider;
?>
<footer class="ffbi-footer mt-auto py-4">
    <div class="container">
        <div class="row g-3 small">
            <div class="col-md-8">
                <div class="fw-bold text-white mb-1">FastFoot<span class="text-danger">BalInfo</span></div>
                <div class="text-secondary">
                    Đồ án học phần Phát triển ứng dụng Web (UEH) &copy; <?= date('Y') ?>.
                </div>
                <div class="text-secondary mt-1">
                    Dữ liệu bóng đá đồng bộ từ nhà cung cấp bên thứ ba; tin tức tổng hợp từ VnExpress (chỉ dẫn nguồn).
                    Sản phẩm được tổng hợp từ các sàn thương mại điện tử; giá và tình trạng hàng do sàn quyết định.
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="text-secondary"><i class="bi bi-hdd-network"></i> Nguồn bóng đá: <?= h($nhanNguon) ?></div>
                <div class="text-secondary"><i class="bi bi-arrow-repeat"></i> Đồng bộ gần nhất: <?= h(dong_bo_gan_nhat()) ?></div>
            </div>
        </div>
    </div>
</footer>
