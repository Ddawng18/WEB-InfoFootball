<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hằng số tên routine. Repository chỉ dùng các hằng số này khi gọi CALL,
 * tuyệt đối không nhận tên routine từ dữ liệu người dùng.
 */
final class RoutineName
{
    // Đồng bộ
    public const UPSERT_QUOC_GIA = 'sp_upsert_quoc_gia';
    public const UPSERT_GIAI_DAU = 'sp_upsert_giai_dau';
    public const UPSERT_DOI_BONG = 'sp_upsert_doi_bong';
    public const UPSERT_TRAN_DAU = 'sp_upsert_tran_dau';
    public const BAT_DAU_DONG_BO = 'sp_bat_dau_dong_bo';
    public const KET_THUC_DONG_BO = 'sp_ket_thuc_dong_bo';

    // Đọc dữ liệu
    public const DS_TRAN_DAU = 'sp_ds_tran_dau';
    public const CHI_TIET_TRAN_DAU = 'sp_chi_tiet_tran_dau';
    public const DS_GIAI_DAU = 'sp_ds_giai_dau';
    public const DS_DOI_BONG = 'sp_ds_doi_bong';
    public const CHI_TIET_DOI_BONG = 'sp_chi_tiet_doi_bong';

    // Tài khoản
    public const DANG_KY_TAI_KHOAN = 'sp_dang_ky_tai_khoan';
    public const LAY_TAI_KHOAN_DANG_NHAP = 'sp_lay_tai_khoan_dang_nhap';
    public const GHI_NHAN_DANG_NHAP = 'sp_ghi_nhan_dang_nhap';

    // Đội yêu thích
    public const DAT_YEU_THICH_DOI_BONG = 'sp_dat_yeu_thich_doi_bong';
    public const DS_DOI_YEU_THICH = 'sp_ds_doi_yeu_thich';
    public const KIEM_TRA_YEU_THICH = 'sp_kiem_tra_yeu_thich';

    // Quản trị
    public const THONG_KE_TONG_QUAN = 'sp_thong_ke_tong_quan';
    public const ADMIN_DS_TAI_KHOAN = 'sp_admin_ds_tai_khoan';
    public const ADMIN_DAT_TRANG_THAI_TAI_KHOAN = 'sp_admin_dat_trang_thai_tai_khoan';
    public const ADMIN_CAU_HINH_GIAI_DAU = 'sp_admin_cau_hinh_giai_dau';
    public const DS_NHAT_KY_DONG_BO = 'sp_ds_nhat_ky_dong_bo';

    // Should
    public const DS_BANG_XEP_HANG = 'sp_ds_bang_xep_hang';
    public const UPSERT_BANG_XEP_HANG = 'sp_upsert_bang_xep_hang';
    public const DS_SU_KIEN_TRAN = 'sp_ds_su_kien_tran';
    public const DS_TIN_TUC = 'sp_ds_tin_tuc';
    public const CHI_TIET_TIN_TUC = 'sp_chi_tiet_tin_tuc';
    public const UPSERT_TIN_TUC = 'sp_upsert_tin_tuc';
}
