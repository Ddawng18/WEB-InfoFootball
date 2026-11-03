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

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

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
    IN p_ti_so_nha TINYINT, IN p_ti_so_khach TINYINT, IN p_co_live TINYINT)
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
    ELSEIF p_co_live IS NOT NULL AND p_co_live NOT IN (0, 1) THEN
        SELECT 'CO_LIVE_SAI' AS ma, v_id AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF v_trang_thai_cu = 'KET_THUC' THEN
        -- Quy tắc nghiệp vụ: trận đã KET_THUC thì không ghi đè
        SELECT 'OK' AS ma, v_id AS tran_dau_id, 'BO_QUA' AS hanh_dong;
    ELSEIF v_id IS NULL THEN
        INSERT INTO tran_dau (nguon, ma_api, giai_dau_id, doi_nha_id, doi_khach_id,
                              thoi_gian_bat_dau, trang_thai, phut, ti_so_nha, ti_so_khach,
                              co_live, cap_nhat_luc)
        VALUES (p_nguon, p_ma_api, v_giai_id, v_nha_id, v_khach_id,
                p_thoi_gian_utc, p_trang_thai, COALESCE(p_phut,0), p_ti_so_nha, p_ti_so_khach,
                p_co_live, NOW());
        SELECT LAST_INSERT_ID() INTO v_id;
        SELECT 'OK' AS ma, v_id AS tran_dau_id, 'THEM' AS hanh_dong;
    ELSE
        UPDATE tran_dau
           SET giai_dau_id = v_giai_id, doi_nha_id = v_nha_id, doi_khach_id = v_khach_id,
               thoi_gian_bat_dau = p_thoi_gian_utc, trang_thai = p_trang_thai,
               phut = COALESCE(p_phut,0), ti_so_nha = p_ti_so_nha, ti_so_khach = p_ti_so_khach,
               co_live = COALESCE(p_co_live, co_live),
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
    DECLARE v_last_success DATETIME DEFAULT NULL;
    DECLARE v_last_attempt DATETIME DEFAULT NULL;
    DECLARE v_backoff INT DEFAULT 300;

    -- p_ttl < 0: ép chạy lại (dùng cho nút "Đồng bộ ngay" của ADMIN).
    IF p_ttl < 0 THEN
        RETURN 1;
    END IF;

    SELECT MAX(ket_thuc) INTO v_last_success
        FROM nhat_ky_dong_bo
        WHERE loai = p_loai AND trang_thai = 'THANH_CONG';
    SELECT MAX(ket_thuc) INTO v_last_attempt
        FROM nhat_ky_dong_bo
        WHERE loai = p_loai AND trang_thai IN ('LOI', 'RONG');

    -- Nếu lần gần nhất là lỗi/rỗng, chờ tối đa 5 phút trước khi thử lại
    -- để tránh treo trang và đốt request khi API ngoài đang lỗi.
    IF v_last_attempt IS NOT NULL
       AND (v_last_success IS NULL OR v_last_attempt > v_last_success) THEN
        IF p_ttl < v_backoff THEN
            SET v_backoff = GREATEST(0, p_ttl);
        END IF;
        IF NOW() <= DATE_ADD(v_last_attempt, INTERVAL v_backoff SECOND) THEN
            RETURN 0;
        END IF;
    END IF;

    IF v_last_success IS NULL THEN
        RETURN 1;
    END IF;
    IF NOW() > DATE_ADD(v_last_success, INTERVAL p_ttl SECOND) THEN
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
    -- Nguồn báo SAP_DIEN_RA nhưng đã quá giờ bóng lăn: không tự nhận "Đang đá".
    -- Hiển thị CHO_CAP_NHAT cho tới khi ESPN chuyển sang trạng thái live thật.
    SELECT t.tran_dau_id, t.ma_api, t.thoi_gian_bat_dau,
           t.trang_thai_hien_thi AS trang_thai, t.trang_thai AS trang_thai_nguon,
           t.phut_hien_thi AS phut,
           t.ti_so_nha, t.ti_so_khach, t.co_live, t.cap_nhat_luc,
           t.doi_nha_id, t.doi_khach_id, t.giai_dau_id,
           nha.ten AS doi_nha_ten, khach.ten AS doi_khach_ten, gd.ten AS giai_ten,
           nha.logo_url AS doi_nha_logo, khach.logo_url AS doi_khach_logo,
           COUNT(*) OVER () AS tong
      FROM (
          SELECT td.*,
                 CASE
                   WHEN td.trang_thai = 'SAP_DIEN_RA'
                        AND td.thoi_gian_bat_dau <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
                        AND td.thoi_gian_bat_dau >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 HOUR)
                   THEN 'CHO_CAP_NHAT'
                   ELSE td.trang_thai
                 END AS trang_thai_hien_thi,
                 td.phut AS phut_hien_thi
            FROM tran_dau td
      ) t
      JOIN doi_bong nha   ON nha.doi_bong_id = t.doi_nha_id
      JOIN doi_bong khach ON khach.doi_bong_id = t.doi_khach_id
      JOIN giai_dau gd    ON gd.giai_dau_id = t.giai_dau_id
     WHERE (p_tu_utc IS NULL OR t.thoi_gian_bat_dau >= p_tu_utc)
       AND (p_den_utc IS NULL OR t.thoi_gian_bat_dau < p_den_utc)
       AND (p_giai_id IS NULL OR t.giai_dau_id = p_giai_id)
       AND (p_doi_id IS NULL OR t.doi_nha_id = p_doi_id OR t.doi_khach_id = p_doi_id)
       AND (CASE WHEN p_nhom = 'DANG'
                 THEN t.trang_thai_hien_thi IN ('DANG_DA','NGHI_GIUA_HIEP','CHO_CAP_NHAT')
                 ELSE (p_trang_thai IS NULL OR p_trang_thai = '' OR t.trang_thai_hien_thi = p_trang_thai)
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
        SELECT 'OK' AS ma, t.tran_dau_id, t.ma_api, t.thoi_gian_bat_dau,
               t.trang_thai_hien_thi AS trang_thai, t.trang_thai AS trang_thai_nguon,
               t.phut_hien_thi AS phut,
               t.ti_so_nha, t.ti_so_khach, t.co_live, t.cap_nhat_luc,
               t.doi_nha_id, t.doi_khach_id, t.giai_dau_id,
               nha.ten AS doi_nha_ten, khach.ten AS doi_khach_ten,
               nha.logo_url AS doi_nha_logo, khach.logo_url AS doi_khach_logo,
               gd.ten AS giai_ten, qg.ten AS quoc_gia_ten
          FROM (
              SELECT td.*,
                     CASE
                       WHEN td.trang_thai = 'SAP_DIEN_RA'
                            AND td.thoi_gian_bat_dau <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
                            AND td.thoi_gian_bat_dau >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 HOUR)
                       THEN 'CHO_CAP_NHAT'
                       ELSE td.trang_thai
                     END AS trang_thai_hien_thi,
                     td.phut AS phut_hien_thi
                FROM tran_dau td
               WHERE td.tran_dau_id = p_id
          ) t
          JOIN doi_bong nha   ON nha.doi_bong_id = t.doi_nha_id
          JOIN doi_bong khach ON khach.doi_bong_id = t.doi_khach_id
          JOIN giai_dau gd    ON gd.giai_dau_id = t.giai_dau_id
          LEFT JOIN quoc_gia qg ON qg.quoc_gia_id = gd.quoc_gia_id;
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
           gd.quoc_gia_id, qg.ten AS quoc_gia_ten, qg.khu_vuc, COUNT(*) OVER () AS tong
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
     WHERE (p_giai_id IS NULL OR EXISTS (
               SELECT 1 FROM tran_dau t
                WHERE t.giai_dau_id = p_giai_id
                  AND (t.doi_nha_id = d.doi_bong_id OR t.doi_khach_id = d.doi_bong_id)
           ))
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
-- NHÓM 4.1: LỊCH CÁ NHÂN + NHẮC TRẬN (S3)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_dat_lich_ca_nhan;
DELIMITER //
CREATE PROCEDURE sp_dat_lich_ca_nhan(
    IN p_tk_id INT UNSIGNED, IN p_tran_id INT UNSIGNED,
    IN p_trong_lich TINYINT, IN p_nhac TINYINT)
BEGIN
    DECLARE v_ton_tai TINYINT DEFAULT 0;
    SET p_trong_lich = COALESCE(p_trong_lich, 0);
    SET p_nhac = COALESCE(p_nhac, 0);
    SELECT EXISTS(
        SELECT 1 FROM lich_ca_nhan
         WHERE tai_khoan_id = p_tk_id AND tran_dau_id = p_tran_id
    ) INTO v_ton_tai;

    IF NOT EXISTS (SELECT 1 FROM tran_dau WHERE tran_dau_id = p_tran_id) THEN
        SELECT 'KHONG_TON_TAI' AS ma, 0 AS trong_lich, 0 AS nhac;
    ELSEIF p_trong_lich NOT IN (0,1) OR p_nhac NOT IN (0,1) THEN
        SELECT 'DU_LIEU_SAI' AS ma, 0 AS trong_lich, 0 AS nhac;
    ELSEIF p_trong_lich = 0 AND p_nhac = 0 THEN
        DELETE FROM lich_ca_nhan
         WHERE tai_khoan_id = p_tk_id AND tran_dau_id = p_tran_id;
        SELECT 'OK' AS ma, 0 AS trong_lich, 0 AS nhac;
    ELSEIF v_ton_tai = 0 AND NOT EXISTS (
        SELECT 1 FROM tran_dau
         WHERE tran_dau_id = p_tran_id
           AND trang_thai = 'SAP_DIEN_RA'
           AND thoi_gian_bat_dau > UTC_TIMESTAMP()
    ) THEN
        SELECT 'CHUA_DIEN_RA' AS ma, 0 AS trong_lich, 0 AS nhac;
    ELSE
        INSERT INTO lich_ca_nhan (tai_khoan_id, tran_dau_id, trong_lich, nhac, da_nhac)
        VALUES (p_tk_id, p_tran_id, p_trong_lich, p_nhac, 0)
        ON DUPLICATE KEY UPDATE
            da_nhac = IF(p_nhac = 1 AND nhac = 0, 0, da_nhac),
            trong_lich = p_trong_lich,
            nhac = p_nhac;
        SELECT 'OK' AS ma, trong_lich, nhac
          FROM lich_ca_nhan
         WHERE tai_khoan_id = p_tk_id AND tran_dau_id = p_tran_id;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_kiem_tra_lich_ca_nhan;
DELIMITER //
CREATE PROCEDURE sp_kiem_tra_lich_ca_nhan(
    IN p_tk_id INT UNSIGNED, IN p_tran_id INT UNSIGNED)
BEGIN
    SELECT
        COALESCE((SELECT trong_lich FROM lich_ca_nhan
                   WHERE tai_khoan_id = p_tk_id AND tran_dau_id = p_tran_id), 0) AS trong_lich,
        COALESCE((SELECT nhac FROM lich_ca_nhan
                   WHERE tai_khoan_id = p_tk_id AND tran_dau_id = p_tran_id), 0) AS nhac,
        COALESCE((SELECT da_nhac FROM lich_ca_nhan
                   WHERE tai_khoan_id = p_tk_id AND tran_dau_id = p_tran_id), 0) AS da_nhac;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_lich_ca_nhan;
DELIMITER //
CREATE PROCEDURE sp_ds_lich_ca_nhan(
    IN p_tk_id INT UNSIGNED, IN p_loc VARCHAR(20),
    IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT t.tran_dau_id, t.ma_api, t.thoi_gian_bat_dau,
           CASE
             WHEN t.trang_thai = 'SAP_DIEN_RA'
                  AND t.thoi_gian_bat_dau <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
                  AND t.thoi_gian_bat_dau >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 HOUR)
             THEN 'CHO_CAP_NHAT'
             ELSE t.trang_thai
           END AS trang_thai,
           t.phut, t.ti_so_nha, t.ti_so_khach, t.cap_nhat_luc,
           nha.doi_bong_id AS doi_nha_id, khach.doi_bong_id AS doi_khach_id,
           nha.ten AS doi_nha_ten, khach.ten AS doi_khach_ten,
           nha.logo_url AS doi_nha_logo, khach.logo_url AS doi_khach_logo,
           gd.ten AS giai_ten,
           l.trong_lich, l.nhac, l.da_nhac, l.ngay_them,
           COUNT(*) OVER () AS tong
      FROM lich_ca_nhan l
      JOIN tran_dau t     ON t.tran_dau_id = l.tran_dau_id
      JOIN doi_bong nha   ON nha.doi_bong_id = t.doi_nha_id
      JOIN doi_bong khach ON khach.doi_bong_id = t.doi_khach_id
      JOIN giai_dau gd    ON gd.giai_dau_id = t.giai_dau_id
     WHERE l.tai_khoan_id = p_tk_id
       AND (l.trong_lich = 1 OR l.nhac = 1)
       AND (p_loc IS NULL OR p_loc = '' OR p_loc = 'all'
            OR (p_loc = 'calendar' AND l.trong_lich = 1)
            OR (p_loc = 'reminder' AND l.nhac = 1)
            OR (p_loc = 'upcoming' AND t.thoi_gian_bat_dau >= UTC_TIMESTAMP())
            OR (p_loc = 'past' AND t.thoi_gian_bat_dau < UTC_TIMESTAMP()))
     ORDER BY CASE WHEN t.thoi_gian_bat_dau >= UTC_TIMESTAMP() THEN 0 ELSE 1 END,
              CASE WHEN t.thoi_gian_bat_dau >= UTC_TIMESTAMP() THEN t.thoi_gian_bat_dau END ASC,
              CASE WHEN t.thoi_gian_bat_dau < UTC_TIMESTAMP() THEN t.thoi_gian_bat_dau END DESC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_lich_theo_nhieu_tran;
DELIMITER //
CREATE PROCEDURE sp_ds_lich_theo_nhieu_tran(
    IN p_tk_id INT UNSIGNED, IN p_ids TEXT)
BEGIN
    SELECT tran_dau_id, trong_lich, nhac
      FROM lich_ca_nhan
     WHERE tai_khoan_id = p_tk_id
       AND p_ids IS NOT NULL AND p_ids <> ''
       AND FIND_IN_SET(tran_dau_id, p_ids) > 0;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_doi_da_them;
DELIMITER //
CREATE PROCEDURE sp_ds_doi_da_them(IN p_tk_id INT UNSIGNED, IN p_gioi_han INT)
BEGIN
    SELECT d.doi_bong_id, d.ten, d.logo_url,
           COUNT(DISTINCT t.tran_dau_id) AS so_tran,
           MIN(CASE WHEN t.thoi_gian_bat_dau >= UTC_TIMESTAMP()
                    THEN t.thoi_gian_bat_dau END) AS tran_sap_toi
      FROM lich_ca_nhan l
      JOIN tran_dau t ON t.tran_dau_id = l.tran_dau_id
      JOIN doi_bong d ON d.doi_bong_id IN (t.doi_nha_id, t.doi_khach_id)
     WHERE l.tai_khoan_id = p_tk_id
       AND (l.trong_lich = 1 OR l.nhac = 1)
     GROUP BY d.doi_bong_id, d.ten, d.logo_url
     ORDER BY so_tran DESC, d.ten ASC
     LIMIT p_gioi_han;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_tao_thong_bao_nhac;
DELIMITER //
CREATE PROCEDURE sp_tao_thong_bao_nhac(IN p_now DATETIME)
BEGIN
    INSERT INTO thong_bao (tai_khoan_id, tran_dau_id, loai, tieu_de, noi_dung)
    SELECT l.tai_khoan_id, t.tran_dau_id, 'NHAC_TRAN',
           'Đã đến giờ bóng lăn',
           CONCAT(nha.ten, ' vs ', khach.ten, ' · ', gd.ten)
      FROM lich_ca_nhan l
      JOIN tran_dau t     ON t.tran_dau_id = l.tran_dau_id
      JOIN doi_bong nha   ON nha.doi_bong_id = t.doi_nha_id
      JOIN doi_bong khach ON khach.doi_bong_id = t.doi_khach_id
      JOIN giai_dau gd    ON gd.giai_dau_id = t.giai_dau_id
     WHERE l.nhac = 1 AND l.da_nhac = 0
       AND t.thoi_gian_bat_dau <= p_now
       AND t.thoi_gian_bat_dau >= DATE_SUB(p_now, INTERVAL 6 HOUR)
       AND t.trang_thai IN ('SAP_DIEN_RA','DANG_DA','NGHI_GIUA_HIEP')
    ON DUPLICATE KEY UPDATE thong_bao_id = thong_bao_id;

    UPDATE lich_ca_nhan l
      JOIN tran_dau t ON t.tran_dau_id = l.tran_dau_id
       SET l.da_nhac = 1
     WHERE l.nhac = 1 AND l.da_nhac = 0
       AND t.thoi_gian_bat_dau <= p_now
       AND t.thoi_gian_bat_dau >= DATE_SUB(p_now, INTERVAL 6 HOUR)
       AND t.trang_thai IN ('SAP_DIEN_RA','DANG_DA','NGHI_GIUA_HIEP');
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_thong_bao;
DELIMITER //
CREATE PROCEDURE sp_ds_thong_bao(
    IN p_tk_id INT UNSIGNED, IN p_gioi_han INT, IN p_bo_qua INT)
BEGIN
    SELECT tb.thong_bao_id, tb.tran_dau_id, tb.loai, tb.tieu_de, tb.noi_dung,
           tb.da_doc, tb.ngay_tao, COUNT(*) OVER () AS tong
      FROM thong_bao tb
     WHERE tb.tai_khoan_id = p_tk_id
     ORDER BY tb.da_doc ASC, tb.ngay_tao DESC
     LIMIT p_gioi_han OFFSET p_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_dem_thong_bao_chua_doc;
DELIMITER //
CREATE PROCEDURE sp_dem_thong_bao_chua_doc(IN p_tk_id INT UNSIGNED)
BEGIN
    SELECT COUNT(*) AS chua_doc
      FROM thong_bao
     WHERE tai_khoan_id = p_tk_id AND da_doc = 0;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_danh_dau_da_doc_thong_bao;
DELIMITER //
CREATE PROCEDURE sp_danh_dau_da_doc_thong_bao(
    IN p_tk_id INT UNSIGNED, IN p_tb_id BIGINT UNSIGNED)
BEGIN
    UPDATE thong_bao
       SET da_doc = 1
     WHERE tai_khoan_id = p_tk_id
       AND (p_tb_id IS NULL OR thong_bao_id = p_tb_id);
    SELECT ROW_COUNT() AS so_dong;
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
        -- Khu vực gắn với quốc gia. Cho phép xoá về "Chưa phân loại".
        IF p_quoc_gia_id IS NOT NULL THEN
            UPDATE quoc_gia
               SET khu_vuc = COALESCE(NULLIF(TRIM(p_khu_vuc), ''), 'Chưa phân loại')
             WHERE quoc_gia_id = p_quoc_gia_id;
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
    DECLARE v_finished INT DEFAULT 0;
    SELECT COUNT(*) INTO v_finished
      FROM tran_dau
     WHERE giai_dau_id = p_giai_id AND trang_thai = 'KET_THUC';

    IF v_finished > 0 THEN
        -- Ưu tiên tính từ kết quả thật để bảng không bị lệch với tỉ số đang hiển thị.
        SELECT ROW_NUMBER() OVER (
                   ORDER BY diem DESC, hieu_so DESC, ban_thang DESC
               ) AS hang,
               doi_bong_id, doi_ten, so_tran, thang, hoa, thua, ban_thang, ban_thua, diem
          FROM (
            SELECT d.doi_bong_id, d.ten AS doi_ten, COUNT(f.tran_dau_id) AS so_tran,
                   COALESCE(SUM(CASE WHEN (f.doi_nha_id = d.doi_bong_id AND f.ti_so_nha > f.ti_so_khach)
                                      OR (f.doi_khach_id = d.doi_bong_id AND f.ti_so_khach > f.ti_so_nha)
                                    THEN 1 ELSE 0 END), 0) AS thang,
                   COALESCE(SUM(CASE WHEN f.ti_so_nha = f.ti_so_khach THEN 1 ELSE 0 END), 0) AS hoa,
                   COALESCE(SUM(CASE WHEN (f.doi_nha_id = d.doi_bong_id AND f.ti_so_nha < f.ti_so_khach)
                                      OR (f.doi_khach_id = d.doi_bong_id AND f.ti_so_khach < f.ti_so_nha)
                                    THEN 1 ELSE 0 END), 0) AS thua,
                   COALESCE(SUM(CASE WHEN f.doi_nha_id = d.doi_bong_id THEN f.ti_so_nha ELSE f.ti_so_khach END), 0) AS ban_thang,
                   COALESCE(SUM(CASE WHEN f.doi_nha_id = d.doi_bong_id THEN f.ti_so_khach ELSE f.ti_so_nha END), 0) AS ban_thua,
                   COALESCE(SUM(CASE WHEN (f.doi_nha_id = d.doi_bong_id AND f.ti_so_nha > f.ti_so_khach)
                                      OR (f.doi_khach_id = d.doi_bong_id AND f.ti_so_khach > f.ti_so_nha)
                                    THEN 3
                                    WHEN f.ti_so_nha = f.ti_so_khach THEN 1
                                    ELSE 0 END), 0) AS diem,
                   COALESCE(SUM(CASE WHEN f.doi_nha_id = d.doi_bong_id THEN f.ti_so_nha ELSE f.ti_so_khach END), 0)
                   - COALESCE(SUM(CASE WHEN f.doi_nha_id = d.doi_bong_id THEN f.ti_so_khach ELSE f.ti_so_nha END), 0) AS hieu_so
              FROM (
                    SELECT doi_nha_id AS doi_id FROM tran_dau WHERE giai_dau_id = p_giai_id
                    UNION
                    SELECT doi_khach_id FROM tran_dau WHERE giai_dau_id = p_giai_id
              ) x
              JOIN doi_bong d ON d.doi_bong_id = x.doi_id
              LEFT JOIN tran_dau f
                     ON f.giai_dau_id = p_giai_id
                    AND f.trang_thai = 'KET_THUC'
                    AND (f.doi_nha_id = d.doi_bong_id OR f.doi_khach_id = d.doi_bong_id)
             GROUP BY d.doi_bong_id, d.ten
          ) x
         ORDER BY diem DESC, hieu_so DESC, ban_thang DESC;
    ELSE
        SELECT bxh.hang, d.doi_bong_id, d.ten AS doi_ten, bxh.so_tran, bxh.thang, bxh.hoa,
               bxh.thua, bxh.ban_thang, bxh.ban_thua, bxh.diem
          FROM bang_xep_hang bxh
          JOIN doi_bong d ON d.doi_bong_id = bxh.doi_bong_id
         WHERE bxh.giai_dau_id = p_giai_id
         ORDER BY bxh.diem DESC, (bxh.ban_thang - bxh.ban_thua) DESC, bxh.ban_thang DESC;
    END IF;
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
    IN p_noi_dung MEDIUMTEXT,
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
            (v_dm_id, p_nguon, p_ma_ngoai, p_tieu_de, p_tom_tat, p_anh_url, p_duong_dan,
             COALESCE(NULLIF(p_noi_dung, ''), p_tom_tat), p_ngay_dang, p_thu_tu)
        ON DUPLICATE KEY UPDATE
            danh_muc_id = VALUES(danh_muc_id), tieu_de = VALUES(tieu_de), tom_tat = VALUES(tom_tat),
            anh_url = VALUES(anh_url), duong_dan = VALUES(duong_dan),
            noi_dung = VALUES(noi_dung),
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
    SELECT tt.tin_id, tt.nguon, tt.ma_ngoai, tt.tieu_de, tt.tom_tat, tt.anh_url, tt.duong_dan,
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
    SELECT tt.tin_id, tt.nguon, tt.ma_ngoai, tt.tieu_de, tt.tom_tat, tt.anh_url, tt.duong_dan,
           tt.noi_dung, tt.ngay_dang, tt.ngay_tao, dm.ten AS danh_muc_ten
      FROM tin_tuc tt
      LEFT JOIN danh_muc_tin dm ON dm.danh_muc_id = tt.danh_muc_id
     WHERE tt.tin_id = p_id;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_xoa_tin_tuc_admin;
DELIMITER //
CREATE PROCEDURE sp_xoa_tin_tuc_admin(IN p_tin_id INT UNSIGNED)
BEGIN
    DECLARE v_nguon VARCHAR(20) DEFAULT NULL;
    SELECT nguon INTO v_nguon FROM tin_tuc WHERE tin_id = p_tin_id LIMIT 1;
    IF v_nguon IS NULL THEN
        SELECT 'KHONG_TIM_THAY' AS ma;
    ELSEIF v_nguon <> 'ADMIN' THEN
        SELECT 'KHONG_PHAI_ADMIN' AS ma;
    ELSE
        DELETE FROM tin_tuc WHERE tin_id = p_tin_id;
        SELECT 'OK' AS ma;
    END IF;
END//
DELIMITER ;

-- ============================================================
-- NHÓM SẢN PHẨM BÓNG ĐÁ (thay thế mục Lưu niệm cũ)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_san_pham_bong_da_upsert;
DELIMITER //
CREATE PROCEDURE sp_san_pham_bong_da_upsert(
    IN p_nguon VARCHAR(30),
    IN p_ma_ngoai VARCHAR(100),
    IN p_ten VARCHAR(255),
    IN p_mo_ta TEXT,
    IN p_anh_url VARCHAR(2048),
    IN p_anh_phu TEXT,
    IN p_gia_goc DECIMAL(12,0),
    IN p_gia_hien_tai DECIMAL(12,0),
    IN p_danh_muc_slug VARCHAR(120),
    IN p_thuong_hieu VARCHAR(100),
    IN p_cua_hang VARCHAR(150),
    IN p_ten_mien VARCHAR(100),
    IN p_lien_ket_mua VARCHAR(2048),
    IN p_lien_ket_goc VARCHAR(2048),
    IN p_con_hang TINYINT,
    IN p_ngay_nguon DATETIME
)
BEGIN
    DECLARE v_nguon VARCHAR(30);
    DECLARE v_ma_ngoai VARCHAR(100);
    DECLARE v_ten VARCHAR(255);
    DECLARE v_danh_muc_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_gia_goc DECIMAL(12,0);
    DECLARE v_giam DECIMAL(12,0);
    DECLARE v_phan_tram TINYINT UNSIGNED;
    DECLARE v_ma INT UNSIGNED DEFAULT NULL;
    DECLARE v_gia_cu DECIMAL(12,0) DEFAULT NULL;
    DECLARE v_doi_gia TINYINT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 'LOI_HE_THONG' AS ma, NULL AS san_pham_id, 'LOI' AS hanh_dong;
    END;

    SET v_nguon = UPPER(TRIM(COALESCE(p_nguon, '')));
    SET v_ma_ngoai = TRIM(COALESCE(p_ma_ngoai, ''));
    SET v_ten = TRIM(COALESCE(p_ten, ''));

    IF v_nguon = '' OR CHAR_LENGTH(v_nguon) > 30
       OR v_ma_ngoai = '' OR CHAR_LENGTH(v_ma_ngoai) > 100
       OR v_ten = '' OR CHAR_LENGTH(v_ten) > 255
       OR p_gia_goc IS NULL OR p_gia_goc < 0 OR p_gia_goc > 1000000000
       OR p_gia_hien_tai IS NULL OR p_gia_hien_tai < 0 OR p_gia_hien_tai > 1000000000
       OR p_anh_url IS NULL OR p_anh_url = '' OR LEFT(p_anh_url, 8) <> 'https://'
       OR p_lien_ket_mua IS NULL OR LEFT(p_lien_ket_mua, 8) <> 'https://'
       OR (p_lien_ket_goc IS NOT NULL AND p_lien_ket_goc <> '' AND LEFT(p_lien_ket_goc, 8) <> 'https://')
       OR p_ten_mien IS NULL OR TRIM(p_ten_mien) = '' OR CHAR_LENGTH(p_ten_mien) > 100 THEN
        SELECT 'BO_QUA' AS ma, NULL AS san_pham_id, 'BO_QUA' AS hanh_dong;
    ELSE
        START TRANSACTION;
        SELECT ma INTO v_danh_muc_id
          FROM danh_muc_san_pham
         WHERE slug = COALESCE(NULLIF(TRIM(p_danh_muc_slug), ''), 'khac')
         LIMIT 1;
        IF v_danh_muc_id IS NULL THEN
            SELECT ma INTO v_danh_muc_id
              FROM danh_muc_san_pham
             WHERE slug = 'khac'
             LIMIT 1;
        END IF;
        IF v_danh_muc_id IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Chưa có danh mục sản phẩm bóng đá';
        END IF;

        SET v_gia_goc = GREATEST(COALESCE(p_gia_goc, 0), p_gia_hien_tai);
        SET v_giam = v_gia_goc - p_gia_hien_tai;
        SET v_phan_tram = IF(v_gia_goc > 0, LEAST(99, GREATEST(0, ROUND(v_giam * 100 / v_gia_goc))), 0);

        SELECT ma, gia_hien_tai INTO v_ma, v_gia_cu
          FROM san_pham_bong_da
         WHERE nguon = v_nguon AND ma_ngoai = v_ma_ngoai
         LIMIT 1;

        IF v_ma IS NULL THEN
            INSERT INTO san_pham_bong_da (
                nguon, ma_ngoai, ten, mo_ta, anh_url, anh_phu, gia_goc, gia_hien_tai,
                phan_tram_giam, so_tien_giam, danh_muc_id, thuong_hieu, cua_hang, ten_mien,
                lien_ket_mua, lien_ket_goc, con_hang, ngay_nguon, lan_kiem_tra, ngay_dong_bo, hien_thi
            ) VALUES (
                v_nguon, v_ma_ngoai, v_ten, COALESCE(p_mo_ta, ''), p_anh_url, COALESCE(p_anh_phu, ''),
                v_gia_goc, p_gia_hien_tai, v_phan_tram, v_giam, v_danh_muc_id,
                TRIM(COALESCE(p_thuong_hieu, '')), TRIM(COALESCE(p_cua_hang, '')), LOWER(TRIM(p_ten_mien)),
                p_lien_ket_mua, NULLIF(p_lien_ket_goc, ''), IF(p_con_hang IS NULL OR p_con_hang = 0, 0, 1),
                p_ngay_nguon, NOW(), NOW(), 1
            );
            SET v_ma = LAST_INSERT_ID();
            COMMIT;
            SELECT 'OK' AS ma, v_ma AS san_pham_id, 'THEM_MOI' AS hanh_dong;
        ELSE
            SET v_doi_gia = IF(v_gia_cu <> p_gia_hien_tai, 1, 0);
            UPDATE san_pham_bong_da
               SET ten = v_ten,
                   mo_ta = IF(COALESCE(p_mo_ta, '') = '', mo_ta, p_mo_ta),
                   anh_url = p_anh_url,
                   anh_phu = IF(COALESCE(p_anh_phu, '') = '', anh_phu, p_anh_phu),
                   gia_goc = v_gia_goc,
                   gia_hien_tai = p_gia_hien_tai,
                   phan_tram_giam = v_phan_tram,
                   so_tien_giam = v_giam,
                   danh_muc_id = v_danh_muc_id,
                   thuong_hieu = TRIM(COALESCE(p_thuong_hieu, '')),
                   cua_hang = TRIM(COALESCE(p_cua_hang, '')),
                   ten_mien = LOWER(TRIM(p_ten_mien)),
                   lien_ket_mua = p_lien_ket_mua,
                   lien_ket_goc = NULLIF(p_lien_ket_goc, ''),
                   con_hang = IF(p_con_hang IS NULL OR p_con_hang = 0, 0, 1),
                   ngay_nguon = p_ngay_nguon,
                   lan_kiem_tra = NOW(),
                   ngay_dong_bo = NOW(),
                   hien_thi = 1
             WHERE ma = v_ma;
            IF v_doi_gia = 1 THEN
                INSERT INTO lich_su_gia_san_pham_bong_da (ma_san_pham, gia_cu, gia_moi, thoi_diem)
                VALUES (v_ma, v_gia_cu, p_gia_hien_tai, NOW());
            END IF;
            COMMIT;
            SELECT 'OK' AS ma, v_ma AS san_pham_id,
                   IF(v_doi_gia = 1, 'DOI_GIA', 'CAP_NHAT') AS hanh_dong;
        END IF;
    END IF;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_san_pham_bong_da_an_cu;
DELIMITER //
CREATE PROCEDURE sp_san_pham_bong_da_an_cu(IN p_nguon VARCHAR(30), IN p_moc DATETIME)
BEGIN
    UPDATE san_pham_bong_da
       SET hien_thi = 0
     WHERE nguon = UPPER(TRIM(COALESCE(p_nguon, '')))
       AND ngay_dong_bo < p_moc
       AND hien_thi = 1;
    SELECT ROW_COUNT() AS so_dong_an;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_san_pham_bong_da;
DELIMITER //
CREATE PROCEDURE sp_ds_san_pham_bong_da(
    IN p_danh_muc_slug VARCHAR(120),
    IN p_tu_khoa VARCHAR(100),
    IN p_ten_mien VARCHAR(100),
    IN p_sap_xep VARCHAR(20),
    IN p_trang INT,
    IN p_so_dong INT
)
BEGIN
    DECLARE v_bo_qua BIGINT UNSIGNED DEFAULT 0;
    DECLARE v_so_dong INT UNSIGNED DEFAULT 0;
    IF p_trang IS NULL OR p_trang < 1 OR p_so_dong IS NULL OR p_so_dong < 1 OR p_so_dong > 48 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tham số phân trang không hợp lệ';
    END IF;
    SET v_so_dong = p_so_dong;
    SET v_bo_qua = (p_trang - 1) * p_so_dong;

    SELECT sp.ma, sp.nguon, sp.ten, sp.mo_ta, sp.anh_url, sp.anh_phu,
           sp.gia_goc, sp.gia_hien_tai, sp.phan_tram_giam, sp.so_tien_giam,
           sp.danh_muc_id, dm.ten AS ten_danh_muc, dm.slug AS slug_danh_muc,
           sp.thuong_hieu, sp.cua_hang, sp.ten_mien, sp.lien_ket_mua, sp.lien_ket_goc,
           sp.con_hang, sp.ngay_nguon, sp.lan_kiem_tra, sp.ngay_dong_bo,
           COUNT(*) OVER () AS tong
      FROM san_pham_bong_da sp
     INNER JOIN danh_muc_san_pham dm ON dm.ma = sp.danh_muc_id
     WHERE sp.hien_thi = 1
       AND (p_danh_muc_slug IS NULL OR p_danh_muc_slug = '' OR dm.slug = p_danh_muc_slug)
       AND (p_ten_mien IS NULL OR p_ten_mien = '' OR sp.ten_mien = p_ten_mien)
       AND (p_tu_khoa IS NULL OR p_tu_khoa = ''
            OR LOCATE(LOWER(p_tu_khoa), LOWER(sp.ten)) > 0
            OR LOCATE(LOWER(p_tu_khoa), LOWER(sp.mo_ta)) > 0
            OR LOCATE(LOWER(p_tu_khoa), LOWER(sp.cua_hang)) > 0)
     ORDER BY
        CASE WHEN p_sap_xep = 'moi_nhat' AND sp.mo_ta <> '' THEN 0
             WHEN p_sap_xep = 'moi_nhat' THEN 1
             ELSE 0 END ASC,
        CASE WHEN p_sap_xep = 'gia_tang' THEN sp.gia_hien_tai END ASC,
        CASE WHEN p_sap_xep = 'gia_giam' THEN sp.gia_hien_tai END DESC,
        CASE WHEN p_sap_xep = 'giam_gia' THEN sp.phan_tram_giam END DESC,
        sp.ngay_dong_bo DESC,
        sp.ma DESC
     LIMIT v_so_dong OFFSET v_bo_qua;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_danh_muc_san_pham_bong_da;
DELIMITER //
CREATE PROCEDURE sp_ds_danh_muc_san_pham_bong_da()
BEGIN
    SELECT dm.ma, dm.ten, dm.slug, dm.thu_tu, COUNT(sp.ma) AS so_san_pham
      FROM danh_muc_san_pham dm
      LEFT JOIN san_pham_bong_da sp ON sp.danh_muc_id = dm.ma AND sp.hien_thi = 1
     GROUP BY dm.ma, dm.ten, dm.slug, dm.thu_tu
     ORDER BY dm.thu_tu ASC, dm.ma ASC;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_ten_mien_san_pham_bong_da;
DELIMITER //
CREATE PROCEDURE sp_ds_ten_mien_san_pham_bong_da()
BEGIN
    SELECT sp.ten_mien, COUNT(*) AS so_san_pham
      FROM san_pham_bong_da sp
     WHERE sp.hien_thi = 1
     GROUP BY sp.ten_mien
     ORDER BY sp.ten_mien ASC;
END//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ds_gia_san_pham_bong_da;
DELIMITER //
CREATE PROCEDURE sp_ds_gia_san_pham_bong_da(
    IN p_danh_muc_slug VARCHAR(120),
    IN p_tu_khoa VARCHAR(100),
    IN p_ten_mien VARCHAR(100)
)
BEGIN
    SELECT sp.ma, sp.gia_goc, sp.gia_hien_tai, sp.phan_tram_giam, sp.so_tien_giam,
           sp.con_hang, sp.lan_kiem_tra
      FROM san_pham_bong_da sp
     INNER JOIN danh_muc_san_pham dm ON dm.ma = sp.danh_muc_id
     WHERE sp.hien_thi = 1
       AND (p_danh_muc_slug IS NULL OR p_danh_muc_slug = '' OR dm.slug = p_danh_muc_slug)
       AND (p_ten_mien IS NULL OR p_ten_mien = '' OR sp.ten_mien = p_ten_mien)
       AND (p_tu_khoa IS NULL OR p_tu_khoa = ''
            OR LOCATE(LOWER(p_tu_khoa), LOWER(sp.ten)) > 0
            OR LOCATE(LOWER(p_tu_khoa), LOWER(sp.mo_ta)) > 0
            OR LOCATE(LOWER(p_tu_khoa), LOWER(sp.cua_hang)) > 0)
     ORDER BY sp.ma ASC
     LIMIT 200;
END//
DELIMITER ;
