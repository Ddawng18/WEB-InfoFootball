# FastFootBalInfo

Website **thông tin bóng đá** (lịch thi đấu, kết quả, chi tiết trận, giải đấu, đội bóng, đội yêu thích,
quản trị) tích hợp **Live Score API bên ngoài** đồng bộ vào **MySQL/MariaDB**.

Đồ án học phần *Phát triển ứng dụng Web* (UEH). Đây là bản MVP bám sát đề cương:
**toàn bộ nghiệp vụ SQL nằm trong routine CSDL; PHP chỉ gọi `CALL tên_routine(?, ...)`.**

## 1. Yêu cầu môi trường

- XAMPP (đã kiểm thử: **PHP 8.0.30**, **MariaDB 10.4.32**).
- Bật extension `mysqli`, `curl` trong `php.ini` (XAMPP mặc định đã bật).
- Trình duyệt hiện đại (Bootstrap 5 qua CDN, cần Internet để lấy CSS/JS).

## 2. Cài đặt

1. **Đặt mã nguồn** vào web root, ví dụ `C:\xampp\htdocs\fastfootbalinfo`.
2. **Tạo cấu hình**: sao chép `config.example.ini` thành `config.ini` rồi điền thông tin:
   - mục `[db]`: tài khoản CSDL (mặc định XAMPP: user `root`, mật khẩu rỗng).
   - mục `[api]`: chọn nguồn dữ liệu:
     - `provider = sample` — chạy bằng dữ liệu mẫu trong `sample/` (không cần key, mặc định).
     - `provider = apifootball` — dùng **API-Football** (api-sports.io), điền `key` vào `config.ini`.
       Đặt `season` (gói Free chỉ cho mùa 2022–2024) và `countries` (danh sách quốc gia lấy giải).
     - `provider = livescore` — dùng Live Score API cũ, kèm `key`, `secret`.
   - **Không commit `config.ini`** (đã có trong `.gitignore`).
3. **Import CSDL** (theo thứ tự):
   ```bat
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/schema.sql"
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/seed.sql"
   C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 -e "source database/routines.sql"
   ```
   Tất cả dùng `utf8mb4`. CSDL tên `fastfootbalinfo`.
   > Nếu CSDL đã tạo từ trước và bạn không muốn import lại, chạy thêm migration:
   > `mysql -u root fastfootbalinfo < database/migrations/001_news_crawl.sql` rồi chạy lại `database/routines.sql`.
4. **Truy cập**: `http://localhost/fastfootbalinfo/`.

## 3. Tài khoản mẫu

| Vai trò | Tên đăng nhập | Mật khẩu |
|---------|---------------|----------|
| Quản trị | `admin` | `Admin@123` |
| Người dùng | `user01` | `User@123` |

## 4. Kiến trúc & luồng dữ liệu

```
Trình duyệt ──AJAX 30s──> api/*.php (ApiController)
                                │
                                ▼
                         Service (Match/Sync/Auth/Favorite)
                                │
                                ▼
                    Repository (mỗi hàm = 1 routine)
                                │
                                ▼
                  Database::callProc("CALL sp_...(?,...)")
                                │
                                ▼
                          MySQL/MariaDB  (routine)
```
`SyncService` là **thành phần duy nhất gọi API ngoài** (qua `FootballDataProvider`). Dữ liệu chảy một chiều:
`API → Provider → SyncService → routine (upsert) → MySQL → routine (đọc) → PHP → JSON → AJAX → giao diện`.
Trình duyệt **không bao giờ** gọi API bóng đá trực tiếp (key/secret nằm ở máy chủ).

### Cây thư mục
```
app/
  Core/        Config, Database (callProc), RoutineName, Validator, JsonResponse, Csrf, View, Url, Time
  Models/      FootballMatch, League, Team, User
  Repositories/ Base, Match, Catalog, User, Favorite, Sync
  Services/    FootballDataProvider(interface), LiveScoreApiClient, SampleDataProvider,
               SyncService, MatchService, AuthService, FavoriteService
  Controllers/ PageController, ApiController, AdminController
  bootstrap.php, helpers.php
api/           matches.php, match.php, favorite.php, admin/sync.php
views/         layout, partials, home, schedule, match, leagues, teams, team, favorites,
               news, news_detail, auth/*, admin/*
database/      schema.sql, seed.sql, routines.sql
sample/        live.json, fixtures.json, leagues.json   (provider = sample)
public/        style.css, app.js
tools/         smoke_test.php, check_no_sql.php
```

Tài liệu giao diện (bố cục, component, trạng thái, responsive, quy ước): [`UI.md`](UI.md).

## 5. Ánh xạ đề cương → mã nguồn

| Mã | Chức năng | Nơi hiện thực |
|----|-----------|----------------|
| F1 | Trang chủ (3 nhóm trận) | `views/home.php`, `MatchService::getHome`, `api/matches.php?view=home` |
| F2 | Lịch + lọc | `views/schedule.php`, `MatchService::getSchedule`, `sp_ds_tran_dau` |
| F3 | Chi tiết trận | `views/match.php`, `MatchService::getDetail`, `sp_chi_tiet_tran_dau` |
| F4 | Giải đấu + khu vực | `views/leagues.php`, `CatalogRepository`, `sp_ds_giai_dau` |
| F5 | Đội bóng + chi tiết | `views/teams.php`, `views/team.php`, `sp_ds_doi_bong`, `sp_chi_tiet_doi_bong` |
| F6 | Đồng bộ API → MySQL | `SyncService`, `FootballDataProvider`, các `sp_upsert_*`, `sp_bat_dau/ket_thuc_dong_bo` |
| F7 | Đăng ký/đăng nhập (khóa 5 lần) | `AuthService`, `sp_dang_ky_tai_khoan`, `sp_lay_tai_khoan_dang_nhap`, `sp_ghi_nhan_dang_nhap` |
| F8 | Quản trị | `AdminController`, `views/admin/*`, `sp_thong_ke_tong_quan`, `sp_admin_*` |

Ràng buộc cốt lõi (đề cương mục 7.1): mọi thao tác CSDL đi qua routine; `Database::callProc` là nơi **duy nhất**
phát sinh `CALL`; tên routine lấy từ hằng số `RoutineName` (không nhận từ request).

## 6. Kiểm thử

```bat
; Quét toàn bộ .php tìm SQL nghiệp vụ (kết quả mong đợi: 0 vi phạm)
C:\xampp\php\php.exe tools\check_no_sql.php

; Smoke test tầng nghiệp vụ (cần CSDL đã import + config.ini)
C:\xampp\php\php.exe tools\smoke_test.php
```

Chạy thử nhanh bằng PHP built-in server (không cần Apache):
```bat
C:\xampp\php\php.exe -S 127.0.0.1:8099 -t fastfootbalinfo
```

Cào tin bóng đá từ VnExpress (kiểm tra nhanh, không cần qua web):
```bat
C:\xampp\php\php.exe tools\news_check.php
```

Dọn dữ liệu MẪU (khi đã có dữ liệu thật, tránh trận "đang đá" giả đứng yên):
```bat
C:\xampp\php\php.exe tools\purge_sample.php
```
> Dữ liệu mẫu dùng mã riêng `nguon='SAMPLE'` nên đồng bộ thật (`nguon='APIFOOTBALL'`) không cập nhật chúng.
> Khôi phục dữ liệu mẫu: đặt `provider = "sample"` rồi đồng bộ, hoặc import lại `schema.sql` + `seed.sql`.

## 6.1. Quyết định phạm vi (Q1–Q9)

Các quyết định chốt theo mặc định đề xuất được ghi tại [`docs/QUYET_DINH_Q1-Q9.md`](docs/QUYET_DINH_Q1-Q9.md):
tích hợp API hai mức (Q1), chỉ gọi API ngoài từ máy chủ (Q7), phạm vi châu Á theo khu vực do quản trị
gán + tin tức là Should-have (Q8), và loại bỏ giỏ hàng/đặt hàng (Q4).

## 7. Ghi chú thiết kế

- **Múi giờ**: cột thời gian lưu **UTC**; kết nối MySQL được ép `SET time_zone = '+00:00'` để `NOW()`
  đồng bộ với PHP; hiển thị theo `display_timezone` (mặc định `Asia/Ho_Chi_Minh`).
- **Bảo mật**: dùng `password_hash`/`password_verify` (thay `md5` của bài giảng), token CSRF cho form/POST,
  `htmlspecialchars` khi in dữ liệu, `session_regenerate_id()` khi đăng nhập.
- **Chống trùng**: khóa duy nhất `(nguon, ma_api)` cho trận/giải/đội; `INSERT ... ON DUPLICATE KEY UPDATE`;
  trận đã `KET_THUC` không bị ghi đè.
- **Logo đội thật**: API-Football trả `teams.*.logo`; được lưu vào `doi_bong.logo_url` khi đồng bộ
  (`sp_upsert_doi_bong` nhận thêm `p_logo_url`, không ghi đè logo cũ bằng giá trị rỗng) và hiển thị qua
  `crest_html()` — ảnh logo có fallback tự động về chữ cái đầu khi thiếu hoặc tải lỗi. Đội dữ liệu mẫu
  (nguồn `SAMPLE`) không có logo thật nên hiển thị chữ cái đầu.
  > CSDL đã tạo trước: chạy lại `database/routines.sql` (chữ ký routine đổi), sau đó đồng bộ lại (LIVE/FIXTURES)
  > để nạp `logo_url`.
- **Chế độ dữ liệu mẫu**: `provider = sample` đọc `sample/*.json` → demo chạy được khi API lỗi/hết quota.
- **Tin tức (S4)**: cào tiêu đề/sapo/ảnh/link từ chuyên mục
  [VnExpress - Bóng đá](https://vnexpress.net/bong-da) qua `App\Services\VnExpressCrawler`,
  lưu vào `tin_tuc` bằng routine `sp_upsert_tin_tuc` (khóa chống trùng `(nguon, ma_ngoai)`).
  Chỉ lưu phần dẫn nguồn (không sao chép toàn bài); trang chi tiết có nút mở bài gốc.
  Đồng bộ mỗi **30 giây** (`ttl_news`): tự cào khi mở trang Tin tức, khi trang chủ gọi AJAX 30 giây,
  hoặc bấm "Cào tin VnExpress" ở quản trị.
- **Phân trang**: mọi danh sách chia trang ở tầng routine (`COUNT(*) OVER ()` cho tổng + `LIMIT/OFFSET`).
  Trang chủ có 3 cột phân trang độc lập (đang đá / sắp diễn ra / vừa kết thúc), mỗi cột **5 dòng/trang**
  với tham số `p_dang`, `p_sap`, `p_kt`; Giải đấu **9/trang**; Đội bóng **12/trang**;
  Tin tức **9/trang**; Đội yêu thích **10/trang**.
- **API-Football (api-sports.io)**: xác thực bằng header `x-apisports-key`; chỉ gọi từ máy chủ.
  Gói Free giới hạn **100 request/ngày**, `/fixtures` chỉ trong khoảng ngày hiện tại **±1 ngày**,
  `/leagues` chỉ mùa **2022–2024**. Tần suất đồng bộ (TTL, `config.ini`):
  **thông tin trận đấu mỗi 45 phút** (`ttl_live`/`ttl_fixtures = 2700`), **danh mục giải/đội mỗi 3 giờ**
  (`ttl_catalog = 10800`), **tin tức mỗi 30 giây** (`ttl_news = 30`). Chế độ `sample` là phương án dự phòng khi hết quota.
- **Nhóm Should**: `bang_xep_hang`, `su_kien_tran`, `nhat_ky_dang_nhap`, `danh_muc_tin`, `tin_tuc`
  đã có routine/bảng; một phần đã hiển thị (sự kiện trận, tin tức).
