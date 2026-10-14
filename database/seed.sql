-- ============================================================
-- FastFootBalInfo - seed.sql
-- Dữ liệu mẫu (dữ liệu giả lập để kiểm thử, ghi rõ trong báo cáo).
-- 2 vai trò, 1 admin, 1 user, 3 quốc gia, 3 giải, 8 đội, 15 trận (đủ 6 trạng thái).
-- Mật khẩu mẫu: admin / Admin@123 ; user01 / User@123
-- ============================================================

USE fastfootbalinfo;

-- Vai trò
INSERT INTO vai_tro (vai_tro_id, ma, ten) VALUES
    (1, 'ADMIN', 'Quản trị viên'),
    (2, 'USER',  'Người dùng');

-- Tài khoản (hash bcrypt của Admin@123 / User@123)
INSERT INTO tai_khoan (tai_khoan_id, ten_dang_nhap, email, ho_ten, mat_khau_hash, vai_tro_id) VALUES
    (1, 'admin',  'admin@fastfoot.local',  'Quản trị viên', '$2y$10$vAGLTHlTzSFTssyAGoeO8u0mE9YlNFMdTfN.YO98c7BQXZY3U75pK', 1),
    (2, 'user01', 'user01@fastfoot.local', 'Người dùng 01', '$2y$10$NA4p9yu4TxEgzE8eB/fVyuJXu8TJclQ222AYcM0fAO/MAKKblo2Wu', 2);

-- Quốc gia (khu_vuc do quản trị gán; còn lại "Chưa phân loại")
INSERT INTO quoc_gia (quoc_gia_id, ma_api, ten, khu_vuc) VALUES
    (1, 'VN', 'Việt Nam',  'Đông Nam Á'),
    (2, 'JP', 'Nhật Bản',  'Đông Á'),
    (3, 'EN', 'Anh',       'Châu Âu');

-- Giải đấu (dang_theo_doi = giải đang được bật theo dõi)
INSERT INTO giai_dau (giai_dau_id, nguon, ma_api, ten, quoc_gia_id, mua_giai, dang_theo_doi) VALUES
    (1, 'SAMPLE', 'VL1', 'V.League 1',      1, '2026', 1),
    (2, 'SAMPLE', 'J1',  'J1 League',       2, '2026', 1),
    (3, 'SAMPLE', 'PL',  'Premier League',  3, '2026', 0);

-- Đội bóng
INSERT INTO doi_bong (doi_bong_id, nguon, ma_api, ten, quoc_gia_id) VALUES
    (1, 'SAMPLE', 'HNFC', 'Hà Nội FC',        1),
    (2, 'SAMPLE', 'HAGL', 'Hoàng Anh Gia Lai',1),
    (3, 'SAMPLE', 'KAW',  'Kawasaki Frontale',2),
    (4, 'SAMPLE', 'URA',  'Urawa Red Diamonds',2),
    (5, 'SAMPLE', 'MCI',  'Manchester City',  3),
    (6, 'SAMPLE', 'LIV',  'Liverpool',        3),
    (7, 'SAMPLE', 'ARS',  'Arsenal',          3),
    (8, 'SAMPLE', 'CHE',  'Chelsea',          3);

-- Trận đấu (giờ UTC). 15 trận đủ 6 trạng thái.
INSERT INTO tran_dau
    (tran_dau_id, nguon, ma_api, giai_dau_id, doi_nha_id, doi_khach_id, thoi_gian_bat_dau, trang_thai, phut, ti_so_nha, ti_so_khach, cap_nhat_luc) VALUES
    (1,  'SAMPLE', 'M001', 1, 1, 2, '2026-10-12 10:00:00', 'DANG_DA',        34, 1, 0, '2026-10-12 10:34:00'),
    (2,  'SAMPLE', 'M002', 1, 2, 1, '2026-10-11 10:00:00', 'KET_THUC',       90, 2, 1, '2026-10-11 11:55:00'),
    (3,  'SAMPLE', 'M003', 1, 1, 2, '2026-10-13 10:00:00', 'HOAN',            0, 0, 0, '2026-10-12 08:00:00'),
    (4,  'SAMPLE', 'M004', 1, 2, 1, '2026-10-14 10:00:00', 'SAP_DIEN_RA',     0, 0, 0, '2026-10-12 07:00:00'),
    (5,  'SAMPLE', 'M005', 2, 3, 4, '2026-10-12 11:00:00', 'NGHI_GIUA_HIEP', 45, 0, 0, '2026-10-12 11:45:00'),
    (6,  'SAMPLE', 'M006', 2, 4, 3, '2026-10-10 11:00:00', 'KET_THUC',       90, 3, 2, '2026-10-10 12:55:00'),
    (7,  'SAMPLE', 'M007', 2, 3, 4, '2026-10-15 11:00:00', 'HUY',             0, 0, 0, '2026-10-12 06:00:00'),
    (8,  'SAMPLE', 'M008', 2, 4, 3, '2026-10-16 11:00:00', 'SAP_DIEN_RA',     0, 0, 0, '2026-10-12 07:00:00'),
    (9,  'SAMPLE', 'M009', 3, 5, 6, '2026-10-12 12:30:00', 'DANG_DA',        67, 2, 1, '2026-10-12 13:37:00'),
    (10, 'SAMPLE', 'M010', 3, 6, 7, '2026-10-09 19:00:00', 'KET_THUC',       90, 1, 1, '2026-10-09 20:55:00'),
    (11, 'SAMPLE', 'M011', 3, 7, 8, '2026-10-13 19:00:00', 'SAP_DIEN_RA',     0, 0, 0, '2026-10-12 07:00:00'),
    (12, 'SAMPLE', 'M012', 3, 8, 5, '2026-10-13 20:00:00', 'HOAN',            0, 0, 0, '2026-10-12 08:00:00'),
    (13, 'SAMPLE', 'M013', 3, 6, 5, '2026-10-08 17:30:00', 'KET_THUC',       90, 0, 2, '2026-10-08 19:25:00'),
    (14, 'SAMPLE', 'M014', 3, 7, 6, '2026-10-17 15:00:00', 'SAP_DIEN_RA',     0, 0, 0, '2026-10-12 07:00:00'),
    (15, 'SAMPLE', 'M015', 3, 8, 7, '2026-10-18 15:00:00', 'HUY',             0, 0, 0, '2026-10-12 06:00:00');

-- Nhật ký đồng bộ mẫu (mốc cũ để TTL không chặn lần đồng bộ thật đầu tiên)
INSERT INTO nhat_ky_dong_bo (nhat_ky_id, loai, bat_dau, ket_thuc, trang_thai, so_ban_ghi, so_bo_qua, thong_bao) VALUES
    (1, 'CATALOG', '2026-10-01 06:00:00', '2026-10-01 06:00:02', 'THANH_CONG', 15, 0, 'Đồng bộ dữ liệu mẫu ban đầu');

-- Đội yêu thích mẫu (user01 thích Hà Nội FC)
INSERT INTO doi_bong_yeu_thich (tai_khoan_id, doi_bong_id) VALUES (2, 1);

-- Bảng xếp hạng mẫu (V.League 1)
INSERT INTO bang_xep_hang (giai_dau_id, doi_bong_id, mua_giai, hang, so_tran, thang, hoa, thua, ban_thang, ban_thua, diem) VALUES
    (1, 1, '2026', 1, 10, 7, 2, 1, 20, 8, 23),
    (1, 2, '2026', 2, 10, 5, 3, 2, 15, 11, 18);

-- Sự kiện trận mẫu (trận 2 đã kết thúc 2-1)
INSERT INTO su_kien_tran (tran_dau_id, phut, loai, doi_bong_id, cau_thu) VALUES
    (2, 15, 'BAN_THANG', 2, 'Văn Toàn'),
    (2, 40, 'BAN_THANG', 1, 'Văn Quyết'),
    (2, 78, 'BAN_THANG', 2, 'Brandão'),
    (2, 65, 'THE_VANG',  1, 'Duy Mạnh');

-- Tin tức mẫu
INSERT INTO danh_muc_tin (danh_muc_id, ten) VALUES
    (1, 'Giải đấu'),
    (2, 'Đội bóng');

INSERT INTO tin_tuc (danh_muc_id, tieu_de, noi_dung) VALUES
    (1, 'V.League 1 trở lại với vòng đấu hấp dẫn', '<p>Vòng đấu cuối tuần hứa hẹn nhiều bất ngờ.</p>'),
    (2, 'Hà Nội FC quyết tâm bảo vệ ngôi đầu', '<p>Đội bóng thủ đô đang có phong độ cao.</p>');
