<?php
$msgMap = [
    'created' => ['success', 'Đã thêm sản phẩm lưu niệm.'],
    'updated' => ['success', 'Đã cập nhật sản phẩm lưu niệm.'],
    'deleted' => ['success', 'Đã xóa sản phẩm lưu niệm.'],
    'price_saved' => ['success', 'Đã lưu giá sản phẩm.'],
    'csrf' => ['danger', 'Phiên hết hạn hoặc mã CSRF không hợp lệ. Vui lòng thử lại.'],
    'invalid_price' => ['danger', 'Giá phải là số nguyên từ 0 đến 100.000.000.'],
    'not_found' => ['warning', 'Không tìm thấy sản phẩm lưu niệm.'],
    'save_failed' => ['danger', 'Không thể lưu thay đổi. Vui lòng kiểm tra dữ liệu và thử lại.'],
];
?>
<section class="souvenir-admin">
    <header class="souvenir-admin-header">
        <div>
            <h1 class="h3 mb-1">Quản lý lưu niệm</h1>
            <p class="text-muted mb-0">Thêm, cập nhật thông tin và quản lý giá sản phẩm.</p>
        </div>
        <a class="souvenir-admin-add" href="<?= h(route('admin-souvenirs-them')) ?>">Thêm sản phẩm</a>
    </header>

    <?php if (isset($msgMap[$msg ?? ''])): [$loai, $noiDung] = $msgMap[$msg]; ?>
        <div class="alert alert-<?= h($loai) ?> py-2"><?= h($noiDung) ?></div>
    <?php endif; ?>
    <?php if (($msg ?? '') === 'invalid_price' && $id_loi > 0): ?>
        <div class="alert alert-danger py-2">Giá vừa nhập cho sản phẩm #<?= (int) $id_loi ?> không hợp lệ: <?= h($gia_loi) ?></div>
    <?php endif; ?>

    <form class="souvenir-admin-filter" method="get" action="<?= h(App\Core\Url::to('index.php')) ?>">
        <input type="hidden" name="r" value="admin-souvenirs">
        <label for="souvenir-admin-category">Danh mục</label>
        <select id="souvenir-admin-category" name="danh_muc">
            <option value="0">Tất cả danh mục</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['ma'] ?>" <?= $category_id === (int) $category['ma'] ? 'selected' : '' ?>>
                    <?= h($category['ten']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Lọc</button>
    </form>

    <div class="souvenir-admin-table-wrap">
        <table class="souvenir-admin-table">
            <thead>
            <tr>
                <th scope="col">Ảnh</th>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Danh mục</th>
                <th scope="col">Giá</th>
                <th scope="col">Còn hàng</th>
                <th scope="col">Hiển thị</th>
                <th scope="col">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php $productId = (int) $item['ma']; ?>
                <tr>
                    <td>
                        <img class="souvenir-admin-thumb"
                             src="<?= h(asset($item['anh_url'])) ?>"
                             alt="<?= h($item['ten']) ?>"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='<?= h(asset('img/souvenirs/no-image.svg')) ?>'">
                    </td>
                    <td class="souvenir-admin-name"><?= h($item['ten']) ?></td>
                    <td><?= h($item['ten_danh_muc']) ?></td>
                    <td>
                        <form class="souvenir-admin-price" method="post" action="<?= h(route('admin-souvenirs', ['danh_muc' => $category_id, 'trang' => $trang])) ?>">
                            <?= App\Core\Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= $productId ?>">
                            <label class="visually-hidden" for="souvenir-admin-price-<?= $productId ?>">Giá sản phẩm <?= h($item['ten']) ?></label>
                            <input id="souvenir-admin-price-<?= $productId ?>"
                                   name="gia"
                                   type="number"
                                   min="0"
                                   max="100000000"
                                   step="1"
                                   required
                                   value="<?= $id_loi === $productId ? h($gia_loi) : h($item['gia']) ?>">
                            <button type="submit">Lưu giá</button>
                        </form>
                    </td>
                    <td><span class="souvenir-admin-status<?= (int) $item['con_hang'] === 1 ? ' souvenir-admin-status--on' : ' souvenir-admin-status--off' ?>"><?= (int) $item['con_hang'] === 1 ? 'Còn hàng' : 'Hết hàng' ?></span></td>
                    <td><span class="souvenir-admin-status<?= (int) $item['hien_thi'] === 1 ? ' souvenir-admin-status--on' : ' souvenir-admin-status--off' ?>"><?= (int) $item['hien_thi'] === 1 ? 'Đang hiện' : 'Đang ẩn' ?></span></td>
                    <td>
                        <div class="souvenir-admin-row-actions">
                            <a class="souvenir-admin-edit" href="<?= h(route('admin-souvenirs-sua', ['id' => $productId])) ?>">Sửa</a>
                            <a class="souvenir-admin-delete" href="<?= h(route('admin-souvenirs-xoa', ['id' => $productId])) ?>">Xóa</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td class="souvenir-admin-empty" colspan="7">Chưa có sản phẩm trong danh mục này.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= render_pager($tong, $moi_trang, $trang, ['danh_muc' => $category_id], 'admin-souvenirs') ?>
</section>
