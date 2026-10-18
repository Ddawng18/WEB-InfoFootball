-- ============================================================
-- Migration 001: mở rộng bảng tin_tuc cho tin cào tự động + thêm routine upsert.
-- Dùng cho CSDL đã tạo trước đó (không mất dữ liệu).
-- Chạy: mysql -u root fastfootbalinfo < database/migrations/001_news_crawl.sql
-- Sau đó chạy lại database/routines.sql để cập nhật routine đọc tin.
-- ============================================================

USE fastfootbalinfo;

ALTER TABLE tin_tuc
    ADD COLUMN nguon     VARCHAR(20)  NOT NULL DEFAULT 'ADMIN' AFTER danh_muc_id,
    ADD COLUMN ma_ngoai  VARCHAR(120) NULL AFTER nguon,
    ADD COLUMN tom_tat   VARCHAR(600) NOT NULL DEFAULT '' AFTER tieu_de,
    ADD COLUMN anh_url   VARCHAR(500) NOT NULL DEFAULT '' AFTER tom_tat,
    ADD COLUMN duong_dan VARCHAR(500) NOT NULL DEFAULT '' AFTER anh_url,
    ADD COLUMN ngay_dang DATETIME NULL AFTER noi_dung,
    ADD COLUMN thu_tu    SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER ngay_dang,
    ADD UNIQUE KEY uq_tt_nguon_ngoai (nguon, ma_ngoai);

DELIMITER //
DROP PROCEDURE IF EXISTS sp_upsert_tin_tuc//
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
