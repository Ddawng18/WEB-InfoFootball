<?php
/** @var \App\Models\Team $doi @var \App\Models\FootballMatch[] $sap_toi @var \App\Models\FootballMatch[] $gan_day */
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= h(route('doi')) ?>">Đội bóng</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= h($doi->getTen()) ?></li>
    </ol>
</nav>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <?= crest_html($doi->getTen(), $doi->getLogoUrl(), 'ink') ?>
            <div>
                <h1 class="h3 mb-1"><?= h($doi->getTen()) ?></h1>
                <div class="text-muted"><i class="bi bi-geo-alt"></i> <?= h($doi->getQuocGiaTen()) ?></div>
            </div>
        </div>
        <div class="text-end">
            <div class="mb-2">
                <span class="badge text-bg-success">Thắng <?= (int) $doi->getThang() ?></span>
                <span class="badge text-bg-secondary">Hòa <?= (int) $doi->getHoa() ?></span>
                <span class="badge text-bg-danger">Thua <?= (int) $doi->getThua() ?></span>
            </div>
            <?php if ($user ?? null): ?>
                <button type="button" class="btn btn-sm <?= $la_yeu_thich ? 'btn-danger' : 'btn-outline-danger' ?> fav-btn"
                        data-doi="<?= (int) $doi->getId() ?>" data-bat="<?= $la_yeu_thich ? '0' : '1' ?>">
                    <i class="bi bi-heart<?= $la_yeu_thich ? '-fill' : '' ?>"></i>
                    <span class="fav-text"><?= $la_yeu_thich ? 'Đang yêu thích' : 'Thêm yêu thích' ?></span>
                </button>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-danger" href="<?= h(route('dangnhap', ['msg' => 'can-dang-nhap'])) ?>">
                    <i class="bi bi-heart"></i> Đăng nhập để yêu thích
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <h2 class="h5">Trận sắp tới</h2>
        <?php if ($sap_toi === []): ?>
            <p class="text-muted small">Không có trận sắp tới.</p>
        <?php else: foreach ($sap_toi as $m) { include __DIR__ . '/partials/match_card.php'; } endif; ?>
    </div>
    <div class="col-lg-6">
        <h2 class="h5">Kết quả gần đây</h2>
        <?php if ($gan_day === []): ?>
            <p class="text-muted small">Chưa có kết quả.</p>
        <?php else: foreach ($gan_day as $m) { include __DIR__ . '/partials/match_card.php'; } endif; ?>
    </div>
</div>
