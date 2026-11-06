USE fastfootbalinfo;

CREATE TABLE IF NOT EXISTS danh_muc_luu_niem (
    ma INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ten VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    thu_tu SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (ma),
    UNIQUE KEY uq_dm_luu_niem_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS san_pham_luu_niem (
    ma INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ma_danh_muc INT UNSIGNED NOT NULL,
    ten VARCHAR(200) NOT NULL,
    mo_ta TEXT NOT NULL,
    gia DECIMAL(12,0) NOT NULL,
    anh_url VARCHAR(255) NOT NULL,
    con_hang TINYINT(1) NOT NULL DEFAULT 1,
    hien_thi TINYINT(1) NOT NULL DEFAULT 1,
    ngay_tao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ma),
    KEY idx_spln_danh_muc (ma_danh_muc),
    KEY idx_spln_ngay_tao (ngay_tao),
    CONSTRAINT fk_spln_danh_muc FOREIGN KEY (ma_danh_muc) REFERENCES danh_muc_luu_niem (ma)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO danh_muc_luu_niem (ten, slug, thu_tu) VALUES
    ('Áo đấu', 'ao-dau', 1),
    ('Khăn và cờ', 'khan-va-co', 2),
    ('Phụ kiện', 'phu-kien', 3),
    ('Đồ sưu tầm', 'do-suu-tam', 4),
    ('Quà tặng', 'qua-tang', 5)
ON DUPLICATE KEY UPDATE ten = VALUES(ten), thu_tu = VALUES(thu_tu);

INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Áo đấu phiên bản cổ động', 'Áo thể thao nhẹ, phù hợp cổ vũ trên khán đài.', 350000, 'img/souvenirs/jersey-home.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'ao-dau' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Áo đấu phiên bản cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Áo đấu sắc đỏ cổ động', 'Thiết kế năng động với chất liệu thoáng mát.', 390000, 'img/souvenirs/jersey-red.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'ao-dau' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Áo đấu sắc đỏ cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Áo đấu phong cách hoài niệm', 'Áo cổ điển dành cho người yêu bóng đá.', 420000, 'img/souvenirs/jersey-retro.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'ao-dau' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Áo đấu phong cách hoài niệm');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Áo tập luyện cổ động', 'Kiểu dáng thoải mái cho hoạt động thường ngày.', 320000, 'img/souvenirs/jersey-training.svg', 0, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'ao-dau' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Áo tập luyện cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Khăn len cổ động', 'Khăn len mềm với họa tiết trái bóng đơn giản.', 180000, 'img/souvenirs/scarf-knit.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'khan-va-co' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Khăn len cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Khăn quàng sân vận động', 'Khăn quàng nhẹ, tiện mang theo khi xem bóng đá.', 145000, 'img/souvenirs/scarf-stadium.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'khan-va-co' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Khăn quàng sân vận động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Cờ cổ động cầm tay', 'Lá cờ nhỏ gọn để khuấy động không khí cổ vũ.', 65000, 'img/souvenirs/flag-hand.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'khan-va-co' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Cờ cổ động cầm tay');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Dây cờ tam giác cổ động', 'Dây cờ trang trí cho góc xem bóng đá tại nhà.', 95000, 'img/souvenirs/flag-pennant.svg', 0, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'khan-va-co' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Dây cờ tam giác cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Móc khóa hình trái bóng', 'Móc khóa nhỏ gọn dành cho người hâm mộ.', 55000, 'img/souvenirs/keychain-ball.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'phu-kien' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Móc khóa hình trái bóng');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Mũ lưỡi trai thể thao', 'Mũ vải nhẹ với biểu tượng quả bóng cách điệu.', 210000, 'img/souvenirs/cap-fan.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'phu-kien' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Mũ lưỡi trai thể thao');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Bình nước cổ động', 'Bình nước tiện dụng cho những buổi vận động.', 160000, 'img/souvenirs/bottle-sport.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'phu-kien' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Bình nước cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Túi đeo thể thao', 'Túi đựng vật dụng cá nhân gọn nhẹ.', 250000, 'img/souvenirs/bag-sport.svg', 0, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'phu-kien' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Túi đeo thể thao');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Cúp lưu niệm mini', 'Mô hình chiếc cúp trang trí cho bàn làm việc.', 290000, 'img/souvenirs/trophy-mini.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'do-suu-tam' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Cúp lưu niệm mini');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Bóng sưu tầm trang trí', 'Quả bóng trang trí với họa tiết hình học.', 480000, 'img/souvenirs/collectible-ball.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'do-suu-tam' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Bóng sưu tầm trang trí');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Bộ huy hiệu bóng đá', 'Bộ huy hiệu kim loại với biểu tượng thể thao phổ thông.', 125000, 'img/souvenirs/pin-set.svg', 0, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'do-suu-tam' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Bộ huy hiệu bóng đá');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Ly sứ cổ động', 'Ly sứ có họa tiết sân cỏ tối giản.', 135000, 'img/souvenirs/mug-football.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'qua-tang' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Ly sứ cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Cờ đuôi nheo lưu niệm', 'Cờ vải trang trí với hình cúp cách điệu.', 110000, 'img/souvenirs/pennant-gift.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'qua-tang' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Cờ đuôi nheo lưu niệm');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Vòng tay cổ động', 'Vòng tay vải nhẹ với màu sắc thể thao.', 45000, 'img/souvenirs/wristband.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'qua-tang' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Vòng tay cổ động');
INSERT INTO san_pham_luu_niem (ma_danh_muc, ten, mo_ta, gia, anh_url, con_hang, hien_thi)
SELECT dm.ma, 'Tranh chiến thuật sân cỏ', 'Tranh trang trí lấy cảm hứng từ sơ đồ chiến thuật.', 195000, 'img/souvenirs/tactics-poster.svg', 1, 1 FROM danh_muc_luu_niem dm
WHERE dm.slug = 'qua-tang' AND NOT EXISTS (SELECT 1 FROM san_pham_luu_niem WHERE ten = 'Tranh chiến thuật sân cỏ');
