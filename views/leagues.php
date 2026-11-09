<?php
/** @var \App\Models\League[] $leagues */
?>
<h1 class="h3 mb-3"><i class="bi bi-trophy"></i> Giải đấu</h1>

<form class="row g-2 align-items-end mb-3" method="get" action="<?= h(App\Core\Url::to('index.php')) ?>">
    <input type="hidden" name="r" value="giai">
    <div class="col-8 col-md-4">
        <label class="form-label small mb-0" for="q">Tìm theo tên</label>
        <input class="form-control form-control-sm" id="q" name="q" value="<?= h($tu_khoa) ?>" placeholder="V.League...">
    </div>
    <div class="col-4 col-md-3">
        <label class="form-label small mb-0" for="kv">Khu vực</label>
        <select class="form-select form-select-sm" id="kv" name="kv">
            <option value="">Tất cả khu vực</option>
            <?php foreach (($regions ?? []) as $region): ?>
                <option value="<?= h((string) $region) ?>" <?= $khu_vuc === $region ? 'selected' : '' ?>>
                    <?= h((string) $region) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Tìm</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= h(route('giai')) ?>">Xóa lọc</a>
    </div>
</form>

<div class="row g-3">
    <?php if ($leagues === []): ?>
        <div class="col-12"><div class="alert alert-info">Không tìm thấy giải đấu nào.</div></div>
    <?php endif; ?>
    <?php foreach ($leagues as $l): ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h2 class="h5 mb-1"><?= h($l->getTen()) ?></h2>
                        <?php if ($l->isDangTheoDoi()): ?>
                            <span class="badge text-bg-success"><i class="bi bi-broadcast"></i> Đang theo dõi</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mb-2">
                        <i class="bi bi-globe-asia-australia"></i> <?= h($l->getQuocGiaTen()) ?>
                        &middot; <?= h($l->getKhuVuc()) ?>
                        <?php if ($l->getMuaGiai() !== ''): ?> &middot; Mùa <?= h($l->getMuaGiai()) ?><?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= h(route('doi', ['giai' => $l->getId()])) ?>">Xem đội bóng</a>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= h(route('bxh', ['giai' => $l->getId()])) ?>">Bảng xếp hạng</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= render_pager($tong ?? 0, $moi_trang ?? 9, $trang ?? 1, ['q' => $tu_khoa, 'kv' => $khu_vuc], 'giai') ?>
