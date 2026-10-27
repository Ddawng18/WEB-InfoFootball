<?php
/** @var \App\Models\FootballMatch $tran @var array $su_kien @var array $lineups @var array $stat_rows */
$dangDienRa = $tran->isDangDienRa();
$pending = $tran->getTrangThai() === 'CHO_CAP_NHAT';
$pendingNote = $tran->getCoLive() === 0 ? 'ESPN không cung cấp live cho trận này' : 'Chờ nguồn cập nhật tỉ số';
$lich = $lich_ca_nhan ?? ['trong_lich' => false, 'nhac' => false, 'da_nhac' => false];
$trongLich = !empty($lich['trong_lich']);
$nhac = !empty($lich['nhac']);
$batDauTran = strtotime($tran->getThoiGianBatDau() . ' UTC');
$coTheLuu = $tran->getTrangThai() === 'SAP_DIEN_RA' && $batDauTran !== false && $batDauTran > time();
$home = $home ?? $tran->getDoiNhaTen();
$away = $away ?? $tran->getDoiKhachTen();
$homeLogo = $home_logo_local ?? $tran->getDoiNhaLogo();
$awayLogo = $away_logo_local ?? $tran->getDoiKhachLogo();
$start = new DateTime($tran->getThoiGianBatDau(), new DateTimeZone('UTC'));
$localStart = clone $start;
$localStart->setTimezone(new DateTimeZone(App\Core\Time::tz()));
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= h(route('home')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= h(route('lich')) ?>">Lịch thi đấu</a></li><li class="breadcrumb-item active" aria-current="page">Chi tiết trận</li></ol></nav>
<section class="match-hero" id="chi-tiet-tran" data-id="<?= (int) $tran->getId() ?>">
    <div class="match-hero__league"><i class="bi bi-trophy"></i> <?= h($tran->getGiaiTen()) ?></div>
    <div class="match-hero__date"><i class="bi bi-calendar3"></i> <?= h($localStart->format('d/m/Y · H:i')) ?> (<?= h(App\Core\Time::tzLabel()) ?>)</div>
    <div class="match-hero__teams">
        <div class="match-team"><?= crest_html($home, $homeLogo, 'ink', '', route('doi-chitiet', ['id' => $tran->getDoiNhaId()])) ?><h1><a class="text-reset text-decoration-none" href="<?= h(route('doi-chitiet', ['id' => $tran->getDoiNhaId()])) ?>"><?= h($home) ?></a></h1></div>
        <div class="match-score"><strong id="ty-so"<?= $pending ? ' title="' . h($pendingNote) . '"' : '' ?>><?= $tran->getTrangThai() === 'SAP_DIEN_RA' ? 'VS' : ($pending ? '–' : (int) $tran->getTiSoNha() . ' – ' . (int) $tran->getTiSoKhach()) ?></strong><span class="<?= h(badge_trang_thai($tran->getTrangThai())) ?>" id="trang-thai-badge"><?php if ($dangDienRa): ?><span class="ff-live-dot"></span><?php endif; ?><?= h(nhan_trang_thai($tran->getTrangThai())) ?><?php if ($dangDienRa && $tran->getPhut() > 0): ?> · <?= (int) $tran->getPhut() ?>'<?php endif; ?></span></div>
        <div class="match-team"><?= crest_html($away, $awayLogo, 'muted', '', route('doi-chitiet', ['id' => $tran->getDoiKhachId()])) ?><h2><a class="text-reset text-decoration-none" href="<?= h(route('doi-chitiet', ['id' => $tran->getDoiKhachId()])) ?>"><?= h($away) ?></a></h2></div>
    </div>
    <?php if ($coTheLuu): ?>
    <div class="match-actions">
        <button type="button" class="ffbi-match-action ffbi-match-action--primary js-calendar<?= $nhac ? ' is-on' : '' ?>"
                data-tran="<?= (int) $tran->getId() ?>" data-field="nhac" data-on="<?= $nhac ? '1' : '0' ?>">
            <i class="bi <?= $nhac ? 'bi-bell-fill' : 'bi-bell' ?>" aria-hidden="true"></i>
            <span><?= $nhac ? 'Đã nhắc' : 'Nhắc tôi' ?></span>
        </button>
        <button type="button" class="ffbi-match-action js-calendar<?= $trongLich ? ' is-on' : '' ?>"
                data-tran="<?= (int) $tran->getId() ?>" data-field="trong_lich" data-on="<?= $trongLich ? '1' : '0' ?>">
            <i class="bi <?= $trongLich ? 'bi-calendar-check-fill' : 'bi-calendar-plus' ?>" aria-hidden="true"></i>
            <span><?= $trongLich ? 'Trong lịch' : 'Thêm vào lịch' ?></span>
        </button>
        <?php if (!$user): ?>
            <p class="match-actions__note">Đăng nhập để lưu trận vào lịch và nhận thông báo trong web.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="match-hero__updated">Cập nhật lúc <span id="cap-nhat-luc"><?= h(App\Core\Time::toDisplay($tran->getCapNhatLuc())) ?></span></div>
</section>
<nav class="match-tabs" aria-label="Mục trận đấu"><a class="is-active" href="#match-overview"><i class="bi bi-bar-chart-line"></i> Tổng quan</a><a href="#match-events"><i class="bi bi-list-ol"></i> Diễn biến</a><a href="#match-lineups"><i class="bi bi-people"></i> Đội hình</a><a href="#match-stats"><i class="bi bi-graph-up"></i> Thống kê</a><a href="#match-h2h"><i class="bi bi-arrow-left-right"></i> Đối đầu</a></nav>
<div class="match-grid" id="match-overview">
    <section class="match-panel"><h2><i class="bi bi-info-circle"></i> Thông tin trận đấu</h2><dl class="match-facts"><div><dt>Giải đấu</dt><dd><?= h($tran->getGiaiTen()) ?></dd></div><div><dt>Ngày thi đấu</dt><dd><?= h($localStart->format('d/m/Y')) ?></dd></div><div><dt>Giờ bắt đầu</dt><dd><?= h($localStart->format('H:i')) ?> (<?= h(App\Core\Time::tzLabel()) ?>)</dd></div><div><dt>Trạng thái</dt><dd><?= h(nhan_trang_thai($tran->getTrangThai()) . ($pending ? ' · ' . $pendingNote : '')) ?></dd></div></dl></section>
    <section class="match-panel" id="match-events"><h2><i class="bi bi-list-ol"></i> Diễn biến chính</h2><?php if ($su_kien === []): ?><p class="match-empty"><?= ($tran->getTrangThai() === 'SAP_DIEN_RA' || $pending) ? 'Trận đấu chưa diễn ra.' : 'Chưa có diễn biến chính từ nguồn dữ liệu.' ?></p><?php else: ?><ol class="match-timeline"><?php foreach ($su_kien as $event): ?><li><time><?= (int) ($event['phut'] ?? 0) ?>′</time><span><b><?= h(nhan_su_kien((string) ($event['loai'] ?? 'Sự kiện'))) ?></b><small><?= h((string) ($event['cau_thu'] ?? '')) ?><?= ($event['doi_ten'] ?? '') !== '' ? ' · ' . h((string) $event['doi_ten']) : '' ?></small></span></li><?php endforeach; ?></ol><?php endif; ?></section>
    <section class="match-panel" id="match-stats"><h2><i class="bi bi-bar-chart"></i> Thống kê trận đấu</h2><div class="match-stat-teams"><span><?= h($home) ?></span><?= crest_html($home, $homeLogo, 'ink', 'sm') ?><span class="match-stat-spacer"></span><?= crest_html($away, $awayLogo, 'muted', 'sm') ?><span><?= h($away) ?></span></div><?php if ($stat_rows === []): ?><p class="match-empty"><?= ($tran->getTrangThai() === 'SAP_DIEN_RA' || $pending) ? 'Thống kê sẽ có sau khi trận bắt đầu.' : 'Thống kê chưa được API cung cấp cho trận này.' ?></p><?php else: ?><?php foreach ($stat_rows as [$label, $homeValue, $awayValue, $share]): ?><div class="match-stat"><b><?= h((string) $homeValue) ?></b><span><?= h($label) ?></span><b><?= h((string) $awayValue) ?></b><div class="match-stat-bar"><i style="--home-share:<?= (float) $share ?>%"></i></div></div><?php endforeach; ?><?php endif; ?></section>
    <section class="match-panel match-panel--wide" id="match-lineups"><h2><i class="bi bi-people"></i> Đội hình ra sân</h2><?php if ($lineups === []): ?><p class="match-empty"><?= ($tran->getTrangThai() === 'SAP_DIEN_RA' || $pending) ? 'Đội hình sẽ được công bố trước giờ bóng lăn.' : 'Đội hình chưa được API cung cấp cho trận này.' ?></p><?php else: ?><div class="match-lineups"><?php foreach ($lineups as $team): ?><div><h3><?= h((string) ($team['team']['name'] ?? 'Đội bóng')) ?> <small><?= h((string) ($team['formation'] ?? '')) ?></small></h3><?php foreach (($team['startXI'] ?? []) as $player): ?><p><?= h((string) ($player['player']['number'] ?? '')) ?> · <?= h((string) ($player['player']['name'] ?? '')) ?></p><?php endforeach; ?></div><?php endforeach; ?></div><?php endif; ?></section>
    <section class="match-panel match-panel--wide" id="match-h2h"><h2><i class="bi bi-arrow-left-right"></i> Đối đầu</h2><?php if ($doi_dau === []): ?><p class="match-empty">Dữ liệu đối đầu chưa được nguồn cung cấp cho trận này.</p><?php else: ?><ul class="match-h2h-list"><?php foreach ($doi_dau as $hd): ?><?php
        $ngayHienThi = (string) ($hd['ngay'] ?? '');
        if ($ngayHienThi !== '') {
            try {
                $ngayObj = new DateTime($ngayHienThi, new DateTimeZone('UTC'));
                $ngayObj->setTimezone(new DateTimeZone(App\Core\Time::tz()));
                $ngayHienThi = $ngayObj->format('d/m/Y');
            } catch (Exception $e) {
            }
        }
        $tiSo = (string) ($hd['ti_so_nha'] ?? '') . ' - ' . (string) ($hd['ti_so_khach'] ?? '');
        ?><li><time><?= h($ngayHienThi) ?></time><div class="match-h2h-teams"><span><?= h((string) ($hd['doi_nha'] ?? '')) ?></span><b><?= h($tiSo) ?></b><span><?= h((string) ($hd['doi_khach'] ?? '')) ?></span></div><small><?= h((string) ($hd['giai_ten'] ?? '')) ?></small></li><?php endforeach; ?></ul><?php endif; ?></section>
</div>
