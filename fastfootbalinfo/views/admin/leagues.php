<?php
/** @var \App\Models\League[] $leagues */
$msgMap = [
    'ok' => ['success', 'Đã lưu cấu hình.'],
    'khong_ton_tai' => ['danger', 'Giải đấu không tồn tại.'],
    'csrf' => ['danger', 'Phiên hết hạn, vui lòng thử lại.'],
];
?>
<h1 class="h3 mb-3"><i class="bi bi-gear"></i> Cấu hình giải đấu</h1>

<?php if (isset($msgMap[$msg ?? ''])): [$loai, $nd] = $msgMap[$msg]; ?>
    <div class="alert alert-<?= h($loai) ?> py-2"><?= h($nd) ?></div>
<?php endif; ?>

<p class="text-muted small">
    Bật/tắt theo dõi để giới hạn số giải được đồng bộ (hạn mức request). Gán khu vực cho quốc gia
    (phạm vi châu Á). Giải không gán khu vực sẽ hiển thị "Chưa phân loại".
</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr><th>#</th><th>Giải</th><th>Quốc gia</th><th>Khu vực</th><th>Theo dõi</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($leagues as $l): ?>
                <tr>
                    <td><?= (int) $l->getId() ?></td>
                    <td class="fw-semibold"><?= h($l->getTen()) ?></td>
                    <td><?= h($l->getQuocGiaTen()) ?></td>
                    <td><?= h($l->getKhuVuc()) ?></td>
                    <td>
                        <span class="badge <?= $l->isDangTheoDoi() ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= $l->isDangTheoDoi() ? 'Đang bật' : 'Đang tắt' ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" class="row g-1" action="<?= h(route('admin-giai')) ?>">
                            <?= App\Core\Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int) $l->getId() ?>">
                            <input type="hidden" name="dang_theo_doi" value="<?= $l->isDangTheoDoi() ? '0' : '1' ?>">
                            <div class="col-auto">
                                <input class="form-control form-control-sm" name="khu_vuc" placeholder="Khu vực"
                                       value="<?= h($l->getKhuVuc() === 'Chưa phân loại' ? '' : $l->getKhuVuc()) ?>">
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-sm btn-outline-primary" type="submit">
                                    <?= $l->isDangTheoDoi() ? 'Tắt & lưu' : 'Bật & lưu' ?>
                                </button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($leagues === []): ?>
                <tr><td colspan="6" class="text-center text-muted">Chưa có giải đấu.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
