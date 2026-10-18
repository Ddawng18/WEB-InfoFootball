<?php
/**
 * Trang chủ: sidebar giải đấu (có phân trang) + tab trạng thái + danh sách trận.
 * @var array $items  @var int $tong  @var int $trang  @var int $moi_trang
 * @var string $tt  @var int|null $giai_id  @var array $dem
 * @var \App\Models\League[] $leagues  @var int $tong_giai  @var int $trang_giai  @var int $moi_giai
 * @var \App\Models\User|null $user
 */
$capNhat = ($cap_nhat_luc ?? '') !== '' ? App\Core\Time::toDisplay($cap_nhat_luc) : 'chưa có';
$tabUrl = static fn (string $tab): string => route('home', array_filter([
    'tt' => $tab, 'giai' => $giai_id,
], static fn ($v) => $v !== null && $v !== ''));
$tabs = [
    ['all', 'Tất cả', $dem['all'] ?? 0, ''],
    ['live', 'Đang đá', $dem['live'] ?? 0, 'live'],
    ['finished', 'Kết thúc', $dem['finished'] ?? 0, ''],
    ['scheduled', 'Sắp diễn ra', $dem['scheduled'] ?? 0, ''],
];
$lastGiai = null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
    <h1 class="as-h2 mb-0">Bảng tỉ số bóng đá</h1>
    <div class="text-muted small">
        <i class="bi bi-arrow-repeat"></i> Cập nhật lúc <span id="cap-nhat-luc"><?= h($capNhat) ?></span>
    </div>
</div>

<div class="as-layout">
    <!-- Sidebar giải đấu (phân trang) -->
    <aside>
        <div class="as-side">
            <h3>Giải đấu (<?= (int) $tong_giai ?>)</h3>
            <ul class="as-lnav">
                <li>
                    <a class="<?= $giai_id === null ? 'active' : '' ?>" href="<?= h(route('home', ['tt' => $tt])) ?>">
                        <span class="ic"><i class="bi bi-globe2"></i></span> Tất cả giải
                    </a>
                </li>
                <?php foreach ($leagues as $l): ?>
                    <li>
                        <a class="<?= $giai_id === $l->getId() ? 'active' : '' ?>"
                           href="<?= h(route('home', ['giai' => $l->getId(), 'tt' => $tt])) ?>">
                            <span class="ic"><?= h(chu_cai_dau($l->getTen())) ?></span>
                            <?= h($l->getTen()) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="as-side-pager">
                <?= render_pager($tong_giai, $moi_giai, $trang_giai, ['tt' => $tt, 'giai' => $giai_id], 'home', 'lg', 1) ?>
            </div>
        </div>
    </aside>

    <!-- Nội dung chính -->
    <section class="as-main">
        <div class="as-toolbar">
            <?php foreach ($tabs as [$key, $nhan, $so, $mod]): ?>
                <a class="as-tab <?= $mod ?> <?= $tt === $key ? 'active' : '' ?>" href="<?= h($tabUrl($key)) ?>">
                    <?php if ($key === 'live'): ?><span class="as-live-dot"></span><?php endif; ?>
                    <?= h($nhan) ?> <span class="cnt">(<?= (int) $so ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="as-list" id="as-list"
             data-tt="<?= h($tt) ?>" data-giai="<?= h((string) ($giai_id ?? '')) ?>" data-trang="<?= (int) $trang ?>">
            <?php if (($du_lieu_cu ?? false) && ($thong_bao ?? '') !== ''): ?>
                <div class="as-state"><i class="bi bi-exclamation-triangle"></i> Đang hiển thị dữ liệu đã lưu. <?= h($thong_bao) ?></div>
            <?php endif; ?>

            <?php if ($items === []): ?>
                <div class="as-empty"><i class="bi bi-search fs-4 d-block mb-2"></i> Không có trận nào phù hợp.</div>
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

        <?= render_pager($tong ?? 0, $moi_trang ?? 20, $trang ?? 1, array_filter(['tt' => $tt, 'giai' => $giai_id], static fn ($v) => $v !== null && $v !== ''), 'home') ?>
    </section>
</div>
