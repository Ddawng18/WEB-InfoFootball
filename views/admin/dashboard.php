<?php
/** @var array<string, mixed> $thong_ke @var array $nhat_ky */
?>
<h1 class="h3 mb-3"><i class="bi bi-speedometer2"></i> Bảng điều khiển quản trị</h1>

<div class="row g-3 mb-4">
    <?php
    $the = [
        ['Giải đấu', 'so_giai', 'bi-trophy', 'primary'],
        ['Đội bóng', 'so_doi', 'bi-people', 'success'],
        ['Trận đấu', 'so_tran', 'bi-calendar3', 'dark'],
        ['Tài khoản', 'so_tai_khoan', 'bi-person-badge', 'warning'],
        ['Đồng bộ OK (24h)', 'dong_bo_thanh_cong_24h', 'bi-check-circle', 'success'],
        ['Đồng bộ lỗi (24h)', 'dong_bo_loi_24h', 'bi-x-circle', 'danger'],
    ];
    foreach ($the as [$nhan, $khoa, $icon, $mau]): ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-bg-<?= h($mau) ?> h-100">
                <div class="card-body text-center">
                    <i class="bi <?= h($icon) ?> fs-3"></i>
                    <div class="fs-4 fw-bold"><?= (int) ($thong_ke[$khoa] ?? 0) ?></div>
                    <div class="small"><?= h($nhan) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Đồng bộ dữ liệu</div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Nút <strong>Đồng bộ ngay</strong> gọi API bên thứ ba từ máy chủ rồi upsert vào CSDL qua routine.
            Nếu chưa hết hạn TTL, hệ thống trả về "Chưa hết hạn đồng bộ".
        </p>
        <div class="d-flex flex-wrap gap-2">
            <form class="sync-form d-inline" data-loai="LIVE">
                <?= App\Core\Csrf::field() ?>
                <button class="btn btn-danger btn-sm" type="submit"><i class="bi bi-broadcast"></i> Đồng bộ trận đang đá</button>
            </form>
            <form class="sync-form d-inline" data-loai="FIXTURES">
                <?= App\Core\Csrf::field() ?>
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-calendar-week"></i> Đồng bộ lịch</button>
            </form>
            <form class="sync-form d-inline" data-loai="CATALOG">
                <?= App\Core\Csrf::field() ?>
                <button class="btn btn-secondary btn-sm" type="submit"><i class="bi bi-collection"></i> Đồng bộ giải/đội</button>
            </form>
            <form class="sync-form d-inline" data-loai="NEWS">
                <?= App\Core\Csrf::field() ?>
                <button class="btn btn-warning btn-sm" type="submit"><i class="bi bi-newspaper"></i> Cào tin VnExpress</button>
            </form>
        </div>
        <div id="sync-result" class="alert alert-info py-2 mt-3 d-none"></div>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Nhật ký đồng bộ gần nhất</div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0">
            <thead>
            <tr><th>#</th><th>Loại</th><th>Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th>
                <th class="text-end">Ghi</th><th class="text-end">Bỏ qua</th><th>Thông báo</th></tr>
            </thead>
            <tbody>
            <?php foreach ($nhat_ky as $r): ?>
                <tr>
                    <td><?= (int) $r['nhat_ky_id'] ?></td>
                    <td><?= h((string) $r['loai']) ?></td>
                    <td><?= h(App\Core\Time::toDisplay((string) $r['bat_dau'])) ?></td>
                    <td><?= h(App\Core\Time::toDisplay((string) ($r['ket_thuc'] ?? ''))) ?></td>
                    <td><span class="badge <?= ($r['trang_thai'] ?? '') === 'THANH_CONG' ? 'text-bg-success' : (($r['trang_thai'] ?? '') === 'LOI' ? 'text-bg-danger' : 'text-bg-secondary') ?>">
                        <?= h((string) $r['trang_thai']) ?></span></td>
                    <td class="text-end"><?= (int) $r['so_ban_ghi'] ?></td>
                    <td class="text-end"><?= (int) $r['so_bo_qua'] ?></td>
                    <td class="small text-muted"><?= h((string) $r['thong_bao']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($nhat_ky === []): ?>
                <tr><td colspan="8" class="text-center text-muted">Chưa có nhật ký.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
