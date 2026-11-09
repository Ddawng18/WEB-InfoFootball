# FastFootBalInfo

Website thông tin bóng đá: lịch thi đấu, kết quả, chi tiết trận, giải đấu, đội bóng, bảng xếp hạng, tin tức, sản phẩm bóng đá và khu vực quản trị.

Đồ án học phần **Phát triển ứng dụng Web** (UEH), xây dựng bằng **PHP 8 + MySQL/MariaDB**, giao diện **Bootstrap 5**, backend **OOP + MVC**, giao tiếp **AJAX/JSON**.

> Nguyên tắc cốt lõi: nghiệp vụ SQL nằm trong routine CSDL. PHP chỉ gọi `CALL tên_routine(?, ...)` qua `Database::callProc()`; tên routine lấy từ `RoutineName`.

## 1. Tính năng

### Người dùng

- Trang chủ: trận đang đá, sắp diễn ra, đã kết thúc; tự cập nhật bằng AJAX.
- Lịch thi đấu, lọc theo ngày, giải, trạng thái; chi tiết trận.
- Chi tiết trận: diễn biến chính, đội hình, thống kê, đối đầu từ ESPN.
- Giải đấu, đội bóng, bảng xếp hạng.
- Tin tức bóng đá tổng hợp từ VnExpress, có dẫn nguồn.
- Sản phẩm bóng đá từ Tiki, có giá, ảnh, thương hiệu và link mua ngoài.
- Tìm kiếm, phân trang, đội yêu thích.
- Lịch cá nhân: thêm trận, bật nhắc giờ bóng lăn, nhận thông báo trong web.
  Chỉ dùng khi đã đăng nhập; mỗi tài khoản có lịch riêng, xem được đội đã thêm.
- Chỉ trận **sắp diễn ra** (chưa bắt đầu) mới được thêm lịch/nhắc mới; danh sách
  “Sắp diễn ra” có nút nhanh trên từng dòng, trận đang đá/chờ/đã đá không có nút thêm.
- Đăng ký, đăng nhập, đăng xuất.

### Quản trị

- Dashboard, nhật ký đồng bộ.
- Quản lý tài khoản (khóa/mở khóa).
- Cấu hình giải đấu (theo dõi, quốc gia, khu vực).
- Thêm/sửa/xóa tin tức do ADMIN soạn; ảnh tải từ máy.
- Làm mới sản phẩm bóng đá từ Tiki.

## 2. Công nghệ

| Thành phần | Sử dụng |
|---|---|
| Backend | PHP 8.0+ (OOP, namespace, autoload) |
| CSDL | MySQL/MariaDB 10.4+ |
| Giao diện | Bootstrap 5.3.3, Bootstrap Icons, jQuery |
| AJAX | `public/app.js` + `api/*.php` trả JSON |
| Dữ liệu ngoài | ESPN public API, Tiki JSON API, VnExpress |
| Kiến trúc | MVC phân tầng: Controller → Service → Repository → Routine |

## 3. Kiến trúc & luồng dữ liệu

```text
Trình duyệt
   │  HTML + AJAX (JSON)
   ▼
index.php (?r=...)
   │
   ├── PageController / ApiController / AdminController
   │
   ├── Service (MatchService, SyncService, AuthService, FavoriteService,
   │            CalendarService, NewsService, FootballProductFeedService)
   │
   ├── Repository (mỗi phương thức = 1 routine)
   │
   ├── Database::callProc("CALL sp_...(?, ...)")
   │
   └── MySQL/MariaDB routine
```

- `SyncService` lấy dữ liệu qua `FootballDataProvider` (hiện tại: `EspnClient`).
- `FootballProductFeedService` lấy sản phẩm qua `TikiFootballProductProvider`.
- `NewsService` dùng `VnExpressCrawler`.
- `CalendarService` quản lý lịch cá nhân, nhắc trận và thông báo trong web.
- Toàn bộ dữ liệu ngoài chỉ gọi từ máy chủ; không có key/token trong trình duyệt.

### Cây thư mục

```text
app/
  Core/          Config, Database, RoutineName, View, Url, Time,
                 Validator, Csrf, Captcha, SafeHtml, SafeUrl, JsonResponse
  Models/        FootballMatch, League, Team, User
  Repositories/  Base, Match, Catalog, News, User, Favorite, Calendar,
                 Sync, FootballProduct
  Services/      FootballDataProvider (interface), FootballProductProvider (interface),
                 EspnClient, TikiFootballProductProvider,
                 FootballProductNormalizer, FootballProductClassifier, FootballProductPresenter,
                 FootballProductFeedService, SyncService, MatchService,
                 CalendarService, NewsService, AuthService, FavoriteService, VnExpressCrawler
  Controllers/   PageController, ApiController, AdminController
  bootstrap.php, helpers.php
index.php        front controller (?r=...)
router.php       router cho PHP built-in server
.htaccess        chặn truy cập trực tiếp thư mục nội bộ
api/             matches.php, match.php, favorite.php, products.php,
                 calendar.php, notifications.php, admin/sync.php
views/           layout, partials, home, schedule, match, leagues, standings,
                 teams, team, favorites, calendar, news, news_detail, san_pham,
                 auth/*, admin/*
config/          tiki_source.json
database/        schema.sql, seed.sql, routines.sql, migrations/
public/          style.css, app.js, img/, images/
tools/           smoke_test.php, check_no_sql.php, news_check.php, real_sync_check.php
```

## 4. Cài đặt

### 4.1. Yêu cầu

- PHP 8.0+ với `mysqli`, `curl`, `mbstring`, `dom`, `fileinfo`.
- MySQL/MariaDB 10.4+.
- Apache có `.htaccess` (hoặc Nginx cấu hình tương đương).
- Internet để tải Bootstrap/jQuery qua CDN và gọi API ngoài.

### 4.2. Cấu hình

Sao chép `config.example.ini` thành `config.ini` (file này **không commit**):

```ini
[db]
host = "127.0.0.1"
port = 3306
name = "fastfootbalinfo"
user = "root"
pass = ""

[api]
provider = "espn"
espn_base_url = "https://site.api.espn.com/apis/site/v2/sports/soccer"
espn_guide_url = "https://site.web.api.espn.com/apis/personalized/site/v2/guide/feed?limit=200"
espn_leagues = "eng.1,esp.1,ita.1,ger.1,fra.1,uefa.champions,uefa.wchampions,esp.copa_del_rey,mex.w.1,fifa.friendly.w"
espn_request_delay_ms = 150

[sync]
ttl_live = 25
ttl_fixtures = 2700
ttl_catalog = 10800
ttl_news = 30

[san_pham]
sources_file = "config/tiki_source.json"
ttl = 1800
max_san_pham = 450
max_seconds = 100
max_trang = 10
chi_tiet_toi_da = 30
retention_days = 7
```

- `[api]`: ESPN không cần API key.
- `[san_pham]`: sản phẩm lấy từ Tiki; từ khóa/danh mục nằm trong `config/tiki_source.json`.
- Bộ cào dùng `https://api.tiki.vn/v2/products`, duyệt các nhánh danh mục bóng đá
  (`category_ids`) rồi bổ sung bằng từ khóa. Một lần làm mới đầy đủ khoảng 400 sản phẩm,
  mất khoảng 90–110 giây; sản phẩm không thấy lại được giữ thêm 7 ngày.
- Không đưa `config.ini` lên GitHub.

### 4.3. Import CSDL

Tạo CSDL `fastfootbalinfo` (hoặc chỉnh tên trong `config.ini` và file SQL), sau đó import theo thứ tự:

```bat
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/schema.sql"
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/seed.sql"
C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/routines.sql"
```

Nếu nâng cấp CSDL cũ:

```text
database/migrations/001_news_crawl.sql
database/migrations/006_football_products.sql
database/migrations/007_product_shop.sql
database/migrations/008_live_coverage.sql
database/migrations/009_calendar_reminders.sql
```

Sau đó chạy lại `database/routines.sql`.

> Trên host không cho `CREATE DATABASE`/`USE`, bỏ các dòng `CREATE DATABASE`/`USE fastfootbalinfo`
> và import vào database đã chọn.

### 4.4. Chạy

- Apache: mở `http://localhost/fastfootbalinfo/`.
- PHP built-in server:

```bat
C:\xampp\php\php.exe -S 127.0.0.1:8099 router.php
```

## 5. Tài khoản mẫu

| Vai trò | Tên đăng nhập | Mật khẩu |
|---|---|---|
| Quản trị | `admin` | `Admin@123` |
| Người dùng | `user01` | `User@123` |

Đổi mật khẩu sau khi cài đặt.

## 6. Nguồn dữ liệu

| Nguồn | Dữ liệu | Ghi chú |
|---|---|---|
| ESPN public API | Trận đấu, lịch, tỉ số, chi tiết trận | Không cần key; API không chính thức, có thể rate-limit |
| Tiki JSON API | Sản phẩm bóng đá, giá, ảnh, shop | Cấu hình trong `config/tiki_source.json` |
| VnExpress | Tin tức bóng đá | Chỉ lưu tiêu đề/sapo/ảnh/link, có dẫn nguồn |

- `SyncService` có TTL + nhật ký đồng bộ, tránh gọi nguồn quá dày.
- Nếu ESPN vẫn báo `SAP_DIEN_RA` sau giờ bóng lăn, trận hiển thị **“Chờ cập nhật”**,
  tỉ số để “–”, không tự nhận “Đang đá”.
- Hệ thống đọc `header.competitions[0].liveAvailable` từ `summary?event=...` và lưu vào
  `tran_dau.co_live`; nếu `false`, giao diện ghi rõ “ESPN không cung cấp live cho trận này”.
  Khi ESPN chuyển sang `in`/`post`, trạng thái và tỉ số thật được cập nhật tự động.
- Trận chưa diễn ra: diễn biến/thống kê/đội hình phụ thuộc dữ liệu ESPN; mục đối đầu vẫn hiển thị.

## 7. Bảo mật

- `password_hash` / `password_verify`; khóa tài khoản sau 5 lần sai (15 phút).
- CSRF token cho form/POST; đăng xuất bằng POST.
- `htmlspecialchars` (`h()`) khi in dữ liệu; `SafeHtml` lọc HTML tin tức.
- Prepared statement cho `CALL`; SQL nghiệp vụ nằm trong routine.
- Session cookie `HttpOnly`, `SameSite=Lax`, `Secure` khi HTTPS; regenerate khi đăng nhập.
- CAPTCHA session cho đăng ký.
- `.htaccess`/`router.php` chặn `app/`, `database/`, `config/`, `views/`, `tools/`.
- Trên production đặt `debug = 0` để không lộ stack trace/CSDL.

## 8. Kiểm thử

```bat
; Kiểm tra không có SQL nghiệp vụ rải trong PHP (kỳ vọng 0 vi phạm)
C:\xampp\php\php.exe tools\check_no_sql.php

; Smoke test kết nối CSDL, routine, đăng nhập
C:\xampp\php\php.exe tools\smoke_test.php

; Kiểm tra crawl tin VnExpress
C:\xampp\php\php.exe tools\news_check.php

; Kiểm tra đồng bộ thật theo provider đang cấu hình
C:\xampp\php\php.exe tools\real_sync_check.php
```

## 9. Triển khai

- Upload mã nguồn; **không upload** `config.ini`, dump CSDL, log/cache hoặc tệp nội bộ.
- Tạo `config.ini` trên server với thông tin CSDL thật.
- Import `schema.sql` + `seed.sql` + `routines.sql` (và migration nếu nâng cấp).
- Trỏ document root vào thư mục dự án; bật SSL; đặt `debug = 0`.
- Cấp quyền ghi cho thư mục cache/ảnh nếu triển khai thêm; phiên bản hiện tại dùng logo URL từ nguồn.

## 10. Phạm vi hiện tại

- Sản phẩm được đồng bộ từ Tiki bằng nút “Làm mới sản phẩm bóng đá” trong trang quản trị.
- Không có cart/thanh toán nội bộ; người dùng mua qua link của sàn.
- ESPN là API công khai không chính thức; nếu bị rate-limit, tăng `espn_request_delay_ms` hoặc giảm tần suất.
- Các tính năng upload/WYSIWYG không nằm trong phạm vi bản này.
