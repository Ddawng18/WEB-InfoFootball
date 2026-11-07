<?php
$messages = [
    'added' => ['success', 'Đã thêm sản phẩm vào giỏ hàng.'],
    'updated' => ['success', 'Đã cập nhật số lượng.'],
    'deleted' => ['success', 'Đã xóa sản phẩm khỏi giỏ hàng.'],
    'cleared' => ['success', 'Đã xóa toàn bộ giỏ hàng.'],
    'quantity_capped' => ['warning', 'Số lượng sản phẩm trong giỏ đã đạt tối đa 10.'],
    'cart_full' => ['warning', 'Giỏ hàng đã đạt tối đa 20 sản phẩm khác nhau.'],
    'unavailable' => ['warning', 'Sản phẩm không còn bán hoặc đã hết hàng.'],
    'product_not_found' => ['warning', 'Không tìm thấy sản phẩm.'],
    'cart_item_missing' => ['warning', 'Sản phẩm không có trong giỏ hàng.'],
    'invalid_quantity' => ['warning', 'Số lượng phải từ 1 đến 10.'],
    'account_inactive' => ['warning', 'Tài khoản không hoạt động. Vui lòng đăng nhập lại.'],
    'csrf' => ['danger', 'Phiên làm việc hết hạn, vui lòng thử lại.'],
    'invalid_action' => ['warning', 'Thao tác giỏ hàng không hợp lệ.'],
    'cart_error' => ['danger', 'Không thể xử lý giỏ hàng lúc này. Vui lòng thử lại.'],
];
$tongTien = 0;
foreach ($items as $item) {
    if ((int) $item['con_ban'] === 1) {
        $tongTien += (float) $item['thanh_tien'];
    }
}
?>
<section class="cart-page">
    <header class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Giỏ hàng</h1>
            <p class="text-secondary mb-0">Giá sản phẩm được cập nhật theo giá hiện tại.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= h(route('souvenirs')) ?>">Tiếp tục xem lưu niệm</a>
    </header>

    <?php if (isset($messages[$msg])): [$type, $message] = $messages[$msg]; ?>
        <div class="alert alert-<?= h($type) ?>" role="status"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="card"><div class="card-body text-center py-5">
            <p class="mb-3">Giỏ hàng đang trống.</p>
            <a class="btn btn-primary" href="<?= h(route('souvenirs')) ?>">Khám phá lưu niệm</a>
        </div></div>
    <?php else: ?>
        <div class="cart-table-wrap">
            <table class="table align-middle mb-0">
                <thead><tr>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col">Đơn giá</th>
                    <th scope="col">Số lượng</th>
                    <th scope="col">Thành tiền</th>
                    <th scope="col"><span class="visually-hidden">Thao tác</span></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $available = (int) $item['con_ban'] === 1; ?>
                        <tr>
                            <td>
                                <div class="cart-product">
                                    <img src="<?= h(asset((string) $item['anh_url'])) ?>" alt="" loading="lazy">
                                    <div>
                                        <strong><?= h($item['ten']) ?></strong>
                                        <?php if (!$available): ?>
                                            <div><span class="badge text-bg-secondary"><?= (int) $item['hien_thi'] === 0 ? 'Không còn bán' : 'Hết hàng' ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= h(number_format((float) $item['gia'], 0, ',', '.')) ?> ₫</td>
                            <td>
                                <form class="cart-update-form" method="post" action="<?= h(route('giohang')) ?>">
                                    <?= App\Core\Csrf::field() ?>
                                    <input type="hidden" name="hanh_dong" value="capnhat">
                                    <input type="hidden" name="ma_san_pham" value="<?= (int) $item['ma_san_pham'] ?>">
                                    <input class="form-control form-control-sm" type="number" name="so_luong" min="1" max="10"
                                           value="<?= (int) $item['so_luong'] ?>" aria-label="Số lượng <?= h($item['ten']) ?>"
                                           <?= $available ? '' : 'disabled' ?>>
                                    <?php if ($available): ?>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Cập nhật</button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td><?= $available ? h(number_format((float) $item['thanh_tien'], 0, ',', '.')) . ' ₫' : '—' ?></td>
                            <td>
                                <form method="post" action="<?= h(route('giohang')) ?>">
                                    <?= App\Core\Csrf::field() ?>
                                    <input type="hidden" name="hanh_dong" value="xoa">
                                    <input type="hidden" name="ma_san_pham" value="<?= (int) $item['ma_san_pham'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="cart-summary">
            <strong>Tổng tiền sản phẩm còn bán</strong>
            <strong><?= h(number_format($tongTien, 0, ',', '.')) ?> ₫</strong>
        </div>
        <form class="mt-3 text-end" method="post" action="<?= h(route('giohang')) ?>">
            <?= App\Core\Csrf::field() ?>
            <input type="hidden" name="hanh_dong" value="xoahet">
            <button class="btn btn-outline-danger" type="submit">Xóa toàn bộ giỏ hàng</button>
        </form>
    <?php endif; ?>
</section>
