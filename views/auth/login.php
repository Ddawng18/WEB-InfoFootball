<?php
/** @var string $msg */
$thongDiep = [
    'SAI_MAT_KHAU' => ['danger', 'Tên đăng nhập hoặc mật khẩu không đúng.'],
    'KHONG_TON_TAI' => ['danger', 'Tên đăng nhập hoặc mật khẩu không đúng.'],
    'BI_KHOA' => ['warning', 'Tài khoản đang bị khóa. Vui lòng thử lại sau.'],
    'can-dang-nhap' => ['warning', 'Bạn cần đăng nhập để tiếp tục.'],
    'csrf' => ['danger', 'Phiên làm việc hết hạn, vui lòng thử lại.'],
    'done' => ['success', 'Đăng ký thành công, hãy đăng nhập.'],
];
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 text-center mb-3">Đăng nhập</h1>

                <?php if (isset($thongDiep[$msg])): [$loai, $nd] = $thongDiep[$msg]; ?>
                    <div class="alert alert-<?= h($loai) ?> py-2"><?= h($nd) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= h(route('dangnhap')) ?>">
                    <?= App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Tên đăng nhập</label>
                        <input class="form-control" id="username" name="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mật khẩu</label>
                        <input class="form-control" type="password" id="password" name="password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Đăng nhập</button>
                </form>
                <p class="text-center mt-3 mb-0 small">
                    Chưa có tài khoản? <a href="<?= h(route('dangky')) ?>">Đăng ký</a>
                </p>
            </div>
        </div>
    </div>
</div>
