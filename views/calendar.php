<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>> $doi_da_them */
/** @var array<int, array<string, mixed>> $thong_bao */
$loc = $loc ?? 'all';
$tabs = [
    'all' => 'Tất cả',
    'upcoming' => 'Sắp tới',
    'past' => 'Đã qua',
    'calendar' => 'Trong lịch',
    'reminder' => 'Nhắc tôi',
];
$tz = new DateTimeZone(App\Core\Time::tz());
?>
<div class="calendar-page" data-calendar-page>
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-calendar2-week text-danger"></i> Lịch của tôi</h1>
            <p class="text-muted mb-0 small">Trận đã thêm vào lịch và các trận bạn đã bật nhắc. Thông báo chỉ hiển thị trong web.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?= h(route('lich')) ?>"><i class="bi bi-calendar3"></i> Lịch thi đấu</a>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($tabs as $key => $nhan): ?>
            <a class="btn btn-sm <?= $loc === $key ? 'btn-danger' : 'btn-outline-secondary' ?>"
               href="<?= h(route('lich-ca-nhan', ['loc' => $key])) ?>"><?= h($nhan) ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($items === []): ?>
        <div class="alert alert-info">
            Bạn chưa thêm trận nào. Mở <a href="<?= h(route('lich')) ?>">lịch thi đấu</a> hoặc trang chi tiết trận,
            bấm <b>Thêm vào lịch</b> hoặc <b>Nhắc tôi</b>.
        </div>
    <?php else: ?>
        <div class="calendar-list">
            <?php foreach ($items as $r): ?>
                <?php
                $kickoff = new DateTime((string) $r['thoi_gian_bat_dau'], new DateTimeZone('UTC'));
                $kickoff->setTimezone($tz);
                $status = (string) $r['trang_thai'];
                $live = in_array($status, ['DANG_DA', 'NGHI_GIUA_HIEP'], true);
                $pending = $status === 'CHO_CAP_NHAT';
                $soon = $status === 'SAP_DIEN_RA';
                $trongLich = (int) ($r['trong_lich'] ?? 0) === 1;
                $nhac = (int) ($r['nhac'] ?? 0) === 1;
                $score = $soon ? 'vs' : ($pending ? '–' : (int) $r['ti_so_nha'] . ' - ' . (int) $r['ti_so_khach']);
                ?>
                <article class="calendar-item">
                    <div class="calendar-item__time">
                        <b><?= h($kickoff->format('d/m')) ?></b>
                        <span><?= h($kickoff->format('H:i')) ?></span>
                    </div>
                    <div class="calendar-item__main">
                        <div class="calendar-item__league"><?= h((string) ($r['giai_ten'] ?? '')) ?></div>
                        <div class="calendar-item__teams">
                            <a href="<?= h(route('doi-chitiet', ['id' => (int) $r['doi_nha_id']])) ?>"><?= h((string) $r['doi_nha_ten']) ?></a>
                            <b><?= h($score) ?></b>
                            <a href="<?= h(route('doi-chitiet', ['id' => (int) $r['doi_khach_id']])) ?>"><?= h((string) $r['doi_khach_ten']) ?></a>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="<?= h(badge_trang_thai($status)) ?>"><?= h(nhan_trang_thai($status)) ?><?= $live && (int) $r['phut'] > 0 ? ' · ' . (int) $r['phut'] . "'" : '' ?></span>
                            <?php if ($trongLich): ?><span class="badge text-bg-secondary">Trong lịch</span><?php endif; ?>
                            <?php if ($nhac): ?><span class="badge text-bg-warning text-dark">Nhắc tôi</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="calendar-item__actions">
                        <a class="btn btn-sm btn-outline-danger" href="<?= h(route('tran', ['id' => (int) $r['tran_dau_id']])) ?>">
                            <i class="bi bi-box-arrow-up-right"></i> Chi tiết
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-calendar<?= $nhac ? ' is-on' : '' ?>"
                                data-tran="<?= (int) $r['tran_dau_id'] ?>" data-field="nhac" data-on="<?= $nhac ? '1' : '0' ?>">
                            <i class="bi <?= $nhac ? 'bi-bell-fill' : 'bi-bell' ?>"></i>
                            <span><?= $nhac ? 'Đã nhắc' : 'Nhắc tôi' ?></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-calendar<?= $trongLich ? ' is-on' : '' ?>"
                                data-tran="<?= (int) $r['tran_dau_id'] ?>" data-field="trong_lich" data-on="<?= $trongLich ? '1' : '0' ?>">
                            <i class="bi <?= $trongLich ? 'bi-calendar-check-fill' : 'bi-calendar-plus' ?>"></i>
                            <span><?= $trongLich ? 'Trong lịch' : 'Thêm vào lịch' ?></span>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?= render_pager((int) ($tong ?? 0), (int) ($moi_trang ?? 12), (int) ($trang ?? 1), ['loc' => $loc], 'lich-ca-nhan') ?>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-lg-7">
            <section class="match-panel">
                <h2><i class="bi bi-shield"></i> Đội đã thêm</h2>
                <?php if ($doi_da_them === []): ?>
                    <p class="match-empty">Chưa có đội nào từ lịch của bạn.</p>
                <?php else: ?>
                    <div class="calendar-teams">
                        <?php foreach ($doi_da_them as $d): ?>
                            <a class="calendar-team" href="<?= h(route('doi-chitiet', ['id' => (int) $d['doi_bong_id']])) ?>">
                                <?= crest_html((string) $d['ten'], (string) ($d['logo_url'] ?? ''), 'ink', 'sm') ?>
                                <span><b><?= h((string) $d['ten']) ?></b><small><?= (int) $d['so_tran'] ?> trận</small></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="match-panel">
                <h2><i class="bi bi-bell"></i> Thông báo gần đây <?php if (($chua_doc ?? 0) > 0): ?><span class="badge text-bg-danger"><?= (int) $chua_doc ?> mới</span><?php endif; ?></h2>
                <?php if ($thong_bao === []): ?>
                    <p class="match-empty">Chưa có thông báo. Khi tới giờ bóng lăn của trận đã bật nhắc, thông báo sẽ xuất hiện tại đây và trên chuông góc phải.</p>
                <?php else: ?>
                    <ul class="calendar-notifications">
                        <?php foreach ($thong_bao as $tb): ?>
                            <li class="<?= (int) ($tb['da_doc'] ?? 0) === 1 ? '' : 'is-unread' ?>">
                                <a href="<?= h(route('tran', ['id' => (int) ($tb['tran_dau_id'] ?? 0)])) ?>">
                                    <b><?= h((string) ($tb['tieu_de'] ?? '')) ?></b>
                                    <span><?= h((string) ($tb['noi_dung'] ?? '')) ?></span>
                                    <small><?= h(App\Core\Time::toDisplay((string) ($tb['ngay_tao'] ?? ''))) ?></small>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
