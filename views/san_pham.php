<?php
use App\Core\Time;
use App\Core\Url;

$filterParams = static function (array $overrides = []) use ($category_slug, $sort, $tu_khoa, $ten_mien): array {
    $params = [
        'danh_muc' => $category_slug,
        'sap_xep' => $sort,
        'q' => $tu_khoa,
        'ten_mien' => $ten_mien,
    ];
    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }
    return array_filter($params, static fn ($value): bool => $value !== '' && $value !== null);
};
$capNhatHienThi = Time::toDisplay((string) $cap_nhat_luc);
?>
<section class="sp-page">
    <header class="sp-heading">
        <div class="sp-heading__text">
            <span class="sp-eyebrow"><i class="bi bi-shop"></i> Cửa hàng bóng đá</span>
            <h1>Sản phẩm bóng đá</h1>
            <p>Giày, áo đấu, bóng và phụ kiện được tổng hợp từ các sàn thương mại điện tử. Giá được kiểm tra lại định kỳ; bấm vào sản phẩm để mua tại trang bán gốc.</p>
        </div>
        <div class="sp-live" aria-live="polite">
            <span class="sp-live-dot" aria-hidden="true"></span>
            <span>Giá cập nhật: <strong id="sp-cap-nhat"><?= h($capNhatHienThi !== '' ? $capNhatHienThi : 'Đang cập nhật') ?></strong></span>
        </div>
    </header>

    <aside class="sp-notice">
        Giá, khuyến mãi và tình trạng hàng do sàn quyết định tại thời điểm bạn mở liên kết.
        Website tổng hợp thông tin để bạn so sánh nhanh trước khi mua.
    </aside>

    <form class="sp-toolbar" method="get" action="<?= h(Url::to('index.php')) ?>">
        <input type="hidden" name="r" value="san-pham">
        <input type="hidden" name="danh_muc" value="<?= h($category_slug) ?>">
        <input type="hidden" name="ten_mien" value="<?= h($ten_mien) ?>">
        <div class="sp-search">
            <label class="visually-hidden" for="sp-q">Tìm sản phẩm bóng đá</label>
            <input id="sp-q" type="search" name="q" value="<?= h($tu_khoa) ?>"
                   placeholder="Tìm giày, áo đấu, bóng, phụ kiện hoặc shop...">
            <button type="submit" aria-label="Tìm kiếm"><i class="bi bi-search"></i></button>
        </div>
        <div class="sp-sort">
            <label for="sp-sort">Sắp xếp</label>
            <select id="sp-sort" name="sap_xep">
                <option value="moi_nhat" <?= $sort === 'moi_nhat' ? 'selected' : '' ?>>Mới cập nhật</option>
                <option value="gia_tang" <?= $sort === 'gia_tang' ? 'selected' : '' ?>>Giá tăng dần</option>
                <option value="gia_giam" <?= $sort === 'gia_giam' ? 'selected' : '' ?>>Giá giảm dần</option>
                <option value="giam_gia" <?= $sort === 'giam_gia' ? 'selected' : '' ?>>Giảm giá nhiều nhất</option>
            </select>
            <button type="submit" class="sp-apply">Áp dụng</button>
        </div>
    </form>

    <nav class="sp-filters" aria-label="Danh mục sản phẩm bóng đá">
        <a class="sp-filter<?= $category_slug === '' ? ' active' : '' ?>"
           href="<?= h(route('san-pham', $filterParams(['danh_muc' => '']))) ?>"
           <?= $category_slug === '' ? 'aria-current="page"' : '' ?>>
            Tất cả <span><?= (int) $tong_tat_ca ?></span>
        </a>
        <?php foreach ($categories as $category): ?>
            <?php $slug = (string) $category['slug']; ?>
            <a class="sp-filter<?= $category_slug === $slug ? ' active' : '' ?>"
               href="<?= h(route('san-pham', $filterParams(['danh_muc' => $slug]))) ?>"
               <?= $category_slug === $slug ? 'aria-current="page"' : '' ?>>
                <?= h((string) $category['ten']) ?> <span><?= (int) ($category['so_san_pham'] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($domains !== []): ?>
        <nav class="sp-domains" aria-label="Lọc theo sàn">
            <a class="sp-domain<?= $ten_mien === '' ? ' active' : '' ?>"
               href="<?= h(route('san-pham', $filterParams(['ten_mien' => '']))) ?>"
               <?= $ten_mien === '' ? 'aria-current="page"' : '' ?>>Tất cả sàn</a>
            <?php foreach ($domains as $domain): ?>
                <?php $domainName = (string) $domain['ten_mien']; ?>
                <a class="sp-domain<?= $ten_mien === $domainName ? ' active' : '' ?>"
                   href="<?= h(route('san-pham', $filterParams(['ten_mien' => $domainName]))) ?>"
                   <?= $ten_mien === $domainName ? 'aria-current="page"' : '' ?>>
                    <?= h($domainName) ?> <span><?= (int) ($domain['so_san_pham'] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <section class="sp-grid" id="sp-grid" aria-label="Sản phẩm bóng đá đang bán">
        <?php if ($items === []): ?>
            <div class="sp-empty">
                <i class="bi bi-bag-x"></i>
                <strong>Chưa có sản phẩm phù hợp.</strong>
                <span>Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc. Quản trị có thể bấm “Làm mới sản phẩm bóng đá” trong trang quản trị.</span>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php extract(App\Services\FootballProductPresenter::present($item)); ?>
                <article class="sp-card"
                         data-sp-id="<?= (int) $item['ma'] ?>"
                         data-sp-current="<?= (int) $price ?>"
                         data-sp-original="<?= (int) $original ?>"
                         data-sp-discount="<?= $percent ?>"
                         data-sp-stock="<?= $inStock ? 1 : 0 ?>">
                    <figure class="sp-image">
                        <?php if ($buyLink !== null): ?>
                            <a class="sp-image-link" href="<?= h($buyLink) ?>" target="_blank"
                               rel="noopener noreferrer sponsored" aria-label="Mở trang bán <?= h($item['ten']) ?>">
                                <img src="<?= h($image) ?>" alt="<?= h($item['ten']) ?>" loading="lazy"
                                     referrerpolicy="no-referrer"
                                     onerror="this.onerror=null;this.src='<?= h($fallbackImage) ?>'">
                            </a>
                        <?php else: ?>
                            <img src="<?= h($image) ?>" alt="<?= h($item['ten']) ?>" loading="lazy"
                                 referrerpolicy="no-referrer"
                                 onerror="this.onerror=null;this.src='<?= h($fallbackImage) ?>'">
                        <?php endif; ?>
                        <span class="sp-cat"><?= h((string) $item['ten_danh_muc']) ?></span>
                        <span class="sp-discount<?= $percent > 0 ? '' : ' d-none' ?>" data-sp-discount-label>-<?= $percent ?>%</span>
                        <span class="sp-out<?= $inStock ? ' d-none' : '' ?>" data-sp-out>Tạm hết hàng</span>
                    </figure>
                    <div class="sp-body">
                        <?php if ($extraImages !== []): ?>
                            <div class="sp-thumbs" aria-label="Ảnh sản phẩm">
                                <?php foreach ($extraImages as $thumb): ?>
                                    <img src="<?= h($thumb) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"
                                         onerror="this.style.display='none'">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="sp-source">
                            <span><i class="bi bi-shop"></i> <?= h($source) ?></span>
                            <?php if ($brand !== ''): ?><span class="sp-brand"><?= h($brand) ?></span><?php endif; ?>
                        </div>
                        <?php if ($shop !== ''): ?>
                            <div class="sp-shop"><i class="bi bi-shop-window"></i> Shop: <strong><?= h($shop) ?></strong></div>
                        <?php endif; ?>
                        <h2>
                            <?php if ($buyLink !== null): ?>
                                <a href="<?= h($buyLink) ?>" target="_blank"
                                   rel="noopener noreferrer sponsored"><?= h((string) $item['ten']) ?></a>
                            <?php else: ?>
                                <?= h((string) $item['ten']) ?>
                            <?php endif; ?>
                        </h2>
                        <?php if ($descriptionShort !== ''): ?>
                            <p class="sp-desc"><?= h($descriptionShort) ?><?= $hasMore ? '…' : '' ?></p>
                            <?php if ($hasMore): ?>
                                <details class="sp-more">
                                    <summary>Mô tả chi tiết</summary>
                                    <p><?= h($description) ?></p>
                                </details>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="sp-prices">
                            <span class="sp-price" data-sp-price><?= h(number_format($price, 0, ',', '.')) ?> ₫</span>
                            <span class="sp-original<?= $original > $price ? '' : ' d-none' ?>" data-sp-original-price><?= h(number_format($original, 0, ',', '.')) ?> ₫</span>
                            <span class="sp-save<?= $original > $price ? '' : ' d-none' ?>" data-sp-save>Tiết kiệm <?= h(number_format($saved, 0, ',', '.')) ?> ₫</span>
                        </div>
                        <div class="sp-meta">
                            <span><i class="bi bi-clock-history"></i> <span data-sp-updated><?= h($updated !== '' ? $updated : 'Đang cập nhật') ?></span></span>
                            <span class="sp-stock-off<?= $inStock ? ' d-none' : '' ?>" data-sp-stock-off>Sàn đang báo hết hàng</span>
                        </div>
                        <?php if ($buyLink !== null): ?>
                            <a class="sp-buy" href="<?= h($buyLink) ?>" target="_blank"
                               rel="noopener noreferrer sponsored">
                                Xem tại <?= h($source) ?> <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="sp-buy sp-buy--disabled">Liên kết tạm thời không khả dụng</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?= render_pager($tong, $moi_trang, $trang, [
        'danh_muc' => $category_slug,
        'sap_xep' => $sort,
        'q' => $tu_khoa,
        'ten_mien' => $ten_mien,
    ], 'san-pham') ?>
</section>
