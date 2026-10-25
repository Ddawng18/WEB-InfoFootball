-- ============================================================
-- 007_product_shop.sql
-- Bổ sung tên shop/cửa hàng cụ thể cho sản phẩm bóng đá.
-- Chạy trên CSDL đã có bảng san_pham_bong_da:
--   mysql -u root --default-character-set=utf8mb4 fastfootbalinfo < database\migrations\007_product_shop.sql
-- ============================================================

USE fastfootbalinfo;

DROP PROCEDURE IF EXISTS tmp_add_cua_hang;
DELIMITER //
CREATE PROCEDURE tmp_add_cua_hang()
BEGIN
    IF NOT EXISTS (
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'san_pham_bong_da'
           AND COLUMN_NAME = 'cua_hang'
    ) THEN
        ALTER TABLE san_pham_bong_da
            ADD COLUMN cua_hang VARCHAR(150) NOT NULL DEFAULT '' AFTER thuong_hieu;
    END IF;
END//
DELIMITER ;

CALL tmp_add_cua_hang();
DROP PROCEDURE tmp_add_cua_hang;

-- Xóa sản phẩm minh họa còn sót từ phiên bản trước (nếu có).
DELETE FROM san_pham_bong_da WHERE nguon = 'MAU';
