<section class="souvenir-page">
    <header class="souvenir-heading<?= isset($user) && $user !== null && $user->isAdmin() ? ' souvenir-admin-header' : '' ?>">
        <div>
            <h1>Lưu niệm</h1>
            <p>Khám phá những món quà đậm chất bóng đá dành cho người hâm mộ.</p>
        </div>
        <?php if (isset($user) && $user !== null && $user->isAdmin()): ?>
            <a class="souvenir-admin-add" href="<?= h(route('admin-souvenirs-them')) ?>">Thêm sản phẩm</a>
        <?php endif; ?>
    </header>

    <div class="souvenir-toolbar">
        <nav class="souvenir-filters" aria-label="Danh mục lưu niệm">
            <a class="souvenir-filter<?= $category_id === 0 ? ' active' : '' ?>"
               href="<?= h(route('souvenirs', ['sap_xep' => $sort])) ?>"
               <?= $category_id === 0 ? 'aria-current="page"' : '' ?>>Tất cả</a>
            <?php foreach ($categories as $category): ?>
                <?php $categoryMa = (int) $category['ma']; ?>
                <a class="souvenir-filter<?= $category_id === $categoryMa ? ' active' : '' ?>"
                   href="<?= h(route('souvenirs', ['danh_muc' => $categoryMa, 'sap_xep' => $sort])) ?>"
                   <?= $category_id === $categoryMa ? 'aria-current="page"' : '' ?>><?= h($category['ten']) ?></a>
            <?php endforeach; ?>
        </nav>

        <form class="souvenir-sort" method="get" action="<?= h(App\Core\Url::to('index.php')) ?>">
            <input type="hidden" name="r" value="souvenirs">
            <input type="hidden" name="danh_muc" value="<?= $category_id ?>">
            <label for="souvenir-sort-select">Sắp xếp</label>
            <select id="souvenir-sort-select" name="sap_xep">
                <option value="moi_nhat" <?= $sort === 'moi_nhat' ? 'selected' : '' ?>>Mới nhất</option>
                <option value="gia_tang" <?= $sort === 'gia_tang' ? 'selected' : '' ?>>Giá tăng dần</option>
                <option value="gia_giam" <?= $sort === 'gia_giam' ? 'selected' : '' ?>>Giá giảm dần</option>
            </select>
            <button type="submit">Áp dụng</button>
        </form>
    </div>

    <section class="souvenir-products" aria-label="Sản phẩm lưu niệm">
        <?php if ($items === []): ?>
            <div class="souvenir-empty">Chưa có sản phẩm trong danh mục này</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <article class="souvenir-card">
                    <?php if ((int) $item['con_hang'] === 0): ?>
                        <span class="souvenir-stock">Hết hàng</span>
                    <?php endif; ?>
                    <figure class="souvenir-image">
                        <img src="<?= h(asset($item['anh_url'])) ?>"
                             alt="<?= h($item['ten']) ?>"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='<?= h(asset('img/souvenirs/no-image.svg')) ?>'">
                    </figure>
                    <div class="souvenir-card-content">
                        <span class="souvenir-category"><?= h($item['ten_danh_muc']) ?></span>
                        <h2><?= h($item['ten']) ?></h2>
                        <p class="souvenir-price"><?= h(number_format((float) $item['gia'], 0, ',', '.')) ?> ₫</p>
                        <?php if (isset($user) && $user !== null && $user->isAdmin()): ?>
                            <div class="souvenir-admin-row-actions">
                                <a class="souvenir-admin-edit" href="<?= h(route('admin-souvenirs-sua', ['id' => (int) $item['ma']])) ?>">Sửa</a>
                                <a class="souvenir-admin-delete" href="<?= h(route('admin-souvenirs-xoa', ['id' => (int) $item['ma']])) ?>">Xóa</a>
                            </div>
                        <?php endif; ?>
                        <?php if ((int) $item['con_hang'] === 0): ?>
                            <button class="souvenir-cart" type="button" disabled title="Hết hàng">Hết hàng</button>
                        <?php else: ?>
                            <button class="souvenir-cart" type="button" disabled title="Chức năng đang phát triển">Thêm vào giỏ hàng</button>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <?= render_pager($tong, $moi_trang, $trang, ['danh_muc' => $category_id, 'sap_xep' => $sort], 'souvenirs') ?>
</section>
