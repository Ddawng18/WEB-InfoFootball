<?php
/** @var array<int, array<string, mixed>> $items @var array|null $crawl */
$nguonNhan = static fn (string $n): string => $n === 'VNEXPRESS' ? 'VnExpress' : ($n === 'ADMIN' ? 'Ban biên tập' : $n);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="bi bi-newspaper"></i> Tin tức bóng đá</h1>
    <?php if (!empty($crawl['thong_bao'])): ?>
        <span class="text-muted small">
            <i class="bi bi-arrow-repeat"></i> <?= h((string) $crawl['thong_bao']) ?>
        </span>
    <?php endif; ?>
</div>
<p class="text-muted small">
    Nguồn: <a href="https://vnexpress.net/bong-da" target="_blank" rel="noopener">VnExpress - Bóng đá</a>.
    Tiêu đề, ảnh và sapo thuộc bản quyền VnExpress; bấm vào tin để đọc bài gốc.
</p>

<?php if ($items === []): ?>
    <div class="alert alert-info">Chưa có tin tức nào. Hãy thử lại sau hoặc bấm "Cào tin VnExpress" trong trang quản trị.</div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($items as $t): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card h-100 news-card">
                    <?php if (!empty($t['anh_url'])): ?>
                        <a href="<?= h(route('tintuc-chitiet', ['id' => (int) $t['tin_id']])) ?>">
                            <img src="<?= h((string) $t['anh_url']) ?>" class="card-img-top news-thumb" alt="" loading="lazy"
                                 referrerpolicy="no-referrer">
                        </a>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h2 class="h6 card-title">
                            <a class="link-body-emphasis text-decoration-none"
                               href="<?= h(route('tintuc-chitiet', ['id' => (int) $t['tin_id']])) ?>">
                                <?= h((string) $t['tieu_de']) ?>
                            </a>
                        </h2>
                        <?php if (!empty($t['tom_tat'])): ?>
                            <p class="card-text small text-muted mb-2 news-sapo"><?= h((string) $t['tom_tat']) ?></p>
                        <?php endif; ?>
                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <span class="badge text-bg-light"><?= h($nguonNhan((string) ($t['nguon'] ?? 'ADMIN'))) ?></span>
                            <span class="text-muted small"><?= h(App\Core\Time::toDisplay((string) ($t['ngay_dang'] ?? '')) ?: App\Core\Time::toDisplay((string) $t['ngay_tao'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= render_pager($tong ?? 0, $moi_trang ?? 9, $trang ?? 1, [], 'tintuc') ?>
<?php endif; ?>
