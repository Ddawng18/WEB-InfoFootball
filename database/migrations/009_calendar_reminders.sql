-- Lịch cá nhân, nhắc trận và thông báo trong web cho từng tài khoản.
CREATE TABLE IF NOT EXISTS lich_ca_nhan (
    tai_khoan_id INT UNSIGNED NOT NULL,
    tran_dau_id  INT UNSIGNED NOT NULL,
    trong_lich   TINYINT(1) NOT NULL DEFAULT 0,
    nhac         TINYINT(1) NOT NULL DEFAULT 0,
    da_nhac      TINYINT(1) NOT NULL DEFAULT 0,
    ngay_them    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ngay_cap_nhat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tai_khoan_id, tran_dau_id),
    KEY idx_lcn_tran (tran_dau_id),
    KEY idx_lcn_nhac (nhac, da_nhac, tai_khoan_id),
    CONSTRAINT fk_lcn_tk FOREIGN KEY (tai_khoan_id) REFERENCES tai_khoan (tai_khoan_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lcn_tran FOREIGN KEY (tran_dau_id) REFERENCES tran_dau (tran_dau_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS thong_bao (
    thong_bao_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tai_khoan_id INT UNSIGNED NOT NULL,
    tran_dau_id  INT UNSIGNED NULL,
    loai         VARCHAR(30) NOT NULL DEFAULT 'NHAC_TRAN',
    tieu_de      VARCHAR(255) NOT NULL,
    noi_dung     VARCHAR(600) NOT NULL DEFAULT '',
    da_doc       TINYINT(1) NOT NULL DEFAULT 0,
    ngay_tao     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (thong_bao_id),
    UNIQUE KEY uq_tb_nhac (tai_khoan_id, tran_dau_id, loai),
    KEY idx_tb_tk_doc (tai_khoan_id, da_doc, ngay_tao),
    CONSTRAINT fk_tb_tk FOREIGN KEY (tai_khoan_id) REFERENCES tai_khoan (tai_khoan_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_tb_tran FOREIGN KEY (tran_dau_id) REFERENCES tran_dau (tran_dau_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
