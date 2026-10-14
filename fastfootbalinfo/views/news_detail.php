<?php
/** @var array<string, mixed> $tin */
$nguon = (string) ($tin['nguon'] ?? 'ADMIN');
$laCao = $nguon !== 'ADMIN';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= h(route('tintuc')) ?>">Tin tức</a></li>
        <li class="breadcrumb-item active" aria-current="page">Chi tiết</li>
    </ol>
</nav>

<article class="card">
    <div class="card-body">
        <h1 class="h3"><?= h((string) $tin['tieu_de']) ?></h1>
        <div class="text-muted small mb-3">
            <?= h(App\Core\Time::toDisplay((string) ($tin['ngay_dang'] ?? '')) ?: App\Core\Time::toDisplay((string) $tin['ngay_tao'])) ?>
            <?php if (!empty($tin['danh_muc_ten'])): ?> &middot; <?= h((string) $tin['danh_muc_ten']) ?><?php endif; ?>
            <?php if ($laCao): ?>
                &middot; Nguồn: <?= $nguon === 'VNEXPRESS' ? 'VnExpress' : h($nguon) ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($tin['anh_url'])): ?>
            <img src="<?= h((string) $tin['anh_url']) ?>" class="img-fluid rounded mb-3" alt=""
                 referrerpolicy="no-referrer">
        <?php endif; ?>

        <?php if (!empty($tin['tom_tat'])): ?>
            <p class="lead fs-6"><?= h((string) $tin['tom_tat']) ?></p>
        <?php endif; ?>

        <?php if ($laCao): ?>
            <div class="alert alert-secondary py-2 small">
                <i class="bi bi-info-circle"></i> Đây là tin tổng hợp: website chỉ lưu <strong>tiêu đề, ảnh và sapo</strong>
                để dẫn nguồn, không sao chép toàn bộ bài viết.
            </div>
            <?php if (!empty($tin['duong_dan'])): ?>
                <a class="btn btn-outline-primary" target="_blank" rel="noopener"
                   href="<?= h((string) $tin['duong_dan']) ?>">
                    <i class="bi bi-box-arrow-up-right"></i> Đọc bài đầy đủ trên VnExpress
                </a>
            <?php endif; ?>
        <?php else: ?>
            <div class="news-content"><?= (string) $tin['noi_dung'] ?></div>
        <?php endif; ?>
    </div>
</article>
