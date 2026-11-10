<?php
/** @var array<int, array<string, mixed>> $items @var array<string, mixed>|null $editing @var string $msg */
$edit = is_array($editing ?? null) ? $editing : [];
$msgMap = [
    'ok' => ['success', 'Đã lưu tin tức.'],
    'unvalid' => ['danger', 'Tiêu đề không được để trống.'],
    'csrf' => ['danger', 'Phiên hết hạn, vui lòng thử lại.'],
    'deleted' => ['success', 'Đã xóa tin tức.'],
    'delete_error' => ['danger', 'Không xóa được tin tức.'],
    'delete_forbidden' => ['danger', 'Chỉ xóa được tin do ADMIN soạn.'],
    'upload_error' => ['danger', 'Tải ảnh thất bại.'],
    'too_large' => ['danger', 'Ảnh quá lớn (tối đa 5 MB).'],
    'invalid_type' => ['danger', 'Chỉ nhận ảnh JPEG, PNG, WebP hoặc GIF.'],
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-newspaper"></i> Quản lý tin tức</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= h(route('admin-tintuc')) ?>">
        <i class="bi bi-plus-lg"></i> Tin mới
    </a>
</div>

<?php if (isset($msgMap[$msg ?? ''])): [$loai, $nd] = $msgMap[$msg]; ?>
    <div class="alert alert-<?= h($loai) ?> py-2"><?= h($nd) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header fw-semibold"><?= $edit === [] ? 'Thêm tin ADMIN' : 'Sửa tin ADMIN' ?></div>
    <div class="card-body">
        <form method="post" action="<?= h(route('admin-tintuc')) ?>" class="row g-3" enctype="multipart/form-data">
            <?= App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="tin_id" value="<?= (int) ($edit['tin_id'] ?? 0) ?>">
            <input type="hidden" name="ma_ngoai" value="<?= h((string) ($edit['ma_ngoai'] ?? '')) ?>">
            <div class="col-12 col-md-8">
                <label class="form-label" for="tieu_de">Tiêu đề</label>
                <input class="form-control" id="tieu_de" name="tieu_de" maxlength="250" required
                       value="<?= h((string) ($edit['tieu_de'] ?? '')) ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="danh_muc">Danh mục</label>
                <input class="form-control" id="danh_muc" name="danh_muc" maxlength="120"
                       value="<?= h((string) ($edit['danh_muc_ten'] ?? 'Bóng đá')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="tom_tat">Sapo / tóm tắt</label>
                <textarea class="form-control" id="tom_tat" name="tom_tat" rows="2" maxlength="580"><?= h((string) ($edit['tom_tat'] ?? '')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="noi_dung">Nội dung</label>
                <textarea class="form-control" id="noi_dung" name="noi_dung" rows="8" placeholder="Có thể dùng HTML cơ bản: p, strong, em, ul, li, a..."><?= h((string) ($edit['noi_dung'] ?? '')) ?></textarea>
                <div class="form-text">Nội dung được lọc an toàn trước khi hiển thị (bỏ script/iframe/on*).</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="anh_file">Ảnh (chọn file)</label>
                <input type="hidden" name="anh_url" value="<?= h((string) ($edit['anh_url'] ?? '')) ?>">
                <input class="form-control" type="file" id="anh_file" name="anh_file"
                       accept="image/jpeg,image/png,image/webp,image/gif">
                <div class="form-text">JPEG/PNG/WebP/GIF, tối đa 5 MB. Bỏ trống để giữ ảnh cũ.</div>
                <?php $anhHienTai = news_image_url((string) ($edit['anh_url'] ?? '')); ?>
                <?php if ($anhHienTai !== null): ?>
                    <img src="<?= h($anhHienTai) ?>" alt="" class="img-thumbnail mt-2" style="max-height:120px">
                <?php endif; ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="duong_dan">Link bài gốc (nếu có)</label>
                <input class="form-control" id="duong_dan" name="duong_dan" maxlength="490"
                       value="<?= h((string) ($edit['duong_dan'] ?? '')) ?>" placeholder="https://...">
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Lưu tin</button>
                <?php if ($edit !== []): ?>
                    <a class="btn btn-outline-secondary" href="<?= h(route('admin-tintuc')) ?>">Huỷ sửa</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header fw-semibold">Tin do ADMIN soạn (<?= count($items) ?>)</div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
            <tr><th>#</th><th>Tiêu đề</th><th>Danh mục</th><th>Ngày</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <tr>
                    <td><?= (int) ($row['tin_id'] ?? 0) ?></td>
                    <td><?= h((string) ($row['tieu_de'] ?? '')) ?></td>
                    <td class="small text-muted"><?= h((string) ($row['danh_muc_ten'] ?? '')) ?></td>
                    <td class="small"><?= h(App\Core\Time::toDisplay((string) ($row['ngay_dang'] ?? '')) ?: App\Core\Time::toDisplay((string) ($row['ngay_tao'] ?? ''))) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= h(route('tintuc-chitiet', ['id' => (int) ($row['tin_id'] ?? 0)])) ?>">Xem</a>
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= h(route('admin-tintuc', ['sua' => (string) ($row['ma_ngoai'] ?? $row['tin_id'] ?? '')])) ?>">Sửa</a>
                        <form method="post" action="<?= h(route('admin-tintuc')) ?>" class="d-inline"
                              onsubmit="return confirm('Xóa tin này?');">
                            <?= App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tin_id" value="<?= (int) ($row['tin_id'] ?? 0) ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Xóa">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="5" class="text-center text-muted">Chưa có tin ADMIN nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
