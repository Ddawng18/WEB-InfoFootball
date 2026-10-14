<?php
/** @var \App\Models\Team[] $items */
?>
<h1 class="h3 mb-3"><i class="bi bi-people"></i> Đội bóng</h1>

<form class="row g-2 align-items-end mb-3" method="get" action="<?= h(App\Core\Url::to('index.php')) ?>">
    <input type="hidden" name="r" value="doi">
    <div class="col-8 col-md-4">
        <label class="form-label small mb-0" for="q">Tìm theo tên</label>
        <input class="form-control form-control-sm" id="q" name="q" value="<?= h($tu_khoa) ?>" placeholder="Hà Nội...">
    </div>
    <div class="col-4 col-md-3">
        <label class="form-label small mb-0" for="giai">Giải</label>
        <select class="form-select form-select-sm" id="giai" name="giai">
            <option value="">Tất cả</option>
            <?php foreach ($leagues as $l): ?>
                <option value="<?= (int) $l->getId() ?>" <?= $gioi_id === $l->getId() ? 'selected' : '' ?>>
                    <?= h($l->getTen()) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Tìm</button>
    </div>
</form>

<p class="text-muted small">Tìm thấy <?= (int) $tong ?> đội bóng.</p>

<div class="row g-3">
    <?php if ($items === []): ?>
        <div class="col-12"><div class="alert alert-info">Không tìm thấy đội bóng nào.</div></div>
    <?php endif; ?>
    <?php foreach ($items as $t): ?>
        <div class="col-12 col-md-6 col-lg-4 col-xl-3">
            <div class="card h-100 team-card" data-id="<?= (int) $t->getId() ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center gap-2 pe-1" style="min-width:0">
                            <?= crest_html($t->getTen(), $t->getLogoUrl(), 'ink', 'sm') ?>
                            <h2 class="h6 mb-0 text-truncate">
                                <a class="link-body-emphasis text-decoration-none"
                                   href="<?= h(route('doi-chitiet', ['id' => $t->getId()])) ?>"><?= h($t->getTen()) ?></a>
                            </h2>
                        </div>
                        <?php if ($user ?? null):
                            $daThich = in_array((int) $t->getId(), $fav_ids ?? [], true); ?>
                            <button type="button" class="btn btn-sm btn-link p-0 fav-btn"
                                    data-doi="<?= (int) $t->getId() ?>" data-bat="<?= $daThich ? '0' : '1' ?>"
                                    title="<?= $daThich ? 'Bỏ yêu thích' : 'Thêm vào yêu thích' ?>">
                                <i class="bi <?= $daThich ? 'bi-heart-fill text-danger' : 'bi-heart text-danger' ?>"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted small mb-2"><i class="bi bi-geo-alt"></i> <?= h($t->getQuocGiaTen()) ?></div>
                    <a class="stretched-link small mt-auto"
                       href="<?= h(route('doi-chitiet', ['id' => $t->getId()])) ?>">Chi tiết</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?= render_pager($tong ?? 0, $moi_trang ?? 12, $trang ?? 1, ['q' => $tu_khoa, 'giai' => $gioi_id], 'doi') ?>
