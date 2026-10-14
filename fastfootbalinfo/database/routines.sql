-- ============================================================
-- FastFootBalInfo - routines.sql
-- Toàn bộ nghiệp vụ SQL nằm trong routine. PHP chỉ gọi CALL tên_routine(?,...).
-- Quy ước:
--   * Routine ghi dữ liệu trả về 1 dòng có cột `ma` (mã kết quả).
--   * Không dùng tham số OUT.
--   * Routine ghi nhiều bảng dùng transaction + EXIT HANDLER -> ROLLBACK, ma='LOI_HE_THONG'.
--   * Không dùng JSON_TABLE (khác nhau giữa MySQL và MariaDB).
-- Chạy: mysql -u root fastfootbalinfo < database/routines.sql
-- ============================================================

USE fastfootbalinfo;

-- ============================================================
-- NHÓM 1: ĐỒNG BỘ (upsert + nhật ký)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_upsert_quoc_gia;
DELIMITER //
CREATE PROCEDURE sp_upsert_quoc_gia(IN p_ma_api VARCHAR(60), IN p_ten VARCHAR(120))
BEGIN
    DECLARE v_id INT UNSIGNED;
    IF p_ma_api IS NULL OR p_ma_api = '' THEN
        SELECT 'THIEU_MA_API' AS ma, NULL AS id;
    ELSE
        INSERT INTO quoc_gia (ma_api, ten) VALUES (p_ma_api, p_ten)
        ON DUPLICATE KEY UPDATE ten = VALUES(ten);
        SELECT quoc_gia_id INTO v_id FROM quoc_gia WHERE ma_api = p_ma_api;
        SELECT 'OK' AS ma, v_id AS id;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_upsert_giai_dau;
DELIMITER //
CREATE PROCEDURE sp_upsert_giai_dau(
    IN p_nguon VARCHAR(20), IN p_ma_api VARCHAR(60), IN p_ten VARCHAR(150),
    IN p_ma_quoc_gia VARCHAR(60), IN p_mua_giai VARCHAR(20))
BEGIN
    DECLARE v_qg_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_id INT UNSIGNED;
    IF p_ma_api IS NULL OR p_ma_api = '' THEN
        SELECT 'THIEU_MA_API' AS ma, NULL AS id;
    ELSE
        IF p_ma_quoc_gia IS NOT NULL AND p_ma_quoc_gia <> '' THEN
            SELECT quoc_gia_id INTO v_qg_id FROM quoc_gia WHERE ma_api = p_ma_quoc_gia LIMIT 1;
        END IF;
        INSERT INTO giai_dau (nguon, ma_api, ten, quoc_gia_id, mua_giai)
        VALUES (p_nguon, p_ma_api, p_ten, v_qg_id, COALESCE(p_mua_giai, ''))
        ON DUPLICATE KEY UPDATE ten = VALUES(ten), quoc_gia_id = VALUES(quoc_gia_id);
        SELECT giai_dau_id INTO v_id FROM giai_dau WHERE nguon = p_nguon AND ma_api = p_ma_api;
        SELECT 'OK' AS ma, v_id AS id;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_upsert_doi_bong;
DELIMITER //
CREATE PROCEDURE sp_upsert_doi_bong(
    IN p_nguon VARCHAR(20), IN p_ma_api VARCHAR(60), IN p_ten VARCHAR(150),
    IN p_ma_quoc_gia VARCHAR(60), IN p_logo_url VARCHAR(255))
BEGIN
    DECLARE v_qg_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_id INT UNSIGNED;
    IF p_ma_api IS NULL OR p_ma_api = '' THEN
        SELECT 'THIEU_MA_API' AS ma, NULL AS id;
    ELSE
        IF p_ma_quoc_gia IS NOT NULL AND p_ma_quoc_gia <> '' THEN
            SELECT quoc_gia_id INTO v_qg_id FROM quoc_gia WHERE ma_api = p_ma_quoc_gia LIMIT 1;
        END IF;
        INSERT INTO doi_bong (nguon, ma_api, ten, quoc_gia_id, logo_url)
        VALUES (p_nguon, p_ma_api, p_ten, v_qg_id, COALESCE(p_logo_url, ''))
        ON DUPLICATE KEY UPDATE ten = VALUES(ten), quoc_gia_id = VALUES(quoc_gia_id),
            -- không ghi đè logo cũ bằng giá trị rỗng
            logo_url = IF(VALUES(logo_url) IS NULL OR VALUES(logo_url) = '', logo_url, VALUES(logo_url));
        SELECT doi_bong_id INTO v_id FROM doi_bong WHERE nguon = p_nguon AND ma_api = p_ma_api;
        SELECT 'OK' AS ma, v_id AS id;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_upsert_tran_dau;
DELIMITER //
CREATE PROCEDURE sp_upsert_tran_dau(
    IN p_nguon VARCHAR(20), IN p_ma_api VARCHAR(60), IN p_ma_giai VARCHAR(60),
    IN p_ma_doi_nha VARCHAR(60), IN p_ma_doi_khach VARCHAR(60),
    IN p_thoi_gian_utc DATETIME, IN p_trang_thai VARCHAR(30), IN p_phut SMALLINT,
    IN p_ti_so_nha TINYINT, IN p_ti_so_khach TINYINT)
BEGIN
    DECLARE v_giai_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_nha_id  INT UNSIGNED DEFAULT NULL;
    DECLARE v_khach_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_trang_thai_cu VARCHAR(30) DEFAULT NULL;

    SELECT giai_dau_id INTO v_giai_id FROM giai_dau WHERE nguon = p_nguon AND ma_api = p_ma_giai LIMIT 1;
    SELECT doi_bong_id INTO v_nha_id  FROM doi_bong WHERE nguon = p_nguon AND ma_api = p_ma_doi_nha LIMIT 1;
    SELECT doi_bong_id INTO v_khach_id FROM doi_bong WHERE nguon = p_nguon AND ma_api = p_ma_doi_khach LIMIT 1;
    SELECT tran_dau_id, trang_thai INTO v_id, v_trang_thai_cu
        FROM tran_dau WHERE nguon = p_nguon AND ma_api = p_ma_api LIMIT 1;

    IF v_giai_id IS NULL OR v_nha_id IS NULL OR v_khach_id IS NULL THEN
        SELECT 'GIAI_HOAC_DOI_CHUA_CO' AS ma, NULL AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF v_nha_id = v_khach_id THEN
        SELECT 'DOI_TRUNG' AS ma, v_id AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF p_trang_thai NOT IN ('SAP_DIEN_RA','DANG_DA','NGHI_GIUA_HIEP','KET_THUC','HOAN','HUY') THEN
        SELECT 'TRANG_THAI_SAI' AS ma, v_id AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF p_ti_so_nha < 0 OR p_ti_so_khach < 0 THEN
        SELECT 'TI_SO_AM' AS ma, v_id AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF v_trang_thai_cu = 'KET_THUC' THEN
        -- Quy tắc nghiệp vụ: trận đã KET_THUC thì không ghi đè
        SELECT 'OK' AS ma, v_id AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF v_id IS NULL THEN
        INSERT INTO tran_dau (nguon, ma_api, giai_dau_id, doi_nha_id, doi_khach_id,
                              thoi_gian_bat_dau, trang_thai, phut, ti_so_nha, ti_so_khach, cap_nhat_luc)
        VALUES (p_nguon, p_ma_api, v_giai_id, v_nha_id, v_khach_id,
                p_thoi_gian_utc, p_trang_thai, COALESCE(p_phut,0), p_ti_so_nha, p_ti_so_khach, NOW());
        SELECT LAST_INSERT_ID() INTO v_id;
        SELECT 'OK' AS ma, v_id AS tran_dau_id, 'THEM' AS hanh_dong;
    ELSE
        UPDATE tran_dau
           SET giai_dau_id = v_giai_id, doi_nha_id = v_nha_id, doi_khach_id = v_khach_id,
               thoi_gian_bat_dau = p_thoi_gian_utc, trang_thai = p_trang_thai,
               phut = COALESCE(p_phut,0), ti_so_nha = p_ti_so_nha, ti_so_khach = p_ti_so_khach,
               cap_nhat_luc = NOW()
         WHERE tran_dau_id = v_id;
        SELECT 'OK' AS ma, v_id AS tran_dau_id, 'CAP_NHAT' AS hanh_dong;
    END IF;
END//
DELIMITER ;

DROP FUNCTION IF EXISTS fn_can_dong_bo;
DELIMITER //
CREATE FUNCTION fn_can_dong_bo(p_loai VARCHAR(30), p_ttl INT) RETURNS TINYINT
BEGIN
    DECLARE v_last DATETIME;
    SELECT MAX(ket_thuc) INTO v_last
        FROM nhat_ky_dong_bo WHERE loai = p_loai AND trang_thai = 'THANH_CONG';
    IF v_last IS NULL THEN
        RETURN 1;
    END IF;
    IF NOW() > DATE_ADD(v_last, INTERVAL p_ttl SECOND) THEN
        RETURN 1;
    END IF;
    RETURN 0;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_bat_dau_dong_bo;
DELIMITER //
CREATE PROCEDURE sp_bat_dau_dong_bo(IN p_loai VARCHAR(30), IN p_ttl INT)
BEGIN
    DECLARE v_dang_chay INT UNSIGNED DEFAULT NULL;
    DECLARE v_id INT UNSIGNED;
    IF fn_can_dong_bo(p_loai, p_ttl) = 0 THEN
        SELECT 'CHUA_HET_HAN' AS ma, NULL AS nhat_ky_id;
    ELSE
        SELECT nhat_ky_id INTO v_dang_chay
            FROM nhat_ky_dong_bo
            WHERE loai = p_loai AND trang_thai = 'DANG_CHAY'
              AND bat_dau > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            LIMIT 1;
        IF v_dang_chay IS NOT NULL THEN
            SELECT 'DANG_CHAY' AS ma, v_dang_chay AS nhat_ky_id;
        ELSE
            -- Dọn các dòng DANG_CHAY bị bỏ dở (quá 2 phút)
            UPDATE nhat_ky_dong_bo
               SET trang_thai = 'LOI', ket_thuc = NOW(),
                   thong_bao = 'Bỏ dở (timeout)'
             WHERE loai = p_loai AND trang_thai = 'DANG_CHAY';
            INSERT INTO nhat_ky_dong_bo (loai, bat_dau, trang_thai)
            VALUES (p_loai, NOW(), 'DANG_CHAY');
            SET v_id = LAST_INSERT_ID();
            SELECT 'OK' AS ma, v_id AS nhat_ky_id;
        END IF;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ket_thuc_dong_bo;
DELIMITER //
CREATE PROCEDURE sp_ket_thuc_dong_bo(
    IN p_id INT UNSIGNED, IN p_trang_thai VARCHAR(20),
    IN p_so_ban_ghi INT, IN p_so_bo_qua INT, IN p_thong_bao VARCHAR(500))
BEGIN
    IF p_trang_thai NOT IN ('THANH_CONG','RONG','LOI') THEN
        SELECT 'TRANG_THAI_SAI' AS ma;
    ELSE
        UPDATE nhat_ky_dong_bo
           SET ket_thuc = NOW(), trang_thai = p_trang_thai,
               so_ban_ghi = COALESCE(p_so_ban_ghi,0),
               so_bo_qua = COALESCE(p_so_bo_qua,0),
               thong_bao = COALESCE(p_thong_bao,'')
         WHERE nhat_ky_id = p_id;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

-- ============================================================
-- NHÓM 2: ĐỌC DỮ LIỆU (F1-F5)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_ds_tran_dau;
DELIMITER //
CREATE PROCEDURE sp_ds_tran_dau(
    IN p_tu_utc DATETIME, IN p_den_utc DATETIME, IN p_giai_id INT UNSIGNED,
    IN p_doi_id INT UNSIGNED, IN p_trang_thai VARCHAR(30), IN p_tu_khoa VARCHAR(100),
    IN p_gioi_han INT, IN p_bo_qua INT, IN p_nhom VARCHAR(20), IN p_sap_xep VARCHAR(10))
BEGIN
    SELECT t.tran_dau_id, t.ma_api, t.thoi_gian_bat_dau, t.trang_thai, t.phut,
           t.ti_so_nha, t.ti_so_khach, t.cap_nhat_luc,
           t.doi_nha_id, t.doi_khach_id, t.giai_dau_id,
           nha.ten AS doi_nha_ten, khach.ten AS doi_khach_ten, gd.ten AS giai_ten,
           nha.logo_url AS doi_nha_logo, khach.logo_url AS doi_khach_logo,
           COUNT(*) OVER () AS tong
      FROM tran_dau t
      JOIN doi_bong nha   ON nha.doi_bong_id = t.doi_nha_id
      JOIN doi_bong khach ON khach.doi_bong_id = t.doi_khach_id
      JOIN giai_dau gd    ON gd.giai_dau_id = t.giai_dau_id
     WHERE (p_tu_utc IS NULL OR t.thoi_gian_bat_dau >= p_tu_utc)
       AND (p_den_utc IS NULL OR t.thoi_gian_bat_dau < p_den_utc)
       AND (p_giai_id IS NULL OR t.giai_dau_id = p_giai_id)
       AND (p_doi_id IS NULL OR t.doi_nha_id = p_doi_id OR t.doi_khach_id = p_doi_id)
       AND (CASE WHEN p_nhom = 'DANG'
                 THEN t.trang_thai IN ('DANG_DA','NGHI_GIUA_HIEP')
                 ELSE (p_trang_thai IS NULL OR p_trang_thai = '' OR t.trang_thai = p_trang_thai)
            END)
       AND (p_tu_khoa IS NULL OR p_tu_khoa = ''
            OR nha.ten LIKE CONCAT('%', p_tu_khoa, '%')
            OR khach.ten LIKE CONCAT('%', p_tu_khoa, '%'))
     ORDER BY CASE WHEN p_sap_xep = 'GIAI' THEN gd.ten END ASC,
              CASE WHEN p_sap_xep = 'DESC' THEN t.thoi_gian_bat_dau END DESC,
              t.thoi_gian_bat_dau ASC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_chi_tiet_tran_dau;
DELIMITER //
CREATE PROCEDURE sp_chi_tiet_tran_dau(IN p_id INT UNSIGNED)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM tran_dau WHERE tran_dau_id = p_id) THEN
        SELECT 'KHONG_TIM_THAY' AS ma;
    ELSE
        SELECT 'OK' AS ma, t.tran_dau_id, t.ma_api, t.thoi_gian_bat_dau, t.trang_thai,
               t.phut, t.ti_so_nha, t.ti_so_khach, t.cap_nhat_luc,
               nha.ten AS doi_nha_ten, khach.ten AS doi_khach_ten,
               nha.logo_url AS doi_nha_logo, khach.logo_url AS doi_khach_logo,
               gd.ten AS giai_ten, qg.ten AS quoc_gia_ten
          FROM tran_dau t
          JOIN doi_bong nha   ON nha.doi_bong_id = t.doi_nha_id
          JOIN doi_bong khach ON khach.doi_bong_id = t.doi_khach_id
          JOIN giai_dau gd    ON gd.giai_dau_id = t.giai_dau_id
          LEFT JOIN quoc_gia qg ON qg.quoc_gia_id = gd.quoc_gia_id
         WHERE t.tran_dau_id = p_id;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_giai_dau;
DELIMITER //
CREATE PROCEDURE sp_ds_giai_dau(
    IN p_quoc_gia_id INT UNSIGNED, IN p_khu_vuc VARCHAR(60), IN p_tu_khoa VARCHAR(100),
    IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT gd.giai_dau_id, gd.ma_api, gd.ten, gd.mua_giai, gd.dang_theo_doi,
           qg.ten AS quoc_gia_ten, qg.khu_vuc, COUNT(*) OVER () AS tong
      FROM giai_dau gd
      LEFT JOIN quoc_gia qg ON qg.quoc_gia_id = gd.quoc_gia_id
     WHERE (p_quoc_gia_id IS NULL OR gd.quoc_gia_id = p_quoc_gia_id)
       AND (p_khu_vuc IS NULL OR p_khu_vuc = '' OR qg.khu_vuc = p_khu_vuc)
       AND (p_tu_khoa IS NULL OR p_tu_khoa = '' OR LOWER(gd.ten) LIKE CONCAT('%', LOWER(p_tu_khoa), '%'))
     ORDER BY gd.dang_theo_doi DESC, gd.ten ASC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_doi_bong;
DELIMITER //
CREATE PROCEDURE sp_ds_doi_bong(
    IN p_giai_id INT UNSIGNED, IN p_tu_khoa VARCHAR(100),
    IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT d.doi_bong_id, d.ma_api, d.ten, d.logo_url, qg.ten AS quoc_gia_ten,
           COUNT(*) OVER () AS tong
      FROM doi_bong d
      LEFT JOIN quoc_gia qg ON qg.quoc_gia_id = d.quoc_gia_id
     WHERE (p_giai_id IS NULL OR d.quoc_gia_id = (SELECT quoc_gia_id FROM giai_dau WHERE giai_dau_id = p_giai_id))
       AND (p_tu_khoa IS NULL OR p_tu_khoa = '' OR LOWER(d.ten) LIKE CONCAT('%', LOWER(p_tu_khoa), '%'))
     ORDER BY d.ten ASC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_chi_tiet_doi_bong;
DELIMITER //
CREATE PROCEDURE sp_chi_tiet_doi_bong(IN p_id INT UNSIGNED)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM doi_bong WHERE doi_bong_id = p_id) THEN
        SELECT 'KHONG_TIM_THAY' AS ma;
    ELSE
        SELECT 'OK' AS ma, d.doi_bong_id, d.ten, d.logo_url, qg.ten AS quoc_gia_ten,
               COUNT(t.tran_dau_id) AS so_tran,
               COALESCE(SUM(CASE WHEN t.trang_thai='KET_THUC'
                    AND ((t.doi_nha_id=d.doi_bong_id AND t.ti_so_nha>t.ti_so_khach)
                      OR (t.doi_khach_id=d.doi_bong_id AND t.ti_so_khach>t.ti_so_nha))
                    THEN 1 ELSE 0 END),0) AS thang,
               COALESCE(SUM(CASE WHEN t.trang_thai='KET_THUC' AND t.ti_so_nha=t.ti_so_khach
                    THEN 1 ELSE 0 END),0) AS hoa,
               COALESCE(SUM(CASE WHEN t.trang_thai='KET_THUC'
                    AND ((t.doi_nha_id=d.doi_bong_id AND t.ti_so_nha<t.ti_so_khach)
                      OR (t.doi_khach_id=d.doi_bong_id AND t.ti_so_khach<t.ti_so_nha))
                    THEN 1 ELSE 0 END),0) AS thua
          FROM doi_bong d
          LEFT JOIN quoc_gia qg ON qg.quoc_gia_id = d.quoc_gia_id
          LEFT JOIN tran_dau t ON (t.doi_nha_id = d.doi_bong_id OR t.doi_khach_id = d.doi_bong_id)
         WHERE d.doi_bong_id = p_id
         GROUP BY d.doi_bong_id, d.ten, qg.ten;
    END IF;
END//
DELIMITER ;

-- ============================================================
-- NHÓM 3: TÀI KHOẢN (F7)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_dang_ky_tai_khoan;
DELIMITER //
CREATE PROCEDURE sp_dang_ky_tai_khoan(
    IN p_ten_dang_nhap VARCHAR(50), IN p_email VARCHAR(120),
    IN p_ho_ten VARCHAR(120), IN p_hash VARCHAR(255))
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'LOI_HE_THONG' AS ma;
    END;
    START TRANSACTION;
    IF EXISTS (SELECT 1 FROM tai_khoan WHERE ten_dang_nhap = p_ten_dang_nhap) THEN
        ROLLBACK;
        SELECT 'TRUNG_TEN' AS ma;
    ELSEIF EXISTS (SELECT 1 FROM tai_khoan WHERE email = p_email) THEN
        ROLLBACK;
        SELECT 'TRUNG_EMAIL' AS ma;
    ELSE
        INSERT INTO tai_khoan (ten_dang_nhap, email, ho_ten, mat_khau_hash, vai_tro_id)
        VALUES (p_ten_dang_nhap, p_email, p_ho_ten, p_hash,
                (SELECT vai_tro_id FROM vai_tro WHERE ma = 'USER'));
        COMMIT;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_lay_tai_khoan_dang_nhap;
DELIMITER //
CREATE PROCEDURE sp_lay_tai_khoan_dang_nhap(IN p_ten VARCHAR(50))
BEGIN
    SELECT tk.tai_khoan_id, tk.ten_dang_nhap, tk.mat_khau_hash, tk.trang_thai,
           tk.so_lan_sai, tk.khoa_den, vt.ma AS vai_tro_ma, tk.ho_ten
      FROM tai_khoan tk
      JOIN vai_tro vt ON vt.vai_tro_id = tk.vai_tro_id
     WHERE tk.ten_dang_nhap = p_ten
     LIMIT 1;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ghi_nhan_dang_nhap;
DELIMITER //
CREATE PROCEDURE sp_ghi_nhan_dang_nhap(
    IN p_tk_id INT UNSIGNED, IN p_ket_qua VARCHAR(20), IN p_ip VARCHAR(45))
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'LOI_HE_THONG' AS ma;
    END;
    IF p_ket_qua NOT IN ('THANH_CONG','SAI_MAT_KHAU','BI_KHOA','KHONG_TON_TAI') THEN
        SELECT 'KET_QUA_SAI' AS ma;
    ELSE
        START TRANSACTION;
        INSERT INTO nhat_ky_dang_nhap (tai_khoan_id, thoi_gian, ket_qua, ip)
        VALUES (p_tk_id, NOW(), p_ket_qua, COALESCE(p_ip, ''));
        IF p_ket_qua = 'THANH_CONG' THEN
            UPDATE tai_khoan SET so_lan_sai = 0, khoa_den = NULL WHERE tai_khoan_id = p_tk_id;
        ELSEIF p_ket_qua = 'SAI_MAT_KHAU' THEN
            UPDATE tai_khoan SET so_lan_sai = so_lan_sai + 1 WHERE tai_khoan_id = p_tk_id;
            UPDATE tai_khoan SET khoa_den = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
             WHERE tai_khoan_id = p_tk_id AND so_lan_sai >= 5 AND (khoa_den IS NULL OR khoa_den < NOW());
        END IF;
        COMMIT;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

-- ============================================================
-- NHÓM 4: ĐỘI YÊU THÍCH (S1)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_dat_yeu_thich_doi_bong;
DELIMITER //
CREATE PROCEDURE sp_dat_yeu_thich_doi_bong(
    IN p_tk_id INT UNSIGNED, IN p_doi_id INT UNSIGNED, IN p_bat TINYINT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM doi_bong WHERE doi_bong_id = p_doi_id) THEN
        SELECT 'KHONG_TON_TAI' AS ma;
    ELSEIF p_bat = 1 THEN
        INSERT IGNORE INTO doi_bong_yeu_thich (tai_khoan_id, doi_bong_id) VALUES (p_tk_id, p_doi_id);
        SELECT 'OK' AS ma;
    ELSE
        DELETE FROM doi_bong_yeu_thich WHERE tai_khoan_id = p_tk_id AND doi_bong_id = p_doi_id;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_doi_yeu_thich;
DELIMITER //
CREATE PROCEDURE sp_ds_doi_yeu_thich(
    IN p_tk_id INT UNSIGNED, IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT d.doi_bong_id, d.ten, d.logo_url, qg.ten AS quoc_gia_ten, yc.ngay_them,
           (SELECT MIN(t.thoi_gian_bat_dau)
              FROM tran_dau t
             WHERE (t.doi_nha_id = d.doi_bong_id OR t.doi_khach_id = d.doi_bong_id)
               AND t.trang_thai = 'SAP_DIEN_RA'
               AND t.thoi_gian_bat_dau >= NOW()) AS tran_sap_toi,
           COUNT(*) OVER () AS tong
      FROM doi_bong_yeu_thich yc
      JOIN doi_bong d ON d.doi_bong_id = yc.doi_bong_id
      LEFT JOIN quoc_gia qg ON qg.quoc_gia_id = d.quoc_gia_id
     WHERE yc.tai_khoan_id = p_tk_id
     ORDER BY yc.ngay_them DESC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_kiem_tra_yeu_thich;
DELIMITER //
CREATE PROCEDURE sp_kiem_tra_yeu_thich(IN p_tk_id INT UNSIGNED, IN p_doi_id INT UNSIGNED)
BEGIN
    SELECT EXISTS(
        SELECT 1 FROM doi_bong_yeu_thich
         WHERE tai_khoan_id = p_tk_id AND doi_bong_id = p_doi_id
    ) AS yeu_thich;
END//
DELIMITER ;

-- ============================================================
-- NHÓM 5: QUẢN TRỊ (F8)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_thong_ke_tong_quan;
DELIMITER //
CREATE PROCEDURE sp_thong_ke_tong_quan()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM giai_dau)  AS so_giai,
        (SELECT COUNT(*) FROM doi_bong)  AS so_doi,
        (SELECT COUNT(*) FROM tran_dau)  AS so_tran,
        (SELECT COUNT(*) FROM tai_khoan) AS so_tai_khoan,
        (SELECT COUNT(*) FROM nhat_ky_dong_bo
          WHERE trang_thai='THANH_CONG' AND bat_dau >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS dong_bo_thanh_cong_24h,
        (SELECT COUNT(*) FROM nhat_ky_dong_bo
          WHERE trang_thai='LOI' AND bat_dau >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS dong_bo_loi_24h;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_ds_tai_khoan;
DELIMITER //
CREATE PROCEDURE sp_admin_ds_tai_khoan(
    IN p_tu_khoa VARCHAR(100), IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT tk.tai_khoan_id, tk.ten_dang_nhap, tk.email, tk.ho_ten, tk.trang_thai,
           tk.so_lan_sai, tk.khoa_den, tk.ngay_tao, vt.ma AS vai_tro_ma,
           COUNT(*) OVER () AS tong
      FROM tai_khoan tk
      JOIN vai_tro vt ON vt.vai_tro_id = tk.vai_tro_id
     WHERE (p_tu_khoa IS NULL OR p_tu_khoa = ''
            OR tk.ten_dang_nhap LIKE CONCAT('%', p_tu_khoa, '%')
            OR tk.email LIKE CONCAT('%', p_tu_khoa, '%'))
     ORDER BY tk.ngay_tao DESC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_dat_trang_thai_tai_khoan;
DELIMITER //
CREATE PROCEDURE sp_admin_dat_trang_thai_tai_khoan(
    IN p_id INT UNSIGNED, IN p_trang_thai VARCHAR(20), IN p_nguoi_thuc_hien INT UNSIGNED)
BEGIN
    IF p_trang_thai NOT IN ('HOAT_DONG','KHOA') THEN
        SELECT 'TRANG_THAI_SAI' AS ma;
    ELSEIF p_id = p_nguoi_thuc_hien THEN
        SELECT 'KHONG_THE_KHOA_CHINH_MINH' AS ma;
    ELSEIF NOT EXISTS (SELECT 1 FROM tai_khoan WHERE tai_khoan_id = p_id) THEN
        SELECT 'KHONG_TON_TAI' AS ma;
    ELSE
        UPDATE tai_khoan
           SET trang_thai = p_trang_thai,
               khoa_den = CASE WHEN p_trang_thai='HOAT_DONG' THEN NULL ELSE khoa_den END
         WHERE tai_khoan_id = p_id;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_admin_cau_hinh_giai_dau;
DELIMITER //
CREATE PROCEDURE sp_admin_cau_hinh_giai_dau(
    IN p_id INT UNSIGNED, IN p_dang_theo_doi TINYINT,
    IN p_quoc_gia_id INT UNSIGNED, IN p_khu_vuc VARCHAR(60))
BEGIN
    IF NOT EXISTS (SELECT 1 FROM giai_dau WHERE giai_dau_id = p_id) THEN
        SELECT 'KHONG_TON_TAI' AS ma;
    ELSE
        UPDATE giai_dau SET dang_theo_doi = p_dang_theo_doi WHERE giai_dau_id = p_id;
        IF p_quoc_gia_id IS NOT NULL AND p_khu_vuc IS NOT NULL AND p_khu_vuc <> '' THEN
            UPDATE quoc_gia SET khu_vuc = p_khu_vuc WHERE quoc_gia_id = p_quoc_gia_id;
        END IF;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_nhat_ky_dong_bo;
DELIMITER //
CREATE PROCEDURE sp_ds_nhat_ky_dong_bo(IN p_gioi_han INT)
BEGIN
    SELECT nhat_ky_id, loai, bat_dau, ket_thuc, trang_thai, so_ban_ghi, so_bo_qua, thong_bao
      FROM nhat_ky_dong_bo
     ORDER BY bat_dau DESC
     LIMIT p_gioi_han;
END//
DELIMITER ;

-- ============================================================
-- NHÓM SHOULD (thêm khi đủ thời gian)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_upsert_bang_xep_hang;
DELIMITER //
CREATE PROCEDURE sp_upsert_bang_xep_hang(
    IN p_giai_id INT UNSIGNED, IN p_doi_id INT UNSIGNED, IN p_mua_giai VARCHAR(20),
    IN p_hang SMALLINT, IN p_so_tran SMALLINT, IN p_thang SMALLINT, IN p_hoa SMALLINT,
    IN p_thua SMALLINT, IN p_ban_thang SMALLINT, IN p_ban_thua SMALLINT, IN p_diem SMALLINT)
BEGIN
    INSERT INTO bang_xep_hang
        (giai_dau_id, doi_bong_id, mua_giai, hang, so_tran, thang, hoa, thua, ban_thang, ban_thua, diem)
    VALUES (p_giai_id, p_doi_id, p_mua_giai, p_hang, p_so_tran, p_thang, p_hoa,
            p_thua, p_ban_thang, p_ban_thua, p_diem)
    ON DUPLICATE KEY UPDATE hang=VALUES(hang), so_tran=VALUES(so_tran), thang=VALUES(thang),
        hoa=VALUES(hoa), thua=VALUES(thua), ban_thang=VALUES(ban_thang),
        ban_thua=VALUES(ban_thua), diem=VALUES(diem);
    SELECT 'OK' AS ma;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_bang_xep_hang;
DELIMITER //
CREATE PROCEDURE sp_ds_bang_xep_hang(IN p_giai_id INT UNSIGNED)
BEGIN
    SELECT bxh.hang, d.doi_bong_id, d.ten AS doi_ten, bxh.so_tran, bxh.thang, bxh.hoa,
           bxh.thua, bxh.ban_thang, bxh.ban_thua, bxh.diem
      FROM bang_xep_hang bxh
      JOIN doi_bong d ON d.doi_bong_id = bxh.doi_bong_id
     WHERE bxh.giai_dau_id = p_giai_id
     ORDER BY bxh.diem DESC, (bxh.ban_thang - bxh.ban_thua) DESC, bxh.ban_thang DESC;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_su_kien_tran;
DELIMITER //
CREATE PROCEDURE sp_ds_su_kien_tran(IN p_tran_id INT UNSIGNED)
BEGIN
    SELECT sk.su_kien_id, sk.phut, sk.loai, sk.cau_thu, d.ten AS doi_ten
      FROM su_kien_tran sk
      LEFT JOIN doi_bong d ON d.doi_bong_id = sk.doi_bong_id
     WHERE sk.tran_dau_id = p_tran_id
     ORDER BY sk.phut ASC;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_upsert_tin_tuc;
DELIMITER //
CREATE PROCEDURE sp_upsert_tin_tuc(
    IN p_nguon VARCHAR(20), IN p_ma_ngoai VARCHAR(120), IN p_tieu_de VARCHAR(255),
    IN p_tom_tat VARCHAR(600), IN p_anh_url VARCHAR(500), IN p_duong_dan VARCHAR(500),
    IN p_ngay_dang DATETIME, IN p_ten_danh_muc VARCHAR(120), IN p_thu_tu SMALLINT UNSIGNED)
BEGIN
    DECLARE v_dm_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_id INT UNSIGNED DEFAULT NULL;
    IF p_ma_ngoai IS NULL OR p_ma_ngoai = '' THEN
        SELECT 'THIEU_MA_NGOAI' AS ma, NULL AS id;
    ELSE
        IF p_ten_danh_muc IS NOT NULL AND p_ten_danh_muc <> '' THEN
            INSERT INTO danh_muc_tin (ten) VALUES (p_ten_danh_muc)
            ON DUPLICATE KEY UPDATE ten = VALUES(ten);
            SELECT danh_muc_id INTO v_dm_id FROM danh_muc_tin WHERE ten = p_ten_danh_muc LIMIT 1;
        END IF;
        INSERT INTO tin_tuc
            (danh_muc_id, nguon, ma_ngoai, tieu_de, tom_tat, anh_url, duong_dan, noi_dung, ngay_dang, thu_tu)
        VALUES
            (v_dm_id, p_nguon, p_ma_ngoai, p_tieu_de, p_tom_tat, p_anh_url, p_duong_dan, p_tom_tat, p_ngay_dang, p_thu_tu)
        ON DUPLICATE KEY UPDATE
            danh_muc_id = VALUES(danh_muc_id), tieu_de = VALUES(tieu_de), tom_tat = VALUES(tom_tat),
            anh_url = VALUES(anh_url), duong_dan = VALUES(duong_dan),
            ngay_dang = VALUES(ngay_dang), thu_tu = VALUES(thu_tu);
        SELECT tin_id INTO v_id FROM tin_tuc WHERE nguon = p_nguon AND ma_ngoai = p_ma_ngoai LIMIT 1;
        SELECT 'OK' AS ma, v_id AS id;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_tin_tuc;
DELIMITER //
CREATE PROCEDURE sp_ds_tin_tuc(IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT tt.tin_id, tt.tieu_de, tt.tom_tat, tt.anh_url, tt.duong_dan, tt.nguon,
           tt.ngay_dang, tt.ngay_tao, tt.thu_tu, dm.ten AS danh_muc_ten,
           COUNT(*) OVER () AS tong
      FROM tin_tuc tt
      LEFT JOIN danh_muc_tin dm ON dm.danh_muc_id = tt.danh_muc_id
     ORDER BY tt.thu_tu ASC, tt.ngay_tao DESC, tt.tin_id DESC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_chi_tiet_tin_tuc;
DELIMITER //
CREATE PROCEDURE sp_chi_tiet_tin_tuc(IN p_id INT UNSIGNED)
BEGIN
    SELECT tt.tin_id, tt.tieu_de, tt.tom_tat, tt.anh_url, tt.duong_dan, tt.nguon,
           tt.noi_dung, tt.ngay_dang, tt.ngay_tao, dm.ten AS danh_muc_ten
      FROM tin_tuc tt
      LEFT JOIN danh_muc_tin dm ON dm.danh_muc_id = tt.danh_muc_id
     WHERE tt.tin_id = p_id;
END//
DELIMITER ;
