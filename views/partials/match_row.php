<?php
/** Một hàng trận trong bảng tỉ số. @var \App\Models\FootballMatch $m */
$live = $m->isDangDienRa();
$pending = $m->getTrangThai() === 'CHO_CAP_NHAT';
$soon = $m->getTrangThai() === 'SAP_DIEN_RA';
$batDau = strtotime($m->getThoiGianBatDau() . ' UTC');
$coTheLuu = $soon && $batDau !== false && $batDau > time();
$chip = match ($m->getTrangThai()) {
    'DANG_DA' => 'as-chip live',
    'NGHI_GIUA_HIEP' => 'as-chip warn',
    'CHO_CAP_NHAT' => 'as-chip warn',
    'SAP_DIEN_RA' => 'as-chip soon',
    'KET_THUC' => 'as-chip ft',
    default => 'as-chip muted',
};
$scoreTitle = $pending
    ? ($m->getCoLive() === 0 ? 'ESPN không cung cấp live cho trận này' : 'Chờ nguồn cập nhật tỉ số')
    : '';
$phut = ($live && $m->getPhut() > 0) ? $m->getPhut() . "'" : '';
$homeLogo = $m->getDoiNhaLogo();
$awayLogo = $m->getDoiKhachLogo();
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
        <?= crest_html($m->getDoiNhaTen(), $homeLogo, 'ink', 'sm', route('doi-chitiet', ['id' => $m->getDoiNhaId()])) ?>
    </div>

    <a class="as-score<?= $soon ? ' soon' : '' ?>" href="<?= h(route('tran', ['id' => $m->getId()])) ?>"
       <?= $scoreTitle !== '' ? 'title="' . h($scoreTitle) . '"' : '' ?>>
        <?= $soon ? 'vs' : ($pending ? '–' : (int) $m->getTiSoNha() . ' - ' . (int) $m->getTiSoKhach()) ?>
    </a>

    <div class="as-team away">
        <?= crest_html($m->getDoiKhachTen(), $awayLogo, 'muted', 'sm', route('doi-chitiet', ['id' => $m->getDoiKhachId()])) ?>
        <a class="nm" href="<?= h(route('doi-chitiet', ['id' => $m->getDoiKhachId()])) ?>"><?= h($m->getDoiKhachTen()) ?></a>
    </div>

    <div class="as-status">
        <span class="<?= h($chip) ?>">
            <?php if ($live): ?><span class="as-live-dot"></span><?php endif; ?>
            <?= h(nhan_trang_thai($m->getTrangThai())) ?>
        </span>
    </div>

    <div class="as-actions">
        <?php if ($coTheLuu): ?>
            <button type="button" class="as-mini js-calendar" data-tran="<?= (int) $m->getId() ?>"
                    data-field="nhac" data-on="0" title="Nhắc tôi" aria-label="Nhắc tôi">
                <i class="bi bi-bell"></i>
            </button>
            <button type="button" class="as-mini js-calendar" data-tran="<?= (int) $m->getId() ?>"
                    data-field="trong_lich" data-on="0" title="Thêm vào lịch" aria-label="Thêm vào lịch">
                <i class="bi bi-calendar-plus"></i>
            </button>
        <?php endif; ?>
    </div>
</div>
