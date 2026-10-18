<?php
/** Một hàng trận trong bảng tỉ số. @var \App\Models\FootballMatch $m */
$live = $m->isDangDienRa();
$soon = $m->getTrangThai() === 'SAP_DIEN_RA';
$chip = match ($m->getTrangThai()) {
    'DANG_DA' => 'as-chip live',
    'NGHI_GIUA_HIEP' => 'as-chip warn',
    'SAP_DIEN_RA' => 'as-chip soon',
    'KET_THUC' => 'as-chip ft',
    default => 'as-chip muted',
};
$phut = ($live && $m->getPhut() > 0) ? $m->getPhut() . "'" : '';
?>
<div class="as-row<?= $live ? ' is-live' : '' ?>" data-id="<?= (int) $m->getId() ?>">
    <div class="as-time">
        <?php if ($live && $phut !== ''): ?>
            <span class="min"><?= h($phut) ?></span>
        <?php else: ?>
            <span><?= h(App\Core\Time::toDisplay($m->getThoiGianBatDau())) ?></span>
        <?php endif; ?>
    </div>

    <div class="as-team home">
        <a class="nm" href="<?= h(route('doi-chitiet', ['id' => $m->getDoiNhaId()])) ?>"><?= h($m->getDoiNhaTen()) ?></a>
        <?= crest_html($m->getDoiNhaTen(), $m->getDoiNhaLogo(), 'ink', 'sm', route('doi-chitiet', ['id' => $m->getDoiNhaId()])) ?>
    </div>

    <a class="as-score<?= $soon ? ' soon' : '' ?>" href="<?= h(route('tran', ['id' => $m->getId()])) ?>">
        <?= $soon ? 'vs' : (int) $m->getTiSoNha() . ' - ' . (int) $m->getTiSoKhach() ?>
    </a>

    <div class="as-team away">
        <?= crest_html($m->getDoiKhachTen(), $m->getDoiKhachLogo(), 'muted', 'sm', route('doi-chitiet', ['id' => $m->getDoiKhachId()])) ?>
        <a class="nm" href="<?= h(route('doi-chitiet', ['id' => $m->getDoiKhachId()])) ?>"><?= h($m->getDoiKhachTen()) ?></a>
    </div>

    <div class="as-status">
        <span class="<?= h($chip) ?>">
            <?php if ($live): ?><span class="as-live-dot"></span><?php endif; ?>
            <?= h(nhan_trang_thai($m->getTrangThai())) ?>
        </span>
    </div>
</div>
