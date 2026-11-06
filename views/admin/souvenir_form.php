<section class="souvenir-admin">
    <header class="souvenir-admin-header">
        <div>
            <h1 class="h3 mb-1"><?= h($title) ?></h1>
            <p class="text-muted mb-0">Các trường có dấu * là bắt buộc.</p>
        </div>
        <a class="souvenir-admin-secondary" href="<?= h(route('admin-souvenirs')) ?>">Quay lại danh sách</a>
    </header>

    <form class="souvenir-admin-form" method="post"
          action="<?= h($product === null ? route('admin-souvenirs-them') : route('admin-souvenirs-sua', ['id' => (int) $product['ma']])) ?>"
          enctype="multipart/form-data">
        <?= App\Core\Csrf::field() ?>
        <?php if ($product !== null): ?>
            <input type="hidden" name="id" value="<?= (int) $product['ma'] ?>">
        <?php endif; ?>

        <div class="souvenir-admin-field">
            <label for="souvenir-admin-name">Tên sản phẩm *</label>
            <input id="souvenir-admin-name" name="ten" type="text" minlength="2" maxlength="200" required value="<?= h($form['ten']) ?>">
            <?php if (isset($errors['ten'])): ?><small class="souvenir-admin-error"><?= h($errors['ten']) ?></small><?php endif; ?>
        </div>

        <div class="souvenir-admin-field">
            <label for="souvenir-admin-category">Danh mục *</label>
            <select id="souvenir-admin-category" name="ma_danh_muc" required>
                <option value="">Chọn danh mục</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['ma'] ?>" <?= (int) $form['ma_danh_muc'] === (int) $category['ma'] ? 'selected' : '' ?>>
                        <?= h($category['ten']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['ma_danh_muc'])): ?><small class="souvenir-admin-error"><?= h($errors['ma_danh_muc']) ?></small><?php endif; ?>
        </div>

        <div class="souvenir-admin-field">
            <label for="souvenir-admin-description">Mô tả</label>
            <textarea id="souvenir-admin-description" name="mo_ta" maxlength="2000" rows="5"><?= h($form['mo_ta']) ?></textarea>
            <?php if (isset($errors['mo_ta'])): ?><small class="souvenir-admin-error"><?= h($errors['mo_ta']) ?></small><?php endif; ?>
        </div>

        <div class="souvenir-admin-field">
            <label for="souvenir-admin-price">Giá (VNĐ) *</label>
            <input id="souvenir-admin-price" name="gia" type="number" min="0" max="100000000" step="1" required value="<?= h($form['gia']) ?>">
            <?php if (isset($errors['gia'])): ?><small class="souvenir-admin-error"><?= h($errors['gia']) ?></small><?php endif; ?>
        </div>

        <div class="souvenir-admin-field">
            <label for="souvenir-admin-image">Ảnh sản phẩm</label>
            <input id="souvenir-admin-image" name="anh" type="file" accept=".jpg,.jpeg,.png,.webp">
            <small>JPG, PNG hoặc WebP; tối đa 2 MB, kích thước không quá 4.000 × 4.000. Bỏ trống để giữ ảnh hiện tại.</small>
            <?php if (isset($errors['anh'])): ?><small class="souvenir-admin-error"><?= h($errors['anh']) ?></small><?php endif; ?>
            <?php if ($product !== null): ?>
                <img class="souvenir-admin-form-image" src="<?= h(asset($product['anh_url'])) ?>" alt="<?= h($product['ten']) ?>" loading="lazy"
                     onerror="this.onerror=null;this.src='<?= h(asset('img/souvenirs/no-image.svg')) ?>'">
            <?php endif; ?>
        </div>

        <fieldset class="souvenir-admin-checks">
            <legend>Trạng thái</legend>
            <div class="souvenir-admin-check">
                <input type="hidden" name="con_hang" value="0">
                <input id="souvenir-admin-stock" name="con_hang" type="checkbox" value="1" <?= $form['con_hang'] === '1' ? 'checked' : '' ?>>
                <label for="souvenir-admin-stock">Còn hàng</label>
            </div>
            <?php if (isset($errors['con_hang'])): ?><small class="souvenir-admin-error"><?= h($errors['con_hang']) ?></small><?php endif; ?>
            <div class="souvenir-admin-check">
                <input type="hidden" name="hien_thi" value="0">
                <input id="souvenir-admin-visible" name="hien_thi" type="checkbox" value="1" <?= $form['hien_thi'] === '1' ? 'checked' : '' ?>>
                <label for="souvenir-admin-visible">Hiển thị trên trang Lưu niệm</label>
            </div>
            <?php if (isset($errors['hien_thi'])): ?><small class="souvenir-admin-error"><?= h($errors['hien_thi']) ?></small><?php endif; ?>
        </fieldset>

        <div class="souvenir-admin-form-actions">
            <button type="submit"><?= $product === null ? 'Thêm sản phẩm' : 'Lưu thay đổi' ?></button>
            <a href="<?= h(route('admin-souvenirs')) ?>">Hủy</a>
        </div>
    </form>

    <?php if ($product !== null): ?>
        <section class="souvenir-admin-history" aria-labelledby="souvenir-admin-history-title">
            <h2 id="souvenir-admin-history-title">10 lần đổi giá gần nhất</h2>
            <div class="souvenir-admin-table-wrap">
                <table class="souvenir-admin-table">
                    <thead><tr><th scope="col">Thời điểm</th><th scope="col">Giá cũ</th><th scope="col">Giá mới</th><th scope="col">Tài khoản</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td><?= h($entry['thoi_diem']) ?></td>
                            <td><?= h(number_format((float) $entry['gia_cu'], 0, ',', '.')) ?> ₫</td>
                            <td><?= h(number_format((float) $entry['gia_moi'], 0, ',', '.')) ?> ₫</td>
                            <td><?= h($entry['ten_dang_nhap']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($history === []): ?>
                        <tr><td class="souvenir-admin-empty" colspan="4">Chưa có lịch sử đổi giá.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>
