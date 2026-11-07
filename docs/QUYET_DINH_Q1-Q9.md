# Quyết định phạm vi Q1–Q9 (dùng mặc định đề xuất)

Tài liệu này ghi lại các quyết định chốt theo **mặc định đề xuất** trong đề cương cho các câu hỏi
Q1–Q9 dành cho giảng viên. Mỗi mục nêu: câu hỏi, mặc định đã chọn, và phần tương ứng trong
đồ án (mã nguồn hoặc ghi chú cho báo cáo).

> Trạng thái: **Đã áp dụng mặc định**. Khi giảng viên trả lời khác, cập nhật lại tài liệu này và
> các phần liên quan.

| # | Câu hỏi | Mặc định đã chọn | Trạng thái |
|---|---------|------------------|------------|
| Q1 | "Hai mức tích hợp API" nghĩa là gì? | Mức 1: PHP gọi API ngoài trực tiếp. Mức 2: web service JSON do ứng dụng tự cung cấp cho AJAX | Đã làm cả hai |
| Q2 | Có được thêm chương Kiểm thử và Kết luận không? | Kiểm thử đặt trong Phụ lục, Kết luận 1 trang cuối Chương 6 | Dùng khi viết báo cáo |
| Q3 | Mục 1.1 "Thương mại điện tử" có phải đổi theo đề tài? | Giữ tên mục, viết ngắn về dịch vụ thông tin số trực tuyến, nối sang đề tài | Dùng khi viết báo cáo |
| Q4 | Chương 3 và 6 của mẫu là giỏ hàng/đặt hàng/thêm sản phẩm, có thay bằng chức năng tương đương? | Có giỏ hàng lưu niệm cho tài khoản hoạt động; chưa triển khai đặt hàng/thanh toán | Đã cập nhật theo phạm vi mới |
| Q5 | 65 trang tính cả bìa, mục lục, phụ lục, tài liệu tham khảo không? | Tính tất cả (cách an toàn nhất) | Dùng khi viết báo cáo |
| Q6 | Làm cá nhân hay nhóm? | Chưa biết, xem mục 9 | Dùng khi viết báo cáo |
| Q7 | Cho phép gọi API ngoài từ trình duyệt hay chỉ qua máy chủ? | Chỉ qua máy chủ, vì key không được lộ ra trình duyệt | Đã làm |
| Q8 | Có bắt buộc phạm vi châu Á và tin tức không? | Châu Á: lọc theo khu vực do quản trị gán. Tin tức: Should-have | Đã làm |
| Q9 | Ngày nộp và hình thức bảo vệ? | Chưa đặt hạn, ước lượng bằng ngày công | Dùng khi lập kế hoạch |

## Chi tiết áp dụng trong mã nguồn

### Q1 — Hai mức tích hợp API (đã làm cả hai)
- **Mức 1 (gọi API ngoài từ máy chủ):** `app/Services/ApiFootballClient.php` dùng cURL gọi
  **API-Football (api-sports.io)** với header `x-apisports-key`: `/fixtures?live=all`,
  `/fixtures?date=`, `/countries`, `/leagues`. (Bản cũ `LiveScoreApiClient.php` vẫn giữ làm tùy chọn.)
- **Mức 2 (web service JSON tự cung cấp cho AJAX):** `api/matches.php`, `api/match.php`,
  `api/favorite.php`, `api/admin/sync.php` do `app/Controllers/ApiController.php` xử lý, trả
  `{ ok, data, meta }`.
- **Trừu tượng hóa:** interface `app/Services/FootballDataProvider.php` với ba lớp cài đặt
  `ApiFootballClient`, `LiveScoreApiClient`, `SampleDataProvider`; `SyncService` chỉ phụ thuộc
  interface nên đổi nguồn dữ liệu không đổi nghiệp vụ. Chọn nguồn qua `[api] provider` trong `config.ini`.

### Q7 — Chỉ gọi API ngoài từ máy chủ (đã làm)
- Trình duyệt chỉ gọi `api/*.php` của ứng dụng (`public/app.js` dùng `$.ajax`).
- Key API-Football nằm trong `config.ini`, bị `.gitignore` và bị `.htaccess` chặn truy cập; không có
  endpoint nào trả key ra ngoài.

### Q8 — Phạm vi châu Á + tin tức (đã làm)
- **Khu vực châu Á:** cột `quoc_gia.khu_vuc`; quản trị gán qua `views/admin/leagues.php` →
  `AdminController` → routine `sp_admin_cau_hinh_giai_dau`; người dùng lọc theo khu vực ở
  `views/leagues.php` → routine `sp_ds_giai_dau`. Giá trị mặc định là "Chưa phân loại".
- **Giới hạn request:** chỉ đồng bộ giải có `giai_dau.dang_theo_doi = 1`.
- **Tin tức (Should-have):** bảng `danh_muc_tin`, `tin_tuc`; routine `sp_upsert_tin_tuc`,
  `sp_ds_tin_tuc`, `sp_chi_tiet_tin_tuc`; hiển thị ở `views/news.php`, `views/news_detail.php`.
  Tin được **cào tự động từ VnExpress - Bóng đá** (`App\Services\VnExpressCrawler`, `NewsService`),
  chỉ lưu tiêu đề/sapo/ảnh/link để dẫn nguồn (không sao chép toàn bài); có thể bấm "Cào tin VnExpress"
  ở khu quản trị. Giới hạn theo `ttl_news` (mặc định 3 giờ).

### Q4 — Giỏ hàng lưu niệm, chưa đặt hàng/thanh toán
- Tài khoản đang hoạt động (USER hoặc ADMIN) có thể thêm, cập nhật và xóa sản phẩm trong giỏ; giỏ giới hạn 20 dòng và số lượng 1–10 mỗi sản phẩm.
- Giá luôn đọc từ sản phẩm hiện tại; mặt hàng bị ẩn hoặc hết hàng vẫn được giữ lại nhưng không tính vào tổng.
- Không triển khai đặt hàng, thanh toán hoặc tồn kho theo số lượng.
- Cấu trúc và nghiệp vụ giỏ hàng nằm trong `database/schema.sql`, `database/routines.sql` và `database/migrations/004_cart.sql`.

## Ảnh hưởng tới báo cáo (T14)
- Chương 3 dùng các trang: Trang chủ, Chi tiết trận, Lịch thi đấu, Đội yêu thích, Lưu niệm/giỏ hàng, Quản trị.
- Chương 6 dùng 4 quy trình: đồng bộ API, đăng ký, đăng nhập, lưu đội yêu thích.
- Mục 1.1 giữ tên "Thương mại điện tử" nhưng viết về dịch vụ thông tin số trực tuyến.
- Kiểm thử để trong Phụ lục; Kết luận ở cuối Chương 6.
- Ngân sách trang: tối thiểu 52, tối đa 65 (tính cả bìa, mục lục, phụ lục, tài liệu tham khảo).
