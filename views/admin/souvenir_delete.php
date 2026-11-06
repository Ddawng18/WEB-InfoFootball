<section class="souvenir-admin">
    <header class="souvenir-admin-header">
        <div>
            <h1 class="h3 mb-1">Xác nhận xóa sản phẩm</h1>
            <p class="text-muted mb-0">Thao tác này không thể hoàn tác.</p>
        </div>
    </header>
    <div class="souvenir-admin-confirm">
        <img class="souvenir-admin-thumb" src="<?= h(asset($product['anh_url'])) ?>" alt="<?= h($product['ten']) ?>" loading="lazy"
             onerror="this.onerror=null;this.src='<?= h(asset('img/souvenirs/no-image.svg')) ?>'">
        <p>Bạn có chắc muốn xóa sản phẩm <strong><?= h($product['ten']) ?></strong> không?</p>
        <div class="souvenir-admin-form-actions">
            <form method="post" action="<?= h(route('admin-souvenirs-xoa', ['id' => (int) $product['ma']])) ?>">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $product['ma'] ?>">
                <button class="souvenir-admin-form-actions__danger" type="submit">Xác nhận xóa</button>
            </form>
            <a href="<?= h(route('admin-souvenirs')) ?>">Hủy</a>
        </div>
    </div>
</section>
