<?php
/** Thẻ trận đấu dùng chung. @var \App\Models\FootballMatch $m */
$dangDienRa = $m->isDangDienRa();
$accent = accent_trang_thai($m->getTrangThai());
$phut = ($dangDienRa && $m->getPhut() > 0) ? " · {$m->getPhut()}'" : '';
$sapDienRa = $m->getTrangThai() === 'SAP_DIEN_RA';
?>
<article class="match-card ff-match" data-id="<?= (int) $m->getId() ?>" style="--accent:<?= h($accent) ?>">
    <div class="ff-match__top">
        <span class="ff-league"><i class="bi bi-trophy"></i> <?= h($m->getGiaiTen()) ?></span>
        <span class="<?= h(badge_trang_thai($m->getTrangThai())) ?>">
            <?php if ($dangDienRa): ?><span class="ff-live-dot"></span><?php endif; ?>
            <?= h(nhan_trang_thai($m->getTrangThai()) . $phut) ?>
        </span>
    </div>

    <p class="ff-match__date"><i class="bi bi-clock"></i> <?= h(App\Core\Time::toDisplay($m->getThoiGianBatDau())) ?></p>

    <div class="ff-match__teams">
        <div class="ff-team">
            <?= crest_html($m->getDoiNhaTen(), $m->getDoiNhaLogo(), 'ink', '', route('doi-chitiet', ['id' => $m->getDoiNhaId()])) ?>
            <a class="ff-team__name" href="<?= h(route('doi-chitiet', ['id' => $m->getDoiNhaId()])) ?>">
                <?= h($m->getDoiNhaTen()) ?></a>
        </div>

        <div class="ff-score<?= $sapDienRa ? ' ff-score--soon' : '' ?>">
            <?php if ($sapDienRa): ?>
                <b>&ndash;</b><small>vs</small><b>&ndash;</b>
            <?php else: ?>
                <b><?= (int) $m->getTiSoNha() ?></b><small>&ndash;</small><b><?= (int) $m->getTiSoKhach() ?></b>
            <?php endif; ?>
        </div>

        <div class="ff-team">
            <?= crest_html($m->getDoiKhachTen(), $m->getDoiKhachLogo(), 'muted', '', route('doi-chitiet', ['id' => $m->getDoiKhachId()])) ?>
            <a class="ff-team__name" href="<?= h(route('doi-chitiet', ['id' => $m->getDoiKhachId()])) ?>">
                <?= h($m->getDoiKhachTen()) ?></a>
        </div>
    </div>

    <div class="ff-match__bottom">
        <span class="text-muted small"><?= h(App\Core\Time::toDisplay($m->getCapNhatLuc())) ?></span>
        <a class="ff-linklink" href="<?= h(route('tran', ['id' => $m->getId()])) ?>">Chi tiết <i class="bi bi-arrow-right"></i></a>
    </div>
</article>
