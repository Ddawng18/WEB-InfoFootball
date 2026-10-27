<?php
/**
 * Trang chủ: sidebar giải đấu (có phân trang) + tab trạng thái + danh sách trận.
 * @var array $items  @var int $tong  @var int $trang  @var int $moi_trang
 * @var string $tt  @var int|null $giai_id  @var array $dem
 * @var \App\Models\League[] $leagues  @var int $tong_giai  @var int $trang_giai  @var int $moi_giai
 * @var \App\Models\User|null $user
 */
$capNhat = ($cap_nhat_luc ?? '') !== '' ? App\Core\Time::toDisplay($cap_nhat_luc) : 'chưa có';
$heroImage = asset('images/football-stadium-hero.jpg');
$stadiumImage = asset('images/stadium-overview.jpg');
$heroPhotoSource = 'https://unsplash.com/photos/soccer-game-played-in-a-packed-stadium-at-night-gq7DkfsEHVU';
$stadiumPhotoSource = 'https://unsplash.com/photos/a-crowded-stadium-at-night-during-a-soccer-match-AcICnuxS7q0';
$tabUrl = static fn (string $tab): string => route('home', array_filter([
    'tt' => $tab, 'giai' => $giai_id,
], static fn ($v) => $v !== null && $v !== ''));
$tabs = [
    ['all', 'Tất cả', $dem['all'] ?? 0, ''],
    ['live', 'Đang đá / chờ', $dem['live'] ?? 0, 'live'],
    ['finished', 'Kết thúc', $dem['finished'] ?? 0, ''],
    ['scheduled', 'Sắp diễn ra', $dem['scheduled'] ?? 0, ''],
];
$spotlight = $spotlight ?? null;
$lastGiai = null;
?>
<section class="ffbi-hero" aria-labelledby="home-title">
    <img class="ffbi-hero__image" src="<?= h($heroImage) ?>" alt="" fetchpriority="high">
    <img class="ffbi-hero__flag" src="<?= h(asset('images/man-utd-flag.png')) ?>" alt="" loading="lazy" decoding="async">
    <img class="ffbi-hero__players" src="<?= h(asset('images/man-utd-trio.png')) ?>" alt="" loading="lazy" decoding="async">
    <div class="ffbi-hero__copy">
        <p class="ffbi-hero__eyebrow"><span class="ffbi-hero__crest" aria-hidden="true"><i class="bi bi-globe2"></i></span> FOOTBALL <span class="ffbi-hero__divider"></span> MATCH CENTRE</p>
        <h1 id="home-title">Mọi giải đấu.<br><span>Mọi khoảnh khắc.</span></h1>
        <p class="ffbi-hero__intro">Lịch thi đấu, tỉ số trực tiếp, kết quả và tin tức từ những giải đấu bạn quan tâm — tất cả trong một nơi.</p>
        <div class="ffbi-hero__meta">
            <span class="ffbi-live-label"><i class="as-live-dot"></i> <span data-dem="live"><?= (int) ($dem['live'] ?? 0) ?></span> trận đang đá / chờ cập nhật</span>
            <span class="ffbi-hero__updated"><i class="bi bi-arrow-repeat"></i> Cập nhật <span id="cap-nhat-luc"><?= h($capNhat) ?></span></span>
        </div>
    </div>
    <a class="ffbi-photo-credit" href="<?= h($heroPhotoSource) ?>" target="_blank" rel="noopener">Ảnh: Zaki / Unsplash <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
</section>

<section class="ffbi-stats" aria-label="Tổng quan bóng đá">
    <div class="ffbi-stat"><span class="ffbi-stat__icon ffbi-stat__icon--live"><i class="bi bi-broadcast-pin"></i></span><span><strong data-dem="live"><?= (int) ($dem['live'] ?? 0) ?></strong><small>Đang đá / chờ</small></span></div>
    <div class="ffbi-stat"><span class="ffbi-stat__icon"><i class="bi bi-calendar-event"></i></span><span><strong data-dem="scheduled"><?= (int) ($dem['scheduled'] ?? 0) ?></strong><small>Sắp diễn ra</small></span></div>
    <div class="ffbi-stat"><span class="ffbi-stat__icon"><i class="bi bi-check2-circle"></i></span><span><strong data-dem="finished"><?= (int) ($dem['finished'] ?? 0) ?></strong><small>Đã kết thúc</small></span></div>
    <div class="ffbi-stat"><span class="ffbi-stat__icon"><i class="bi bi-trophy"></i></span><span><strong><?= (int) $tong_giai ?></strong><small>Giải đấu theo dõi</small></span></div>
</section>

<div class="as-layout">
    <!-- Sidebar giải đấu (phân trang) -->
    <aside>
        <section class="ffbi-side-feature" aria-label="Toàn cảnh bóng đá">
            <img src="<?= h($stadiumImage) ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
            <div class="ffbi-side-feature__body">
                <span class="ffbi-side-feature__eyebrow">TOÀN CẢNH BÓNG ĐÁ</span>
                <h2>Trận cầu<br>không biên giới.</h2>
                <a href="<?= h(route('lich')) ?>">Khám phá lịch đấu <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                <a class="ffbi-side-feature__credit" href="<?= h($stadiumPhotoSource) ?>" target="_blank" rel="noopener">Ảnh: Maryam Tello / Unsplash <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
            </div>
        </section>
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
        <?php if ($spotlight !== null): ?>
            <article class="ffbi-featured-match"<?= $spotlight['countdown'] !== null ? ' data-countdown-target="' . (int) $spotlight['countdown'] . '"' : '' ?>>
                <img class="ffbi-featured-match__image" src="<?= h($stadiumImage) ?>" alt="" loading="lazy">
                <div class="ffbi-featured-match__content">
                    <div class="ffbi-featured-match__top">
                        <span class="ffbi-featured-match__label"><i class="bi bi-calendar-event" aria-hidden="true"></i> <?= h($spotlight['label']) ?></span>
                        <span class="<?= h(badge_trang_thai($spotlight['status'])) ?>">
                            <?php if ($spotlight['live']): ?><span class="ff-live-dot"></span><?php endif; ?>
                            <?= h(nhan_trang_thai($spotlight['status'])) ?>
                            <?php if ($spotlight['live'] && (int) $spotlight['minute'] > 0): ?> · <?= (int) $spotlight['minute'] ?>'<?php endif; ?>
                        </span>
                    </div>
                    <?php $kickoffLocal = new DateTime($spotlight['kickoff'], new DateTimeZone('UTC')); $kickoffLocal->setTimezone(new DateTimeZone(App\Core\Time::tz())); $weekday = [0 => 'CN', 1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7'][(int) $kickoffLocal->format('w')]; ?>
                    <div class="ffbi-featured-match__layout">
                        <div class="ffbi-featured-match__teams">
                            <div class="ffbi-featured-team">
                                <?= crest_html($spotlight['home'], $spotlight['home_logo'], 'ink') ?>
                                <strong><?= h($spotlight['home']) ?></strong>
                            </div>
                            <a class="ffbi-featured-match__score" href="<?= h($spotlight['href']) ?>" aria-label="Chi tiết trận <?= h($spotlight['home']) ?> và <?= h($spotlight['away']) ?>">
                                <span class="ffbi-featured-match__competition"><i class="bi bi-trophy" aria-hidden="true"></i> <?= h($spotlight['league']) ?></span>
                                <?php if ($spotlight['status'] === 'SAP_DIEN_RA'): ?>
                                    <span><?= h($kickoffLocal->format('H:i')) ?></span>
                                <?php elseif (!empty($spotlight['pending'])): ?>
                                    <span title="<?= h(($spotlight['co_live'] ?? null) === 0 ? 'ESPN không cung cấp live cho trận này' : 'Chờ nguồn cập nhật tỉ số') ?>">–</span>
                                <?php else: ?>
                                    <span><?= (int) $spotlight['home_score'] ?></span><i>:</i><span><?= (int) $spotlight['away_score'] ?></span>
                                <?php endif; ?>
                                <small><?= h($weekday . ', ' . $kickoffLocal->format('d/m/Y')) ?></small>
                            </a>
                            <div class="ffbi-featured-team">
                                <?= crest_html($spotlight['away'], $spotlight['away_logo'], 'muted') ?>
                                <strong><?= h($spotlight['away']) ?></strong>
                            </div>
                        </div>
                        <?php if ($spotlight['countdown'] !== null): ?>
                            <div class="ffbi-featured-match__actions">
                                <div class="ffbi-countdown" aria-label="Thời gian đến trận đấu" aria-live="off">
                                    <span class="ffbi-countdown__title">ĐẾM NGƯỢC</span>
                                    <div class="ffbi-countdown__units">
                                        <span><b data-countdown="days">00</b><small>Ngày</small></span>
                                        <span><b data-countdown="hours">00</b><small>Giờ</small></span>
                                        <span><b data-countdown="minutes">00</b><small>Phút</small></span>
                                        <span><b data-countdown="seconds">00</b><small>Giây</small></span>
                                    </div>
                                </div>
                                <button class="ffbi-match-action ffbi-match-action--primary js-calendar<?= !empty($spotlight['nhac']) ? ' is-on' : '' ?>"
                                        type="button" data-tran="<?= (int) $spotlight['tran_dau_id'] ?>"
                                        data-field="nhac" data-on="<?= !empty($spotlight['nhac']) ? '1' : '0' ?>">
                                    <i class="bi <?= !empty($spotlight['nhac']) ? 'bi-bell-fill' : 'bi-bell' ?>" aria-hidden="true"></i>
                                    <span><?= !empty($spotlight['nhac']) ? 'Đã nhắc' : 'Nhắc tôi' ?></span>
                                </button>
                                <button class="ffbi-match-action js-calendar<?= !empty($spotlight['trong_lich']) ? ' is-on' : '' ?>"
                                        type="button" data-tran="<?= (int) $spotlight['tran_dau_id'] ?>"
                                        data-field="trong_lich" data-on="<?= !empty($spotlight['trong_lich']) ? '1' : '0' ?>">
                                    <i class="bi <?= !empty($spotlight['trong_lich']) ? 'bi-calendar-check-fill' : 'bi-calendar-plus' ?>" aria-hidden="true"></i>
                                    <span><?= !empty($spotlight['trong_lich']) ? 'Trong lịch' : 'Thêm vào lịch' ?></span>
                                </button>
                                <?php if (!$user): ?><p class="match-actions__note">Đăng nhập để lưu trận vào lịch và nhận thông báo trong web.</p><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($spotlight['countdown'] === null): ?><a class="ffbi-featured-match__link" href="<?= h($spotlight['href']) ?>"><?= h($spotlight['link_label']) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
                </div>
            </article>
        <?php endif; ?>

        <div class="as-toolbar">
            <?php foreach ($tabs as [$key, $nhan, $so, $mod]): ?>
                <a class="as-tab <?= $mod ?> <?= $tt === $key ? 'active' : '' ?>" data-tab="<?= h($key) ?>"
                   href="<?= h($tabUrl($key)) ?>">
                    <?php if ($key === 'live'): ?><span class="as-live-dot"></span><?php endif; ?>
                    <?= h($nhan) ?> <span class="cnt" data-tab-count="<?= h($key) ?>">(<?= (int) $so ?>)</span>
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
