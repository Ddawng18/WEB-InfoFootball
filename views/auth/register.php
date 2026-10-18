<?php
/** @var string $msg */
$thongDiep = [
    'TRUNG_TEN' => ['warning', 'Tên đăng nhập đã tồn tại.'],
    'TRUNG_EMAIL' => ['warning', 'Email đã được sử dụng.'],
    'UNVALID_DATA' => ['danger', 'Dữ liệu nhập vào chưa hợp lệ (tên 4-50 ký tự, mật khẩu ≥ 8 ký tự, email đúng định dạng).'],
    'csrf' => ['danger', 'Phiên làm việc hết hạn, vui lòng thử lại.'],
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
                        <input class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ho_ten">Họ tên</label>
                        <input class="form-control" id="ho_ten" name="ho_ten">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input class="form-control" type="password" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password2">Nhắc lại mật khẩu</label>
                        <input class="form-control" type="password" id="password2" name="password2" required>
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
