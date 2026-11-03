-- Đánh dấu trận có được nguồn (ESPN) cung cấp dữ liệu live hay không.
-- NULL = chưa kiểm tra, 0 = không có live, 1 = có live.
ALTER TABLE tran_dau
    ADD COLUMN co_live TINYINT NULL DEFAULT NULL AFTER ti_so_khach;
