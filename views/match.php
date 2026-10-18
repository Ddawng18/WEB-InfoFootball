<?php
/** @var \App\Models\FootballMatch $tran @var array $su_kien */
$dangDienRa = $tran->isDangDienRa();
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= h(route('home')) ?>">Trang chủ</a></li>
        <li class="breadcrumb-item"><a href="<?= h(route('lich')) ?>">Lịch thi đấu</a></li>
        <li class="breadcrumb-item active" aria-current="page">Chi tiết trận</li>
    </ol>
</nav>

<div class="card mb-4" id="chi-tiet-tran" data-id="<?= (int) $tran->getId() ?>">
    <div class="card-body">
        <div class="text-center text-muted small mb-2">
            <i class="bi bi-trophy"></i> <?= h($tran->getGiaiTen()) ?>
            &middot; <i class="bi bi-clock"></i> <?= h(App\Core\Time::toDisplay($tran->getThoiGianBatDau())) ?>
        </div>
        <div class="row align-items-center">
            <div class="col-5 d-flex justify-content-end align-items-center gap-2 gap-sm-3">
                <h2 class="h4 mb-0 text-end"><?= h($tran->getDoiNhaTen()) ?></h2>
                <?= crest_html($tran->getDoiNhaTen(), $tran->getDoiNhaLogo(), 'ink') ?>
            </div>
            <div class="col-2 text-center">
                <div class="display-6 fw-bold" id="ty-so">
                    <?= $tran->getTrangThai() === 'SAP_DIEN_RA' ? 'vs' : (int) $tran->getTiSoNha() . ' - ' . (int) $tran->getTiSoKhach() ?>
                </div>
                <span class="<?= h(badge_trang_thai($tran->getTrangThai())) ?>" id="trang-thai-badge">
                    <?php if ($dangDienRa): ?><span class="ff-live-dot"></span><?php endif; ?>
                    <?= h(nhan_trang_thai($tran->getTrangThai())) ?>
                    <?php if ($dangDienRa && $tran->getPhut() > 0): ?> &middot; <?= (int) $tran->getPhut() ?>'<?php endif; ?>
                </span>
            </div>
            <div class="col-5 d-flex align-items-center gap-2 gap-sm-3">
                <?= crest_html($tran->getDoiKhachTen(), $tran->getDoiKhachLogo(), 'muted') ?>
                <h2 class="h4 mb-0"><?= h($tran->getDoiKhachTen()) ?></h2>
            </div>
        </div>
        <div class="text-center text-muted small mt-3">
            Cập nhật lúc <span id="cap-nhat-luc"><?= h(App\Core\Time::toDisplay($tran->getCapNhatLuc())) ?></span>
        </div>
    </div>
</div>

<h2 class="h5">Diễn biến</h2>
<?php if ($su_kien === []): ?>
    <p class="text-muted small">Chưa có sự kiện (bàn thắng, thẻ) cho trận này.</p>
<?php else: ?>
    <ul class="list-group">
        <?php foreach ($su_kien as $sk): ?>
            <li class="list-group-item d-flex justify-content-between">
                <span>
                    <i class="bi <?= ($sk['loai'] ?? '') === 'BAN_THANG' ? 'bi-dribbble' : 'bi-card-text' ?>"></i>
                    <?= h((string) ($sk['loai'] ?? '')) ?>
                    <?= h((string) ($sk['cau_thu'] ?? '')) ?>
                    <span class="text-muted">(<?= h((string) ($sk['doi_ten'] ?? '')) ?>)</span>
                </span>
                <span class="badge text-bg-light"><?= (int) ($sk['phut'] ?? 0) ?>'</span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
