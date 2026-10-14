<?php /** @var string $thong_bao @var string|null $chi_tiet */ ?>
<div class="text-center py-5">
    <i class="bi bi-emoji-frown display-1 text-muted"></i>
    <h1 class="h3 mt-3"><?= h($thong_bao ?? 'Đã xảy ra lỗi.') ?></h1>
    <?php if (!empty($chi_tiet)): ?>
        <pre class="text-start bg-dark text-light p-3 mt-3 rounded small"><?= h($chi_tiet) ?></pre>
    <?php endif; ?>
    <a class="btn btn-primary mt-3" href="<?= h(route('home')) ?>">Về trang chủ</a>
</div>
