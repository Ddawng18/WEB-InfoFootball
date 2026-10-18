<?php
/** @var \App\Models\FootballMatch[] $items @var array $leagues @var \App\Models\User|null $user */
$moiTrang = 20;
$tongTrang = max(1, (int) ceil(($tong ?? 0) / $moiTrang));
$lastGiai = null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <h1 class="as-h2 mb-0"><i class="bi bi-calendar3 text-primary"></i> Lịch thi đấu &amp; kết quả</h1>
    <div class="text-muted small">Tổng <?= (int) ($tong ?? 0) ?> trận</div>
</div>

<form class="as-toolbar mb-0" method="get" action="<?= h(App\Core\Url::to('index.php')) ?>">
    <input type="hidden" name="r" value="lich">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <input class="form-control form-control-sm" style="width:auto" type="date" id="ngay" name="ngay" value="<?= h($ngay) ?>">
        <select class="form-select form-select-sm" style="width:auto" id="giai" name="giai">
            <option value="">Tất cả giải</option>
            <?php foreach ($leagues as $l): ?>
                <option value="<?= (int) $l->getId() ?>" <?= $giai_id === $l->getId() ? 'selected' : '' ?>><?= h($l->getTen()) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select form-select-sm" style="width:auto" id="tt" name="tt">
            <option value="">Tất cả trạng thái</option>
            <?php foreach (['SAP_DIEN_RA','DANG_DA','NGHI_GIUA_HIEP','KET_THUC','HOAN','HUY'] as $tt): ?>
                <option value="<?= h($tt) ?>" <?= $trang_thai === $tt ? 'selected' : '' ?>><?= h(nhan_trang_thai($tt)) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Lọc</button>
        <a class="btn btn-outline-secondary btn-sm" href="<?= h(route('lich')) ?>">Xóa lọc</a>
    </div>
</form>

<div class="as-list">
    <?php if ($items === []): ?>
        <div class="as-empty"><i class="bi bi-calendar-x fs-4 d-block mb-2"></i> Không có trận nào.</div>
    <?php else: ?>
        <?php foreach ($items as $m):
            $g = $m->getGiaiTen();
            if ($g !== $lastGiai): $lastGiai = $g; ?>
                <div class="as-league-head">
                    <span class="ic ff-crest ff-crest--muted ff-crest--sm"><span class="ff-crest__ini"><?= h(chu_cai_dau($g)) ?></span></span>
                    <span class="name"><?= h($g) ?></span>
                </div>
            <?php endif; ?>
            <?php include __DIR__ . '/partials/match_row.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?= render_pager($tong ?? 0, $moiTrang, $trang ?? 1, ['ngay' => $ngay, 'giai' => $giai_id, 'tt' => $trang_thai], 'lich') ?>
