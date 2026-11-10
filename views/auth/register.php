<?php
/** @var string $msg */
$oldUsername = isset($_GET['username']) && is_string($_GET['username']) ? $_GET['username'] : '';
$oldEmail = isset($_GET['email']) && is_string($_GET['email']) ? $_GET['email'] : '';
$oldHoTen = isset($_GET['ho_ten']) && is_string($_GET['ho_ten']) ? $_GET['ho_ten'] : '';
$thongDiep = [
    'TRUNG_TEN' => ['warning', 'Tên đăng nhập đã tồn tại.'],
    'TRUNG_EMAIL' => ['warning', 'Email đã được sử dụng.'],
    'UNVALID_DATA' => ['danger', 'Dữ liệu nhập vào chưa hợp lệ (tên 4-50 ký tự, mật khẩu ≥ 8 ký tự, email đúng định dạng).'],
    'csrf' => ['danger', 'Phiên làm việc hết hạn, vui lòng thử lại.'],
    'captcha' => ['danger', 'Mã xác minh chưa đúng. Vui lòng thử lại.'],
];
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 text-center mb-3">Đăng ký</h1>

                <?php if (isset($thongDiep[$msg])): [$loai, $nd] = $thongDiep[$msg]; ?>
                    <div class="alert alert-<?= h($loai) ?> py-2"><?= h($nd) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= h(route('dangky')) ?>">
                    <?= App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Tên đăng nhập</label>
                        <input class="form-control" id="username" name="username" required
                               value="<?= h($oldUsername) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ho_ten">Họ tên</label>
                        <input class="form-control" id="ho_ten" name="ho_ten" value="<?= h($oldHoTen) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" required
                               value="<?= h($oldEmail) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input class="form-control" type="password" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password2">Nhắc lại mật khẩu</label>
                        <input class="form-control" type="password" id="password2" name="password2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="captcha">Xác minh: <?= h((string) ($captcha_question ?? '')) ?></label>
                        <input class="form-control" id="captcha" name="captcha" inputmode="numeric"
                               autocomplete="off" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Đăng ký</button>
                </form>
                <p class="text-center mt-3 mb-0 small">
                    Đã có tài khoản? <a href="<?= h(route('dangnhap')) ?>">Đăng nhập</a>
                </p>
            </div>
        </div>
    </div>
</div>
