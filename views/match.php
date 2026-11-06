<?php
/** @var \App\Models\FootballMatch $tran @var array $su_kien @var array $match_details */
$dangDienRa = $tran->isDangDienRa();
$match_details = $match_details ?? [];
$stats = $match_details['statistics'] ?? [];
$lineups = $match_details['lineups'] ?? [];
if (!empty($match_details['events'])) {
    $su_kien = array_map(static fn ($event) => [
        'phut' => (int) ($event['time']['elapsed'] ?? 0),
        'loai' => trim((string) ($event['type'] ?? '') . ' · ' . (string) ($event['detail'] ?? '')),
        'cau_thu' => (string) ($event['player']['name'] ?? ''),
        'doi_ten' => (string) ($event['team']['name'] ?? ''),
    ], $match_details['events']);
}
$home = $tran->getDoiNhaTen();
$away = $tran->getDoiKhachTen();
$homeLogo = $home_logo_local ?? $tran->getDoiNhaLogo();
$awayLogo = $away_logo_local ?? $tran->getDoiKhachLogo();
$start = new DateTime($tran->getThoiGianBatDau(), new DateTimeZone('UTC'));
$localStart = clone $start;
$localStart->setTimezone(new DateTimeZone(App\Core\Time::tz()));
$homeStats = $stats[0]['statistics'] ?? [];
$awayStats = $stats[1]['statistics'] ?? [];
$statRows = [];
foreach ($homeStats as $stat) {
    $name = (string) ($stat['type'] ?? '');
    foreach ($awayStats as $awayStat) {
        if (($awayStat['type'] ?? '') === $name && $name !== '') {
            $statRows[] = [$name, $stat['value'] ?? '—', $awayStat['value'] ?? '—'];
            break;
        }
    }
}
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= h(route('home')) ?>">Trang chủ</a></li><li class="breadcrumb-item"><a href="<?= h(route('lich')) ?>">Lịch thi đấu</a></li><li class="breadcrumb-item active" aria-current="page">Chi tiết trận</li></ol></nav>
<section class="match-hero" id="chi-tiet-tran" data-id="<?= (int) $tran->getId() ?>">
    <div class="match-hero__league"><i class="bi bi-trophy"></i> <?= h($tran->getGiaiTen()) ?></div>
    <div class="match-hero__date"><i class="bi bi-calendar3"></i> <?= h($localStart->format('d/m/Y · H:i')) ?> (UTC+7)</div>
    <div class="match-hero__teams">
        <div class="match-team"><?= crest_html($home, $homeLogo, 'ink') ?><h1><?= h($home) ?></h1></div>
        <div class="match-score"><strong id="ty-so"><?= $tran->getTrangThai() === 'SAP_DIEN_RA' ? 'VS' : (int) $tran->getTiSoNha() . ' – ' . (int) $tran->getTiSoKhach() ?></strong><span class="<?= h(badge_trang_thai($tran->getTrangThai())) ?>" id="trang-thai-badge"><?php if ($dangDienRa): ?><span class="ff-live-dot"></span><?php endif; ?><?= h(nhan_trang_thai($tran->getTrangThai())) ?><?php if ($dangDienRa && $tran->getPhut() > 0): ?> · <?= (int) $tran->getPhut() ?>'<?php endif; ?></span></div>
        <div class="match-team"><?= crest_html($away, $awayLogo, 'muted') ?><h2><?= h($away) ?></h2></div>
    </div>
    <div class="match-hero__updated">Cập nhật lúc <span id="cap-nhat-luc"><?= h(App\Core\Time::toDisplay($tran->getCapNhatLuc())) ?></span></div>
</section>
<nav class="match-tabs" aria-label="Mục trận đấu"><a class="is-active" href="#match-overview"><i class="bi bi-bar-chart-line"></i> Tổng quan</a><a href="#match-events"><i class="bi bi-list-ol"></i> Diễn biến</a><a href="#match-lineups"><i class="bi bi-people"></i> Đội hình</a><a href="#match-stats"><i class="bi bi-graph-up"></i> Thống kê</a><a href="#match-h2h"><i class="bi bi-arrow-left-right"></i> Đối đầu</a></nav>
<div class="match-grid" id="match-overview">
    <section class="match-panel"><h2><i class="bi bi-info-circle"></i> Thông tin trận đấu</h2><dl class="match-facts"><div><dt>Giải đấu</dt><dd><?= h($tran->getGiaiTen()) ?></dd></div><div><dt>Ngày thi đấu</dt><dd><?= h($localStart->format('d/m/Y')) ?></dd></div><div><dt>Giờ bắt đầu</dt><dd><?= h($localStart->format('H:i')) ?> (UTC+7)</dd></div><div><dt>Trạng thái</dt><dd><?= h(nhan_trang_thai($tran->getTrangThai())) ?></dd></div></dl></section>
    <section class="match-panel" id="match-events"><h2><i class="bi bi-list-ol"></i> Diễn biến chính</h2><?php if ($su_kien === []): ?><p class="match-empty">Chưa có sự kiện trận đấu từ nguồn dữ liệu.</p><?php else: ?><ol class="match-timeline"><?php foreach ($su_kien as $event): ?><li><time><?= (int) ($event['phut'] ?? 0) ?>′</time><span><b><?= h((string) ($event['loai'] ?? 'Sự kiện')) ?></b><small><?= h((string) ($event['cau_thu'] ?? '')) ?> · <?= h((string) ($event['doi_ten'] ?? '')) ?></small></span></li><?php endforeach; ?></ol><?php endif; ?></section>
    <section class="match-panel" id="match-stats"><h2><i class="bi bi-bar-chart"></i> Thống kê trận đấu</h2><div class="match-stat-teams"><span><?= h($home) ?></span><?= crest_html($home, $homeLogo, 'ink', 'sm') ?><span class="match-stat-spacer"></span><?= crest_html($away, $awayLogo, 'muted', 'sm') ?><span><?= h($away) ?></span></div><?php if ($statRows === []): ?><p class="match-empty">Thống kê chưa được API cung cấp cho trận này.</p><?php else: ?><?php foreach ($statRows as [$label,$homeValue,$awayValue]): ?><div class="match-stat"><b><?= h((string) $homeValue) ?></b><span><?= h($label) ?></span><b><?= h((string) $awayValue) ?></b><div class="match-stat-bar"><i style="--home-share:<?= (float) str_replace('%','',$homeValue) ?>%"></i></div></div><?php endforeach; ?><?php endif; ?></section>
    <section class="match-panel match-panel--wide" id="match-lineups"><h2><i class="bi bi-people"></i> Đội hình ra sân</h2><?php if ($lineups === []): ?><p class="match-empty">Đội hình chưa được API cung cấp cho trận này.</p><?php else: ?><div class="match-lineups"><?php foreach ($lineups as $team): ?><div><h3><?= h((string) ($team['team']['name'] ?? 'Đội bóng')) ?> <small><?= h((string) ($team['formation'] ?? '')) ?></small></h3><?php foreach (($team['startXI'] ?? []) as $player): ?><p><?= h((string) ($player['player']['number'] ?? '')) ?> · <?= h((string) ($player['player']['name'] ?? '')) ?></p><?php endforeach; ?></div><?php endforeach; ?></div><?php endif; ?></section>
</div>
