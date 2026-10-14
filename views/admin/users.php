<?php
/** @var array<int, array<string, mixed>> $items */
$moiTrang = 20;
$tongTrang = max(1, (int) ceil(($tong ?? 0) / $moiTrang));
$msgMap = [
    'ok' => ['success', 'Đã cập nhật tài khoản.'],
    'khong_the_khoa_chinh_minh' => ['warning', 'Không thể khóa chính tài khoản đang đăng nhập.'],
    'khong_ton_tai' => ['danger', 'Tài khoản không tồn tại.'],
    'csrf' => ['danger', 'Phiên hết hạn, vui lòng thử lại.'],
];
?>
<h1 class="h3 mb-3"><i class="bi bi-person-badge"></i> Quản lý tài khoản</h1>

<?php if (isset($msgMap[$msg ?? ''])): [$loai, $nd] = $msgMap[$msg]; ?>
    <div class="alert alert-<?= h($loai) ?> py-2"><?= h($nd) ?></div>
<?php endif; ?>

<form class="row g-2 mb-3" method="get" action="<?= h(App\Core\Url::to('index.php')) ?>">
    <input type="hidden" name="r" value="admin-taikhoan">
    <div class="col-8 col-md-4">
        <input class="form-control form-control-sm" name="q" value="<?= h($tu_khoa) ?>" placeholder="Tên đăng nhập hoặc email">
    </div>
    <div class="col-4 col-md-2 d-grid">
        <button class="btn btn-primary btn-sm" type="submit">Tìm</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
            <tr><th>#</th><th>Tên đăng nhập</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th>
                <th class="text-end">Sai</th><th>Khóa đến</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $u): ?>
                <tr>
                    <td><?= (int) $u['tai_khoan_id'] ?></td>
                    <td><?= h((string) $u['ten_dang_nhap']) ?></td>
                    <td class="small"><?= h((string) $u['email']) ?></td>
                    <td><span class="badge <?= ($u['vai_tro_ma'] ?? '') === 'ADMIN' ? 'text-bg-dark' : 'text-bg-light' ?>">
                        <?= h((string) $u['vai_tro_ma']) ?></span></td>
                    <td><span class="badge <?= ($u['trang_thai'] ?? '') === 'HOAT_DONG' ? 'text-bg-success' : 'text-bg-danger' ?>">
                        <?= h((string) $u['trang_thai']) ?></span></td>
                    <td class="text-end"><?= (int) $u['so_lan_sai'] ?></td>
                    <td class="small"><?= h(App\Core\Time::toDisplay((string) ($u['khoa_den'] ?? ''))) ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline" action="<?= h(route('admin-taikhoan')) ?>">
                            <?= App\Core\Csrf::field() ?>
                            <input type="hidden" name="id" value="<?= (int) $u['tai_khoan_id'] ?>">
                            <input type="hidden" name="trang_thai" value="<?= ($u['trang_thai'] ?? '') === 'HOAT_DONG' ? 'KHOA' : 'HOAT_DONG' ?>">
                            <button class="btn btn-sm btn-outline-<?= ($u['trang_thai'] ?? '') === 'HOAT_DONG' ? 'danger' : 'success' ?>" type="submit">
                                <?= ($u['trang_thai'] ?? '') === 'HOAT_DONG' ? 'Khóa' : 'Mở' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="8" class="text-center text-muted">Không có tài khoản nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($tongTrang > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $tongTrang; $i++): ?>
            <li class="page-item <?= $i === $trang ? 'active' : '' ?>">
                <a class="page-link" href="<?= h(route('admin-taikhoan', ['q' => $tu_khoa, 'trang' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
