<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CatalogRepository;
use App\Repositories\SouvenirRepository;
use App\Repositories\SyncRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

/** Khu vực quản trị (F8). Mọi hành động đều kiểm tra vai trò ADMIN. */
final class AdminController
{
    private AuthService $auth;
    private SyncRepository $sync;
    private UserRepository $users;
    private CatalogRepository $catalog;
    private SouvenirRepository $souvenirs;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->sync = new SyncRepository();
        $this->users = new UserRepository();
        $this->catalog = new CatalogRepository();
        $this->souvenirs = new SouvenirRepository();
    }

    public function dispatch(string $route): void
    {
        if (!$this->batBuocAdmin()) {
            return;
        }
        match ($route) {
            'admin' => $this->dashboard(),
            'admin-taikhoan' => $this->taiKhoan(),
            'admin-giai' => $this->cauHinhGiai(),
            'admin-souvenirs' => $this->souvenirList(),
            'admin/souvenirs' => $this->souvenirList(),
            'admin-souvenirs-them' => $this->souvenirAdd(),
            'admin-souvenirs-sua' => $this->souvenirEdit(),
            'admin-souvenirs-xoa' => $this->souvenirDeleteConfirm(),
            default => $this->dashboard(),
        };
    }

    private function dashboard(): void
    {
        View::render('admin/dashboard', [
            'title' => 'Quản trị',
            'thong_ke' => $this->sync->thongKe(),
            'nhat_ky' => $this->sync->nhatKy(20),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function taiKhoan(): void
    {
        $tuKhoa = Validator::chuoi($_GET['q'] ?? '', 100);
        $trang = Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1;
        $moiTrang = 20;
        $res = $this->users->danhSach($tuKhoa, $moiTrang, ($trang - 1) * $moiTrang);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-taikhoan', ['msg' => 'csrf']));
                return;
            }
            $id = Validator::soNguyenDuong($_POST['id'] ?? null);
            $tt = ($_POST['trang_thai'] ?? '') === 'KHOA' ? 'KHOA' : 'HOAT_DONG';
            if ($id !== null) {
                $ma = $this->users->datTrangThai($id, $tt, (int) $this->auth->taiKhoanId());
                $this->chuyenTrang(route('admin-taikhoan', ['msg' => strtolower($ma)]));
                return;
            }
        }

        View::render('admin/users', [
            'title' => 'Quản lý tài khoản',
            'items' => $res['items'],
            'tong' => $res['tong'],
            'tu_khoa' => $tuKhoa,
            'trang' => $trang,
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 40),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function cauHinhGiai(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-giai', ['msg' => 'csrf']));
                return;
            }
            $id = Validator::soNguyenDuong($_POST['id'] ?? null);
            if ($id !== null) {
                $theoDoi = ($_POST['dang_theo_doi'] ?? '0') === '1';
                $qgId = Validator::soNguyenDuong($_POST['quoc_gia_id'] ?? null);
                $khuVuc = Validator::chuoi($_POST['khu_vuc'] ?? '', 60);
                $ma = $this->sync->cauHinhGiaiDau($id, $theoDoi, $qgId, $khuVuc !== '' ? $khuVuc : null);
                $this->chuyenTrang(route('admin-giai', ['msg' => strtolower($ma)]));
                return;
            }
        }
        View::render('admin/leagues', [
            'title' => 'Cấu hình giải đấu',
            'leagues' => $this->catalog->getLeagues(null, null, null, 1000, 0)['items'],
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 40),
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function batBuocAdmin(): bool
    {
        if (!$this->auth->daDangNhap()) {
            $this->chuyenTrang(route('dangnhap', ['msg' => 'can-dang-nhap']));
            return false;
        }
        if (!$this->auth->laAdmin()) {
            http_response_code(403);
            View::render('error', [
                'title' => 'Không có quyền truy cập',
                'thong_bao' => 'Bạn không có quyền truy cập khu vực quản trị.',
                'user' => $this->auth->currentUser(),
            ]);
            return false;
        }
        return true;
    }

    private function souvenirList(): void
    {
        $categories = $this->souvenirs->getCategories();
        $categoryIds = array_map(static fn (array $category): int => (int) $category['ma'], $categories);
        $requestedCategory = Validator::soNguyenKhongAm($_GET['danh_muc'] ?? null, 0);
        $categoryId = $requestedCategory === 0 || in_array($requestedCategory, $categoryIds, true)
            ? $requestedCategory
            : 0;
        $page = min(1000000, Validator::soNguyenDuong($_GET['trang'] ?? null) ?? 1);
        $perPage = 10;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-souvenirs', ['danh_muc' => $categoryId, 'msg' => 'csrf']));
                return;
            }
            $id = Validator::soNguyenDuong($_POST['id'] ?? null);
            $price = $this->integerInput($_POST['gia'] ?? null, 0, 100000000);
            if ($id === null || $price === null) {
                $this->chuyenTrang(route('admin-souvenirs', [
                    'danh_muc' => $categoryId,
                    'msg' => 'invalid_price',
                    'id_loi' => $id ?? 0,
                    'gia_loi' => is_scalar($_POST['gia'] ?? null) ? (string) $_POST['gia'] : '',
                ]));
                return;
            }
            try {
                $this->souvenirs->changePrice($id, $price, (int) $this->auth->taiKhoanId());
                $this->chuyenTrang(route('admin-souvenirs', ['danh_muc' => $categoryId, 'msg' => 'price_saved']));
            } catch (\Throwable $e) {
                error_log('Không đổi được giá sản phẩm lưu niệm: ' . $e->getMessage());
                $this->chuyenTrang(route('admin-souvenirs', ['danh_muc' => $categoryId, 'msg' => 'save_failed']));
            }
            return;
        }

        $result = $this->souvenirs->getAdminProducts($categoryId, $page, $perPage);
        View::render('admin/souvenirs', [
            'title' => 'Quản lý lưu niệm',
            'categories' => $categories,
            'category_id' => $categoryId,
            'items' => $result['items'],
            'tong' => $result['tong'],
            'trang' => $page,
            'moi_trang' => $perPage,
            'msg' => Validator::chuoi($_GET['msg'] ?? '', 40),
            'id_loi' => Validator::soNguyenKhongAm($_GET['id_loi'] ?? null, 0),
            'gia_loi' => is_scalar($_GET['gia_loi'] ?? null) ? (string) ($_GET['gia_loi'] ?? '') : '',
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function souvenirAdd(): void
    {
        $categories = $this->souvenirs->getCategories();
        $form = $this->emptySouvenirForm();
        $errors = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'csrf']));
                return;
            }
            $form = $this->validateSouvenirForm($_POST, $categories, null, $errors);
            if ($errors === []) {
                $imagePath = $this->saveSouvenirUpload($errors);
                if ($errors === []) {
                    $imageUrl = $imagePath ?? 'img/souvenirs/no-image.svg';
                    try {
                        $this->souvenirs->addProduct(
                            $form['ma_danh_muc'],
                            $form['ten'],
                            $form['mo_ta'],
                            $form['gia_so'],
                            $imageUrl,
                            $form['con_hang'],
                            $form['hien_thi'],
                            (int) $this->auth->taiKhoanId()
                        );
                        $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'created']));
                    } catch (\Throwable $e) {
                        $this->removeUploadedImage($imagePath);
                        error_log('Không thêm được sản phẩm lưu niệm: ' . $e->getMessage());
                        $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'save_failed']));
                    }
                    return;
                }
            }
        }

        $this->renderSouvenirForm('Thêm sản phẩm lưu niệm', null, $form, $errors, $categories, []);
    }

    private function souvenirEdit(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? $_POST['id'] ?? null);
        $product = $id !== null ? $this->souvenirs->getProductById($id) : null;
        if ($product === null) {
            $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'not_found']));
            return;
        }
        $categories = $this->souvenirs->getCategories();
        $form = $this->souvenirFormFromProduct($product);
        $errors = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'csrf']));
                return;
            }
            $form = $this->validateSouvenirForm($_POST, $categories, $product, $errors);
            if ($errors === []) {
                $imagePath = $this->saveSouvenirUpload($errors);
                if ($errors === []) {
                    $imageUrl = $imagePath ?? (string) $product['anh_url'];
                    try {
                        $this->souvenirs->updateProduct(
                            $id,
                            $form['ma_danh_muc'],
                            $form['ten'],
                            $form['mo_ta'],
                            $form['gia_so'],
                            $imageUrl,
                            $form['con_hang'],
                            $form['hien_thi'],
                            (int) $this->auth->taiKhoanId()
                        );
                        if ($imagePath !== null && $imageUrl !== (string) $product['anh_url']) {
                            $this->removeUploadedImage((string) $product['anh_url']);
                        }
                        $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'updated']));
                    } catch (\Throwable $e) {
                        $this->removeUploadedImage($imagePath);
                        error_log('Không sửa được sản phẩm lưu niệm: ' . $e->getMessage());
                        $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'save_failed']));
                    }
                    return;
                }
            }
        }

        $this->renderSouvenirForm(
            'Sửa sản phẩm lưu niệm',
            $product,
            $form,
            $errors,
            $categories,
            $this->souvenirs->getPriceHistory($id)
        );
    }

    private function souvenirDeleteConfirm(): void
    {
        $id = Validator::soNguyenDuong($_GET['id'] ?? $_POST['id'] ?? null);
        $product = $id !== null ? $this->souvenirs->getProductById($id) : null;
        if ($product === null) {
            $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'not_found']));
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'csrf']));
                return;
            }
            try {
                $deleted = $this->souvenirs->deleteProduct($id, (int) $this->auth->taiKhoanId());
                if ($deleted === null || ($deleted['ma'] ?? '') !== 'OK') {
                    $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'not_found']));
                    return;
                }
                $this->removeUploadedImage((string) ($deleted['anh_url'] ?? ''));
                $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'deleted']));
            } catch (\Throwable $e) {
                error_log('Không xóa được sản phẩm lưu niệm: ' . $e->getMessage());
                $this->chuyenTrang(route('admin-souvenirs', ['msg' => 'save_failed']));
            }
            return;
        }

        View::render('admin/souvenir_delete', [
            'title' => 'Xác nhận xóa sản phẩm',
            'product' => $product,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function renderSouvenirForm(
        string $title,
        ?array $product,
        array $form,
        array $errors,
        array $categories,
        array $history
    ): void {
        View::render('admin/souvenir_form', [
            'title' => $title,
            'product' => $product,
            'form' => $form,
            'errors' => $errors,
            'categories' => $categories,
            'history' => $history,
            'user' => $this->auth->currentUser(),
        ]);
    }

    private function emptySouvenirForm(): array
    {
        return [
            'ten' => '',
            'ma_danh_muc' => 0,
            'mo_ta' => '',
            'gia' => '',
            'con_hang' => '1',
            'hien_thi' => '1',
        ];
    }

    private function souvenirFormFromProduct(array $product): array
    {
        return [
            'ten' => (string) $product['ten'],
            'ma_danh_muc' => (int) $product['ma_danh_muc'],
            'mo_ta' => (string) $product['mo_ta'],
            'gia' => (string) $product['gia'],
            'con_hang' => (string) $product['con_hang'],
            'hien_thi' => (string) $product['hien_thi'],
        ];
    }

    private function validateSouvenirForm(array $input, array $categories, ?array $product, array &$errors): array
    {
        $form = $product === null ? $this->emptySouvenirForm() : $this->souvenirFormFromProduct($product);
        $name = is_string($input['ten'] ?? null) ? trim($input['ten']) : '';
        $description = is_string($input['mo_ta'] ?? null) ? $input['mo_ta'] : '';
        $form['ten'] = $name;
        $form['mo_ta'] = $description;
        if (!Validator::doDai($name, 2, 200)) {
            $errors['ten'] = 'Tên sản phẩm bắt buộc từ 2 đến 200 ký tự.';
        }
        if (!Validator::doDai($description, 0, 2000)) {
            $errors['mo_ta'] = 'Mô tả không được vượt quá 2.000 ký tự.';
        }

        $categoryId = $this->integerInput($input['ma_danh_muc'] ?? null, 1, PHP_INT_MAX);
        $categoryIds = array_map(static fn (array $category): int => (int) $category['ma'], $categories);
        $form['ma_danh_muc'] = $categoryId ?? 0;
        if ($categoryId === null || !in_array($categoryId, $categoryIds, true)) {
            $errors['ma_danh_muc'] = 'Vui lòng chọn danh mục tồn tại.';
        }

        $price = $this->integerInput($input['gia'] ?? null, 0, 100000000);
        $form['gia'] = is_scalar($input['gia'] ?? null) ? (string) $input['gia'] : '';
        if ($price === null) {
            $errors['gia'] = 'Giá phải là số nguyên từ 0 đến 100.000.000.';
        } else {
            $form['gia_so'] = $price;
        }

        foreach (['con_hang', 'hien_thi'] as $field) {
            $value = $input[$field] ?? null;
            if (!is_string($value) || !in_array($value, ['0', '1'], true)) {
                $errors[$field] = 'Giá trị chỉ được là 0 hoặc 1.';
                $form[$field] = '0';
            } else {
                $form[$field] = $value;
            }
        }
        return $form;
    }

    private function integerInput($value, int $min, int $max): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $value)) {
            return null;
        }
        $number = (int) $value;
        return $number >= $min && $number <= $max ? $number : null;
    }

    private function saveSouvenirUpload(array &$errors): ?string
    {
        if (!isset($_FILES['anh']) || !is_array($_FILES['anh'])) {
            return null;
        }
        $file = $_FILES['anh'];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            $errors['anh'] = 'Tải ảnh lên không thành công. Vui lòng chọn lại ảnh.';
            return null;
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 2097152) {
            $errors['anh'] = 'Ảnh phải có dung lượng tối đa 2 MB.';
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mime) || !isset($extensions[$mime])) {
            $errors['anh'] = 'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.';
            return null;
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || $imageInfo[0] > 4000 || $imageInfo[1] > 4000) {
            $errors['anh'] = 'Ảnh không hợp lệ hoặc kích thước vượt quá 4.000 × 4.000.';
            return null;
        }

        $directory = APP_ROOT . '/public/img/souvenirs/uploads';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $errors['anh'] = 'Không tạo được thư mục lưu ảnh.';
            return null;
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
            $errors['anh'] = 'Không thể lưu ảnh đã tải lên.';
            return null;
        }
        return 'img/souvenirs/uploads/' . $filename;
    }

    private function removeUploadedImage(?string $imageUrl): void
    {
        if ($imageUrl === null
            || !preg_match('#^img/souvenirs/uploads/[a-f0-9]{32}\.(?:jpg|png|webp)$#D', $imageUrl)) {
            return;
        }
        $directory = realpath(APP_ROOT . '/public/img/souvenirs/uploads');
        $path = realpath(APP_ROOT . '/public/' . $imageUrl);
        if ($directory !== false && $path !== false && dirname($path) === $directory && is_file($path) && !unlink($path)) {
            error_log('Không xóa được ảnh lưu niệm đã tải lên: ' . $imageUrl);
        }
    }

    private function chuyenTrang(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
