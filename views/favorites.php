<?php
/** @var array<int, array<string, mixed>> $items */
?>
<h1 class="h3 mb-3"></i> Đội bóng yêu thích</h1>

<?php if ($items === []): ?>
    <div class="alert alert-info">
        Bạn chưa lưu đội bóng nào. Vào <a href="<?= h(route('doi')) ?>">Đội bóng</a> và bấm nút trái tim.
    </div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($items as $r): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3" style="min-width:0">
                    <?= crest_html((string) $r['ten'], (string) ($r['logo_url'] ?? ''), 'ink', 'sm') ?>
                    <div style="min-width:0">
                        <a class="fw-semibold text-decoration-none"
                           href="<?= h(route('doi-chitiet', ['id' => (int) $r['doi_bong_id']])) ?>">
                            <?= h((string) $r['ten']) ?>
                        </a>
                        <div class="text-muted small">
                            <i class="bi bi-geo-alt"></i> <?= h((string) ($r['quoc_gia_ten'] ?? 'Chưa phân loại')) ?>
                            <?php if (!empty($r['tran_sap_toi'])): ?>
                                &middot; Trận tới: <?= h(App\Core\Time::toDisplay((string) $r['tran_sap_toi'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger fav-btn"
                        data-doi="<?= (int) $r['doi_bong_id'] ?>" data-bat="0">
                    🥀</i> Bỏ
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="text-muted small mt-2" id="fav-empty" hidden>Danh sách yêu thích đang trống.</p>
    <?= render_pager($tong ?? 0, $moi_trang ?? 10, $trang ?? 1, [], 'yeuthich') ?>
<?php endif; ?>
