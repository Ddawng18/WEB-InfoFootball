USE fastfootbalinfo;

CREATE TABLE IF NOT EXISTS gio_hang (
    ma BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tai_khoan_id INT UNSIGNED NOT NULL,
    ma_san_pham INT UNSIGNED NOT NULL,
    so_luong TINYINT UNSIGNED NOT NULL,
    ngay_them DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ngay_cap_nhat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ma),
    UNIQUE KEY uq_gio_hang_tai_khoan_san_pham (tai_khoan_id, ma_san_pham),
    KEY idx_gio_hang_san_pham (ma_san_pham),
    CONSTRAINT fk_gio_hang_tai_khoan FOREIGN KEY (tai_khoan_id) REFERENCES tai_khoan (tai_khoan_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_gio_hang_san_pham FOREIGN KEY (ma_san_pham) REFERENCES san_pham_luu_niem (ma)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS sp_gio_hang_them;
DELIMITER //
CREATE PROCEDURE sp_gio_hang_them(
    IN p_tai_khoan_id INT UNSIGNED,
    IN p_ma_san_pham INT UNSIGNED,
    IN p_so_luong TINYINT UNSIGNED
)
BEGIN
    DECLARE v_tai_khoan_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_so_dong INT UNSIGNED DEFAULT 0;
    DECLARE v_so_luong_cu TINYINT UNSIGNED DEFAULT 0;
    DECLARE v_so_luong_moi TINYINT UNSIGNED DEFAULT 0;
    DECLARE v_con_hang TINYINT DEFAULT 0;
    DECLARE v_hien_thi TINYINT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT tai_khoan_id INTO v_tai_khoan_id
      FROM tai_khoan
     WHERE tai_khoan_id = p_tai_khoan_id AND trang_thai = 'HOAT_DONG'
       AND (khoa_den IS NULL OR khoa_den <= NOW())
     FOR UPDATE;
    IF v_tai_khoan_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại hoặc không hoạt động.';
    END IF;
    IF p_so_luong IS NULL OR p_so_luong < 1 OR p_so_luong > 10 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số lượng phải từ 1 đến 10.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ma = p_ma_san_pham) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm không tồn tại.';
    END IF;
    SELECT con_hang, hien_thi INTO v_con_hang, v_hien_thi
      FROM san_pham_luu_niem WHERE ma = p_ma_san_pham FOR UPDATE;
    IF v_hien_thi <> 1 OR v_con_hang <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm đang ẩn hoặc đã hết hàng.';
    END IF;
    IF EXISTS (
        SELECT 1 FROM gio_hang
         WHERE tai_khoan_id = p_tai_khoan_id AND ma_san_pham = p_ma_san_pham
    ) THEN
        SELECT so_luong INTO v_so_luong_cu FROM gio_hang
         WHERE tai_khoan_id = p_tai_khoan_id AND ma_san_pham = p_ma_san_pham;
        SET v_so_luong_moi = LEAST(10, v_so_luong_cu + p_so_luong);
        UPDATE gio_hang SET so_luong = v_so_luong_moi
         WHERE tai_khoan_id = p_tai_khoan_id AND ma_san_pham = p_ma_san_pham;
        COMMIT;
        IF v_so_luong_cu + p_so_luong > 10 THEN
            SELECT 'GIOI_HAN_SO_LUONG' AS ma;
        ELSE
            SELECT 'OK' AS ma;
        END IF;
    ELSE
        SELECT COUNT(*) INTO v_so_dong FROM gio_hang WHERE tai_khoan_id = p_tai_khoan_id;
        IF v_so_dong >= 20 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Giỏ hàng chỉ có thể chứa tối đa 20 sản phẩm khác nhau.';
        END IF;
        INSERT INTO gio_hang (tai_khoan_id, ma_san_pham, so_luong)
        VALUES (p_tai_khoan_id, p_ma_san_pham, p_so_luong);
        COMMIT;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_gio_hang_doc;
DELIMITER //
CREATE PROCEDURE sp_gio_hang_doc(IN p_tai_khoan_id INT UNSIGNED)
BEGIN
    DECLARE v_tai_khoan_id INT UNSIGNED DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT tai_khoan_id INTO v_tai_khoan_id
      FROM tai_khoan
     WHERE tai_khoan_id = p_tai_khoan_id AND trang_thai = 'HOAT_DONG'
       AND (khoa_den IS NULL OR khoa_den <= NOW())
     FOR UPDATE;
    IF v_tai_khoan_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại hoặc không hoạt động.';
    END IF;
    SELECT sp.ma AS ma_san_pham, sp.ten, sp.anh_url, sp.gia, gh.so_luong,
           CASE WHEN sp.hien_thi = 1 AND sp.con_hang = 1 THEN sp.gia * gh.so_luong ELSE 0 END AS thanh_tien,
           CASE WHEN sp.hien_thi = 1 AND sp.con_hang = 1 THEN 1 ELSE 0 END AS con_ban,
           sp.hien_thi, sp.con_hang
      FROM gio_hang gh
      INNER JOIN san_pham_luu_niem sp ON sp.ma = gh.ma_san_pham
     WHERE gh.tai_khoan_id = p_tai_khoan_id
     ORDER BY gh.ngay_them DESC, gh.ma DESC;
    COMMIT;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_gio_hang_cap_nhat;
DELIMITER //
CREATE PROCEDURE sp_gio_hang_cap_nhat(
    IN p_tai_khoan_id INT UNSIGNED,
    IN p_ma_san_pham INT UNSIGNED,
    IN p_so_luong TINYINT UNSIGNED
)
BEGIN
    DECLARE v_tai_khoan_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_con_hang TINYINT DEFAULT 0;
    DECLARE v_hien_thi TINYINT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT tai_khoan_id INTO v_tai_khoan_id
      FROM tai_khoan
     WHERE tai_khoan_id = p_tai_khoan_id AND trang_thai = 'HOAT_DONG'
       AND (khoa_den IS NULL OR khoa_den <= NOW())
     FOR UPDATE;
    IF v_tai_khoan_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại hoặc không hoạt động.';
    END IF;
    IF p_so_luong IS NULL OR p_so_luong < 1 OR p_so_luong > 10 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số lượng phải từ 1 đến 10.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ma = p_ma_san_pham) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm không tồn tại.';
    END IF;
    SELECT con_hang, hien_thi INTO v_con_hang, v_hien_thi
      FROM san_pham_luu_niem WHERE ma = p_ma_san_pham FOR UPDATE;
    IF v_hien_thi <> 1 OR v_con_hang <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm đang ẩn hoặc đã hết hàng.';
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM gio_hang
         WHERE tai_khoan_id = p_tai_khoan_id AND ma_san_pham = p_ma_san_pham
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm không có trong giỏ hàng.';
    END IF;
    UPDATE gio_hang SET so_luong = p_so_luong
     WHERE tai_khoan_id = p_tai_khoan_id AND ma_san_pham = p_ma_san_pham;
    COMMIT;
    SELECT 'OK' AS ma;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_gio_hang_xoa;
DELIMITER //
CREATE PROCEDURE sp_gio_hang_xoa(
    IN p_tai_khoan_id INT UNSIGNED,
    IN p_ma_san_pham INT UNSIGNED
)
BEGIN
    DECLARE v_tai_khoan_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_so_dong INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT tai_khoan_id INTO v_tai_khoan_id
      FROM tai_khoan
     WHERE tai_khoan_id = p_tai_khoan_id AND trang_thai = 'HOAT_DONG'
       AND (khoa_den IS NULL OR khoa_den <= NOW())
     FOR UPDATE;
    IF v_tai_khoan_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại hoặc không hoạt động.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ma = p_ma_san_pham) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sản phẩm không tồn tại.';
    END IF;
    DELETE FROM gio_hang
     WHERE tai_khoan_id = p_tai_khoan_id AND ma_san_pham = p_ma_san_pham;
    SET v_so_dong = ROW_COUNT();
    COMMIT;
    IF v_so_dong = 0 THEN
        SELECT 'KHONG_CO_TRONG_GIO' AS ma;
    ELSE
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_gio_hang_xoa_het;
DELIMITER //
CREATE PROCEDURE sp_gio_hang_xoa_het(IN p_tai_khoan_id INT UNSIGNED)
BEGIN
    DECLARE v_tai_khoan_id INT UNSIGNED DEFAULT NULL;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT tai_khoan_id INTO v_tai_khoan_id
      FROM tai_khoan
     WHERE tai_khoan_id = p_tai_khoan_id AND trang_thai = 'HOAT_DONG'
       AND (khoa_den IS NULL OR khoa_den <= NOW())
     FOR UPDATE;
    IF v_tai_khoan_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại hoặc không hoạt động.';
    END IF;
    DELETE FROM gio_hang WHERE tai_khoan_id = p_tai_khoan_id;
    COMMIT;
    SELECT 'OK' AS ma;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_gio_hang_dem;
DELIMITER //
CREATE PROCEDURE sp_gio_hang_dem(IN p_tai_khoan_id INT UNSIGNED)
BEGIN
    DECLARE v_tai_khoan_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_tong_so_luong BIGINT UNSIGNED DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    START TRANSACTION;
    SELECT tai_khoan_id INTO v_tai_khoan_id
      FROM tai_khoan
     WHERE tai_khoan_id = p_tai_khoan_id AND trang_thai = 'HOAT_DONG'
       AND (khoa_den IS NULL OR khoa_den <= NOW())
     FOR UPDATE;
    IF v_tai_khoan_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tài khoản không tồn tại hoặc không hoạt động.';
    END IF;
    SELECT COALESCE(SUM(gh.so_luong), 0) INTO v_tong_so_luong
      FROM gio_hang gh
      INNER JOIN san_pham_luu_niem sp ON sp.ma = gh.ma_san_pham
     WHERE gh.tai_khoan_id = p_tai_khoan_id AND sp.hien_thi = 1 AND sp.con_hang = 1;
    COMMIT;
    SELECT v_tong_so_luong AS tong_so_luong;
END//
DELIMITER ;
