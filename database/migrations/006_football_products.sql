-- ============================================================
-- 006_football_products.sql
-- Thay thế mục Lưu niệm (sản phẩm nội bộ + AccessTrade + giỏ hàng)
-- bằng mô-đun sản phẩm bóng đá thu thập từ các sàn thương mại điện tử.
--
-- Chạy trên CSDL đã tồn tại:
--   mysql -u root --default-character-set=utf8mb4 fastfootbalinfo < database\migrations\006_football_products.sql
--   mysql -u root --default-character-set=utf8mb4 fastfootbalinfo < database\routines.sql
-- ============================================================

USE fastfootbalinfo;

-- Gỡ routine cũ của mục Lưu niệm/đối tác/giỏ hàng.
DROP PROCEDURE IF EXISTS sp_ds_danh_muc_luu_niem;
DROP PROCEDURE IF EXISTS sp_ds_san_pham_luu_niem;
DROP PROCEDURE IF EXISTS sp_admin_ds_san_pham_luu_niem;
DROP PROCEDURE IF EXISTS sp_admin_chi_tiet_san_pham_luu_niem;
DROP PROCEDURE IF EXISTS sp_admin_them_san_pham_luu_niem;
DROP PROCEDURE IF EXISTS sp_admin_sua_san_pham_luu_niem;
DROP PROCEDURE IF EXISTS sp_admin_doi_gia_luu_niem;
DROP PROCEDURE IF EXISTS sp_admin_xoa_san_pham_luu_niem;
DROP PROCEDURE IF EXISTS sp_ds_lich_su_gia_luu_niem;
DROP PROCEDURE IF EXISTS sp_doi_tac_upsert_san_pham;
DROP PROCEDURE IF EXISTS sp_doi_tac_an_san_pham_cu;
DROP PROCEDURE IF EXISTS sp_ds_san_pham_doi_tac;
DROP PROCEDURE IF EXISTS sp_ds_doi_tac_ten_mien;
DROP PROCEDURE IF EXISTS sp_gio_hang_them;
DROP PROCEDURE IF EXISTS sp_gio_hang_doc;
DROP PROCEDURE IF EXISTS sp_gio_hang_cap_nhat;
DROP PROCEDURE IF EXISTS sp_gio_hang_xoa;
DROP PROCEDURE IF EXISTS sp_gio_hang_xoa_het;
DROP PROCEDURE IF EXISTS sp_gio_hang_dem;

-- Gỡ bảng cũ (giỏ hàng trước vì có khóa ngoại tới sản phẩm lưu niệm).
DROP TABLE IF EXISTS gio_hang;
DROP TABLE IF EXISTS lich_su_gia_luu_niem;
DROP TABLE IF EXISTS san_pham_doi_tac;
DROP TABLE IF EXISTS san_pham_luu_niem;
DROP TABLE IF EXISTS danh_muc_luu_niem;

-- Danh mục sản phẩm bóng đá.
CREATE TABLE IF NOT EXISTS danh_muc_san_pham (
    ma INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ten VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    thu_tu SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ma),
    UNIQUE KEY uq_dm_san_pham_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sản phẩm bóng đá thu thập từ sàn.
CREATE TABLE IF NOT EXISTS san_pham_bong_da (
    ma INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nguon VARCHAR(30) NOT NULL,
    ma_ngoai VARCHAR(100) NOT NULL,
    ten VARCHAR(255) NOT NULL,
    mo_ta TEXT NOT NULL,
    anh_url VARCHAR(2048) NOT NULL,
    anh_phu TEXT NOT NULL,
    gia_goc DECIMAL(12,0) NOT NULL DEFAULT 0,
    gia_hien_tai DECIMAL(12,0) NOT NULL,
    phan_tram_giam TINYINT UNSIGNED NOT NULL DEFAULT 0,
    so_tien_giam DECIMAL(12,0) NOT NULL DEFAULT 0,
    danh_muc_id INT UNSIGNED NOT NULL,
    thuong_hieu VARCHAR(100) NOT NULL DEFAULT '',
    cua_hang VARCHAR(150) NOT NULL DEFAULT '',
    ten_mien VARCHAR(100) NOT NULL,
    lien_ket_mua VARCHAR(2048) NOT NULL,
    lien_ket_goc VARCHAR(2048) NULL,
    con_hang TINYINT(1) NOT NULL DEFAULT 1,
    ngay_nguon DATETIME NULL,
    lan_kiem_tra DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ngay_dong_bo DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hien_thi TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (ma),
    UNIQUE KEY uq_spbd_nguon_ma_ngoai (nguon, ma_ngoai),
    KEY idx_spbd_hien_thi_danh_muc (hien_thi, danh_muc_id),
    KEY idx_spbd_hien_thi_ten_mien (hien_thi, ten_mien),
    KEY idx_spbd_ngay_dong_bo (ngay_dong_bo),
    KEY idx_spbd_gia (gia_hien_tai),
    CONSTRAINT fk_spbd_danh_muc FOREIGN KEY (danh_muc_id) REFERENCES danh_muc_san_pham (ma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lịch sử biến động giá.
CREATE TABLE IF NOT EXISTS lich_su_gia_san_pham_bong_da (
    ma BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ma_san_pham INT UNSIGNED NOT NULL,
    gia_cu DECIMAL(12,0) NOT NULL,
    gia_moi DECIMAL(12,0) NOT NULL,
    thoi_diem DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ma),
    KEY idx_lsgspbd_san_pham (ma_san_pham, thoi_diem),
    CONSTRAINT fk_lsgspbd_san_pham FOREIGN KEY (ma_san_pham) REFERENCES san_pham_bong_da (ma)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO danh_muc_san_pham (ten, slug, thu_tu) VALUES
    ('Giày bóng đá', 'giay-bong-da', 1),
    ('Áo đấu', 'ao-dau', 2),
    ('Bóng', 'bong', 3),
    ('Phụ kiện', 'phu-kien', 4),
    ('Khác', 'khac', 5)
ON DUPLICATE KEY UPDATE ten = VALUES(ten), thu_tu = VALUES(thu_tu);
