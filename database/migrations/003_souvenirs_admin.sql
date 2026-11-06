USE fastfootbalinfo;

ALTER TABLE san_pham_luu_niem MODIFY COLUMN ten VARCHAR(200) NOT NULL;

CREATE TABLE IF NOT EXISTS lich_su_gia_luu_niem (
    ma BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ma_san_pham INT UNSIGNED NOT NULL,
    gia_cu DECIMAL(12,0) NOT NULL,
    gia_moi DECIMAL(12,0) NOT NULL,
    ma_tai_khoan INT UNSIGNED NOT NULL,
    thoi_diem DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ma),
    KEY idx_lsgln_san_pham (ma_san_pham, thoi_diem),
    KEY idx_lsgln_tai_khoan (ma_tai_khoan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS sp_admin_ds_san_pham_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_admin_ds_san_pham_luu_niem(IN p_ma_danh_muc INT, IN p_trang INT, IN p_so_dong INT)
BEGIN
    DECLARE v_bo_qua BIGINT UNSIGNED DEFAULT 0;
    IF p_trang IS NULL OR p_trang < 1 OR p_so_dong IS NULL OR p_so_dong < 1 OR p_so_dong > 50 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tham số phân trang không hợp lệ';
    END IF;
    SET v_bo_qua = (p_trang - 1) * p_so_dong;
    SELECT sp.ma, sp.ma_danh_muc, sp.ten, sp.mo_ta, sp.gia, sp.anh_url,
           sp.con_hang, sp.hien_thi, sp.ngay_tao, dm.ten AS ten_danh_muc,
           COUNT(*) OVER () AS tong
      FROM san_pham_luu_niem sp
      INNER JOIN danh_muc_luu_niem dm ON dm.ma = sp.ma_danh_muc
     WHERE p_ma_danh_muc IS NULL OR p_ma_danh_muc = 0 OR sp.ma_danh_muc = p_ma_danh_muc
     ORDER BY sp.ngay_tao DESC, sp.ma DESC
     LIMIT p_so_dong OFFSET v_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_chi_tiet_san_pham_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_admin_chi_tiet_san_pham_luu_niem(IN p_ma INT UNSIGNED)
BEGIN
    SELECT sp.ma, sp.ma_danh_muc, sp.ten, sp.mo_ta, sp.gia, sp.anh_url,
           sp.con_hang, sp.hien_thi, sp.ngay_tao, dm.ten AS ten_danh_muc
      FROM san_pham_luu_niem sp
      INNER JOIN danh_muc_luu_niem dm ON dm.ma = sp.ma_danh_muc
     WHERE sp.ma = p_ma;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_them_san_pham_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_admin_them_san_pham_luu_niem(
    IN p_ma_danh_muc INT UNSIGNED, IN p_ten VARCHAR(200), IN p_mo_ta TEXT,
    IN p_gia BIGINT, IN p_anh_url VARCHAR(255), IN p_con_hang TINYINT,
    IN p_hien_thi TINYINT, IN p_ma_tai_khoan INT UNSIGNED
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM tai_khoan tk
        INNER JOIN vai_tro vt ON vt.vai_tro_id = tk.vai_tro_id
        WHERE tk.tai_khoan_id = p_ma_tai_khoan AND tk.trang_thai = 'HOAT_DONG'
          AND (tk.khoa_den IS NULL OR tk.khoa_den <= NOW()) AND vt.ma = 'ADMIN'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại, đã khóa hoặc không có quyền quản trị.';
    END IF;
    IF p_ten IS NULL OR CHAR_LENGTH(TRIM(p_ten)) < 2 OR CHAR_LENGTH(TRIM(p_ten)) > 200 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên sản phẩm phải có từ 2 đến 200 ký tự.';
    END IF;
    IF p_mo_ta IS NULL OR CHAR_LENGTH(p_mo_ta) > 2000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mô tả không được vượt quá 2.000 ký tự.';
    END IF;
    IF p_gia IS NULL OR p_gia < 0 OR p_gia > 100000000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá phải từ 0 đến 100.000.000 đồng.';
    END IF;
    IF p_ma_danh_muc IS NULL OR NOT EXISTS (SELECT 1 FROM danh_muc_luu_niem WHERE ma = p_ma_danh_muc) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Danh mục lưu niệm không tồn tại.';
    END IF;
    IF p_anh_url IS NULL OR p_anh_url = '' OR p_con_hang IS NULL OR p_con_hang NOT IN (0, 1)
       OR p_hien_thi IS NULL OR p_hien_thi NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Thông tin sản phẩm không hợp lệ.';
    END IF;
    INSERT INTO san_pham_luu_niem
        (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
    VALUES (p_ma_danh_muc, TRIM(p_ten), p_mo_ta, p_gia, p_anh_url, p_con_hang, p_hien_thi);
    SELECT 'OK' AS ma, LAST_INSERT_ID() AS ma_san_pham;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_sua_san_pham_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_admin_sua_san_pham_luu_niem(
    IN p_ma INT UNSIGNED, IN p_ma_danh_muc INT UNSIGNED, IN p_ten VARCHAR(200),
    IN p_mo_ta TEXT, IN p_gia BIGINT, IN p_anh_url VARCHAR(255),
    IN p_con_hang TINYINT, IN p_hien_thi TINYINT, IN p_ma_tai_khoan INT UNSIGNED
)
BEGIN
    DECLARE v_gia_cu DECIMAL(12,0);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    IF NOT EXISTS (
        SELECT 1 FROM tai_khoan tk
        INNER JOIN vai_tro vt ON vt.vai_tro_id = tk.vai_tro_id
        WHERE tk.tai_khoan_id = p_ma_tai_khoan AND tk.trang_thai = 'HOAT_DONG'
          AND (tk.khoa_den IS NULL OR tk.khoa_den <= NOW()) AND vt.ma = 'ADMIN'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại, đã khóa hoặc không có quyền quản trị.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ma = p_ma) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm lưu niệm không tồn tại.';
    END IF;
    IF p_ten IS NULL OR CHAR_LENGTH(TRIM(p_ten)) < 2 OR CHAR_LENGTH(TRIM(p_ten)) > 200 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tên sản phẩm phải có từ 2 đến 200 ký tự.';
    END IF;
    IF p_mo_ta IS NULL OR CHAR_LENGTH(p_mo_ta) > 2000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Mô tả không được vượt quá 2.000 ký tự.';
    END IF;
    IF p_gia IS NULL OR p_gia < 0 OR p_gia > 100000000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá phải từ 0 đến 100.000.000 đồng.';
    END IF;
    IF p_ma_danh_muc IS NULL OR NOT EXISTS (SELECT 1 FROM danh_muc_luu_niem WHERE ma = p_ma_danh_muc) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Danh mục lưu niệm không tồn tại.';
    END IF;
    IF p_anh_url IS NULL OR p_anh_url = '' OR p_con_hang IS NULL OR p_con_hang NOT IN (0, 1)
       OR p_hien_thi IS NULL OR p_hien_thi NOT IN (0, 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Thông tin sản phẩm không hợp lệ.';
    END IF;
    START TRANSACTION;
    SELECT gia INTO v_gia_cu FROM san_pham_luu_niem WHERE ma = p_ma FOR UPDATE;
    UPDATE san_pham_luu_niem
       SET ma_danh_muc = p_ma_danh_muc, ten = TRIM(p_ten), mo_ta = p_mo_ta,
           gia = p_gia, anh_url = p_anh_url, con_hang = p_con_hang, hien_thi = p_hien_thi
     WHERE ma = p_ma;
    IF v_gia_cu <> p_gia THEN
        INSERT INTO lich_su_gia_luu_niem (ma_san_pham, gia_cu, gia_moi, ma_tai_khoan)
        VALUES (p_ma, v_gia_cu, p_gia, p_ma_tai_khoan);
    END IF;
    COMMIT;
    SELECT 'OK' AS ma;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_doi_gia_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_admin_doi_gia_luu_niem(
    IN p_ma INT UNSIGNED, IN p_gia_moi BIGINT, IN p_ma_tai_khoan INT UNSIGNED
)
BEGIN
    DECLARE v_gia_cu DECIMAL(12,0);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    IF NOT EXISTS (
        SELECT 1 FROM tai_khoan tk
        INNER JOIN vai_tro vt ON vt.vai_tro_id = tk.vai_tro_id
        WHERE tk.tai_khoan_id = p_ma_tai_khoan AND tk.trang_thai = 'HOAT_DONG'
          AND (tk.khoa_den IS NULL OR tk.khoa_den <= NOW()) AND vt.ma = 'ADMIN'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại, đã khóa hoặc không có quyền quản trị.';
    END IF;
    IF p_gia_moi IS NULL OR p_gia_moi < 0 OR p_gia_moi > 100000000 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giá phải từ 0 đến 100.000.000 đồng.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ma = p_ma) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm lưu niệm không tồn tại.';
    END IF;
    START TRANSACTION;
    SELECT gia INTO v_gia_cu FROM san_pham_luu_niem WHERE ma = p_ma FOR UPDATE;
    IF v_gia_cu <> p_gia_moi THEN
        UPDATE san_pham_luu_niem SET gia = p_gia_moi WHERE ma = p_ma;
        INSERT INTO lich_su_gia_luu_niem (ma_san_pham, gia_cu, gia_moi, ma_tai_khoan)
        VALUES (p_ma, v_gia_cu, p_gia_moi, p_ma_tai_khoan);
    END IF;
    COMMIT;
    SELECT 'OK' AS ma;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_xoa_san_pham_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_admin_xoa_san_pham_luu_niem(IN p_ma INT UNSIGNED, IN p_ma_tai_khoan INT UNSIGNED)
BEGIN
    DECLARE v_anh_url VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    IF NOT EXISTS (
        SELECT 1 FROM tai_khoan tk
        INNER JOIN vai_tro vt ON vt.vai_tro_id = tk.vai_tro_id
        WHERE tk.tai_khoan_id = p_ma_tai_khoan AND tk.trang_thai = 'HOAT_DONG'
          AND (tk.khoa_den IS NULL OR tk.khoa_den <= NOW()) AND vt.ma = 'ADMIN'
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại, đã khóa hoặc không có quyền quản trị.';
    END IF;
    START TRANSACTION;
    SELECT anh_url INTO v_anh_url FROM san_pham_luu_niem WHERE ma = p_ma FOR UPDATE;
    IF v_anh_url IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm lưu niệm không tồn tại.';
    END IF;
    DELETE FROM san_pham_luu_niem WHERE ma = p_ma;
    COMMIT;
    SELECT 'OK' AS ma, v_anh_url AS anh_url;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_lich_su_gia_luu_niem;
DELIMITER //
CREATE PROCEDURE sp_ds_lich_su_gia_luu_niem(IN p_ma_san_pham INT UNSIGNED)
BEGIN
    SELECT ls.ma, ls.ma_san_pham, ls.gia_cu, ls.gia_moi, ls.ma_tai_khoan,
           ls.thoi_diem, tk.ten_dang_nhap
      FROM lich_su_gia_luu_niem ls
      INNER JOIN tai_khoan tk ON tk.tai_khoan_id = ls.ma_tai_khoan
     WHERE ls.ma_san_pham = p_ma_san_pham
     ORDER BY ls.thoi_diem DESC, ls.ma DESC
     LIMIT 10;
END//
DELIMITER ;
