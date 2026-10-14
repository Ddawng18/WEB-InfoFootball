-- ============================================================
-- FastFootBalInfo - schema.sql
-- CSDL MVP 8 bảng (Must) + 5 bảng Should = 13 bảng
-- Engine InnoDB, charset utf8mb4, collation utf8mb4_unicode_ci
-- Tương thích MySQL 8.0.16+ và MariaDB 10.2.1+ (có CHECK, ENUM, DECLARE HANDLER)
-- Chạy: mysql -u root < database/schema.sql
-- ============================================================

DROP DATABASE IF EXISTS fastfootbalinfo;
CREATE DATABASE fastfootbalinfo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE fastfootbalinfo;

-- ------------------------------------------------------------
-- NHÓM MUST
-- ------------------------------------------------------------

-- 1. Vai trò: USER, ADMIN  (F7, F8)
CREATE TABLE vai_tro (
    vai_tro_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ma          VARCHAR(20)  NOT NULL,
    ten         VARCHAR(50)  NOT NULL,
    PRIMARY KEY (vai_tro_id),
    UNIQUE KEY uq_vai_tro_ma (ma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tài khoản (F7, F8)
CREATE TABLE tai_khoan (
    tai_khoan_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ten_dang_nhap VARCHAR(50)  NOT NULL,
    email         VARCHAR(120) NOT NULL,
    ho_ten        VARCHAR(120) NOT NULL DEFAULT '',
    mat_khau_hash VARCHAR(255) NOT NULL,
    vai_tro_id    INT UNSIGNED NOT NULL,
    trang_thai    ENUM('HOAT_DONG','KHOA') NOT NULL DEFAULT 'HOAT_DONG',
    so_lan_sai    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    khoa_den      DATETIME NULL,
    ngay_tao      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tai_khoan_id),
    UNIQUE KEY uq_tk_ten (ten_dang_nhap),
    UNIQUE KEY uq_tk_email (email),
    KEY idx_tk_vai_tro (vai_tro_id),
    CONSTRAINT fk_tk_vai_tro FOREIGN KEY (vai_tro_id) REFERENCES vai_tro (vai_tro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Quốc gia (F4, phạm vi châu Á; khu_vuc do quản trị gán)
CREATE TABLE quoc_gia (
    quoc_gia_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ma_api      VARCHAR(60)  NOT NULL,
    ten         VARCHAR(120) NOT NULL,
    khu_vuc     VARCHAR(60)  NOT NULL DEFAULT 'Chưa phân loại',
    PRIMARY KEY (quoc_gia_id),
    UNIQUE KEY uq_qg_ma_api (ma_api)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Giải đấu (F2, F4, F6)
CREATE TABLE giai_dau (
    giai_dau_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nguon         VARCHAR(20)  NOT NULL DEFAULT 'SAMPLE',
    ma_api        VARCHAR(60)  NOT NULL,
    ten           VARCHAR(150) NOT NULL,
    quoc_gia_id   INT UNSIGNED NULL,
    mua_giai      VARCHAR(20)  NOT NULL DEFAULT '',
    dang_theo_doi TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (giai_dau_id),
    UNIQUE KEY uq_gd_nguon_ma (nguon, ma_api),
    KEY idx_gd_quoc_gia (quoc_gia_id),
    CONSTRAINT fk_gd_quoc_gia FOREIGN KEY (quoc_gia_id) REFERENCES quoc_gia (quoc_gia_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Đội bóng (F5)
CREATE TABLE doi_bong (
    doi_bong_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nguon       VARCHAR(20)  NOT NULL DEFAULT 'SAMPLE',
    ma_api      VARCHAR(60)  NOT NULL,
    ten         VARCHAR(150) NOT NULL,
    quoc_gia_id INT UNSIGNED NULL,
    logo_url    VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (doi_bong_id),
    UNIQUE KEY uq_db_nguon_ma (nguon, ma_api),
    KEY idx_db_quoc_gia (quoc_gia_id),
    CONSTRAINT fk_db_quoc_gia FOREIGN KEY (quoc_gia_id) REFERENCES quoc_gia (quoc_gia_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Trận đấu (F1-F3). Lưu giờ UTC. Trạng thái ánh xạ sang ENUM nội bộ.
CREATE TABLE tran_dau (
    tran_dau_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nguon            VARCHAR(20)  NOT NULL DEFAULT 'SAMPLE',
    ma_api           VARCHAR(60)  NOT NULL,
    giai_dau_id      INT UNSIGNED NOT NULL,
    doi_nha_id       INT UNSIGNED NOT NULL,
    doi_khach_id     INT UNSIGNED NOT NULL,
    thoi_gian_bat_dau DATETIME    NOT NULL,
    trang_thai       ENUM('SAP_DIEN_RA','DANG_DA','NGHI_GIUA_HIEP','KET_THUC','HOAN','HUY')
                     NOT NULL DEFAULT 'SAP_DIEN_RA',
    phut             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ti_so_nha        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ti_so_khach      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    cap_nhat_luc     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tran_dau_id),
    UNIQUE KEY uq_td_nguon_ma (nguon, ma_api),
    KEY idx_td_giai (giai_dau_id),
    KEY idx_td_nha (doi_nha_id),
    KEY idx_td_khach (doi_khach_id),
    KEY idx_td_thoi_gian (thoi_gian_bat_dau),
    KEY idx_td_trang_thai (trang_thai),
    CONSTRAINT fk_td_giai FOREIGN KEY (giai_dau_id) REFERENCES giai_dau (giai_dau_id),
    CONSTRAINT fk_td_nha FOREIGN KEY (doi_nha_id) REFERENCES doi_bong (doi_bong_id),
    CONSTRAINT fk_td_khach FOREIGN KEY (doi_khach_id) REFERENCES doi_bong (doi_bong_id),
    CONSTRAINT chk_td_hai_doi CHECK (doi_nha_id <> doi_khach_id),
    CONSTRAINT chk_td_ti_so CHECK (ti_so_nha >= 0 AND ti_so_khach >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Nhật ký đồng bộ (F6, F8, khóa chống chạy trùng)
CREATE TABLE nhat_ky_dong_bo (
    nhat_ky_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    loai        VARCHAR(30)  NOT NULL,
    bat_dau     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ket_thuc    DATETIME NULL,
    trang_thai  ENUM('DANG_CHAY','THANH_CONG','RONG','LOI') NOT NULL DEFAULT 'DANG_CHAY',
    so_ban_ghi  INT NOT NULL DEFAULT 0,
    so_bo_qua   INT NOT NULL DEFAULT 0,
    thong_bao   VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (nhat_ky_id),
    KEY idx_nk_loai (loai, bat_dau)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Đội bóng yêu thích (S1) - PK kép chống trùng
CREATE TABLE doi_bong_yeu_thich (
    tai_khoan_id INT UNSIGNED NOT NULL,
    doi_bong_id  INT UNSIGNED NOT NULL,
    ngay_them    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tai_khoan_id, doi_bong_id),
    KEY idx_yc_doi (doi_bong_id),
    CONSTRAINT fk_yc_tk FOREIGN KEY (tai_khoan_id) REFERENCES tai_khoan (tai_khoan_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_yc_doi FOREIGN KEY (doi_bong_id) REFERENCES doi_bong (doi_bong_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- NHÓM SHOULD
-- ------------------------------------------------------------

-- 9. Bảng xếp hạng (S2)
CREATE TABLE bang_xep_hang (
    giai_dau_id INT UNSIGNED NOT NULL,
    doi_bong_id INT UNSIGNED NOT NULL,
    mua_giai    VARCHAR(20)  NOT NULL,
    hang        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    so_tran     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    thang       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    hoa         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    thua        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ban_thang   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ban_thua    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    diem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (giai_dau_id, doi_bong_id, mua_giai),
    KEY idx_bxh_doi (doi_bong_id),
    CONSTRAINT fk_bxh_giai FOREIGN KEY (giai_dau_id) REFERENCES giai_dau (giai_dau_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_bxh_doi FOREIGN KEY (doi_bong_id) REFERENCES doi_bong (doi_bong_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Sự kiện trận (S3): bàn thắng, thẻ
CREATE TABLE su_kien_tran (
    su_kien_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tran_dau_id INT UNSIGNED NOT NULL,
    phut        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    loai        ENUM('BAN_THANG','THE_VANG','THE_DO','PHAN_LUOI') NOT NULL,
    doi_bong_id INT UNSIGNED NULL,
    cau_thu     VARCHAR(120) NOT NULL DEFAULT '',
    PRIMARY KEY (su_kien_id),
    KEY idx_sk_tran (tran_dau_id),
    CONSTRAINT fk_sk_tran FOREIGN KEY (tran_dau_id) REFERENCES tran_dau (tran_dau_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_sk_doi FOREIGN KEY (doi_bong_id) REFERENCES doi_bong (doi_bong_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Nhật ký đăng nhập (S5)
CREATE TABLE nhat_ky_dang_nhap (
    nhat_ky_dn_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tai_khoan_id  INT UNSIGNED NOT NULL,
    thoi_gian     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ket_qua       ENUM('THANH_CONG','SAI_MAT_KHAU','BI_KHOA','KHONG_TON_TAI') NOT NULL,
    ip            VARCHAR(45) NOT NULL DEFAULT '',
    PRIMARY KEY (nhat_ky_dn_id),
    KEY idx_nkdn_tk (tai_khoan_id, thoi_gian),
    CONSTRAINT fk_nkdn_tk FOREIGN KEY (tai_khoan_id) REFERENCES tai_khoan (tai_khoan_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Danh mục tin (S4)
CREATE TABLE danh_muc_tin (
    danh_muc_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ten         VARCHAR(120) NOT NULL,
    PRIMARY KEY (danh_muc_id),
    UNIQUE KEY uq_dmt_ten (ten)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Tin tức (S4) - admin nhập hoặc cào tự động (VD: VnExpress)
CREATE TABLE tin_tuc (
    tin_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    danh_muc_id INT UNSIGNED NULL,
    nguon       VARCHAR(20)  NOT NULL DEFAULT 'ADMIN',
    ma_ngoai    VARCHAR(120) NULL,
    tieu_de     VARCHAR(255) NOT NULL,
    tom_tat     VARCHAR(600) NOT NULL DEFAULT '',
    anh_url     VARCHAR(500) NOT NULL DEFAULT '',
    duong_dan   VARCHAR(500) NOT NULL DEFAULT '',
    noi_dung    MEDIUMTEXT NOT NULL,
    ngay_dang   DATETIME NULL,
    thu_tu      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ngay_tao    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tin_id),
    UNIQUE KEY uq_tt_nguon_ngoai (nguon, ma_ngoai),
    KEY idx_tt_danh_muc (danh_muc_id),
    CONSTRAINT fk_tt_danh_muc FOREIGN KEY (danh_muc_id) REFERENCES danh_muc_tin (danh_muc_id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
