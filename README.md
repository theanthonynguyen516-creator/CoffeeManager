# CoffeeManager

Hệ thống quản lý quán cà phê — Frontend (HTML/CSS/JS thuần) + Backend (PHP) + Database (Microsoft SQL Server).

## Kiến trúc

```
HTML/CSS/JavaScript  --(Fetch API)-->  PHP REST-like API  -->  Microsoft SQL Server
```

- **Frontend**: multi-page, mỗi chức năng 1 file HTML riêng trong `pages/`.
- **Backend**: PHP chỉ xử lý API (nhận request, kiểm tra quyền, truy vấn SQL Server, trả JSON). **Không** render giao diện.
- **Database**: Microsoft SQL Server, kết nối qua PDO driver `sqlsrv`.

## Cài đặt

### 1. Yêu cầu môi trường
- PHP >= 8.0 với extension `pdo_sqlsrv` và `sqlsrv`
  (tải tại: https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server)
- Microsoft SQL Server (2019+) hoặc SQL Server Express
- Web server: XAMPP/Laragon (Windows) hoặc Apache/Nginx (Linux) chạy PHP

### 2. Tạo Database
Mở **SQL Server Management Studio (SSMS)**, chạy lần lượt theo đúng thứ tự:
```
database/database.sql       -- tạo database + toàn bộ bảng
database/seed.sql            -- tạo tài khoản admin + dữ liệu bắt buộc
database/sample_data.sql     -- (tùy chọn) dữ liệu demo để test giao diện
```

### 3. Cấu hình kết nối
Sửa file `backend/config/database.php`, đổi lại 4 dòng:
```php
define('DB_SERVER', 'localhost');
define('DB_NAME', 'CoffeeManagerDB');
define('DB_USER', 'sa');
define('DB_PASSWORD', 'YourStrong@Passw0rd');
```

### 4. Tạo lại mật khẩu admin (bắt buộc)
Hash mẫu trong `seed.sql` chỉ là ví dụ minh họa. Hãy tạo file PHP tạm để sinh hash thật:
```php
<?php echo password_hash('Admin@123', PASSWORD_DEFAULT);
```
Copy kết quả, chạy lệnh SQL sau trong SSMS để cập nhật:
```sql
UPDATE Users SET PasswordHash = '<hash-vừa-copy>' WHERE Username = 'admin';
```

### 5. Chạy website
Đặt thư mục `CoffeeManager/` vào thư mục gốc web server (vd: `htdocs/` với XAMPP), truy cập:
```
http://localhost/CoffeeManager/pages/login.html
```
Đăng nhập: `admin` / `Admin@123`

## Tiến độ xây dựng (theo lộ trình 20 giai đoạn)

- [x] Giai đoạn 1: Cấu trúc thư mục
- [x] Giai đoạn 2: Thiết kế SQL Server (`database/database.sql`, `seed.sql`, `sample_data.sql`)
- [x] Giai đoạn 3: PHP database connection + middleware (`database.php`, `response.php`, `validation.php`, `auth.php`, `permission.php`)
- [x] Giai đoạn 4: API cơ bản (`auth/login.php`, `logout.php`, `me.php`)
- [x] Giai đoạn 5: `common.css`, `responsive.css`, `api.js`, `common.js`, `auth.js` + trang `pages/login.html`
- [x] Giai đoạn 6: Dashboard (`index.html`, `dashboard.css`, `dashboard.js`, `dashboard/statistics.php`)
- [x] Giai đoạn 7: Trang Sản phẩm (`products.html`, `products.css`, `products.js`, `products/list|create|update|delete.php`, `categories/list.php` tạm thời)
- [x] Giai đoạn 8: Trang Danh mục (`categories.html`, `categories.css`, `categories.js`, `categories/create|update|delete.php`, nâng cấp `list.php` hỗ trợ tìm kiếm + xem cả danh mục ẩn)
- [x] Giai đoạn 9: Trang Bàn (`tables.html`, `tables.css`, `tables.js`, `tables/list|create|update|delete|transfer|merge.php`)
- [x] Giai đoạn 10: Trang POS (`pos.html`, `pos.css`, `pos.js`, `orders/create.php`)
- [x] Giai đoạn 11: Trang Đơn hàng (`orders.html`, `orders.css`, `orders.js`, `orders/list|detail|cancel.php`)
- [x] Giai đoạn 12: Trang Khách hàng (`customers.html`, `customers.css`, `customers.js`, `customers/list|create|update|delete|history.php`)
- [x] Giai đoạn 13: Trang Nhân viên (`employees.html`, `employees.css`, `employees.js`, `employees/list|roles|create|update|delete.php`)
- [x] Giai đoạn 14: Trang Kho (`inventory.html`, `inventory.css`, `inventory.js`, `inventory/list|create|update|delete|transaction|history.php`, `suppliers/list.php` tạm thời)
- [x] Giai đoạn 15: Trang Nhà cung cấp (`suppliers.html`, `suppliers.css`, `suppliers.js`, `suppliers/create|update|delete.php`, nâng cấp `list.php`)
- [x] Giai đoạn 16: Trang Báo cáo (`reports.html`, `reports.css`, `reports.js`, `reports/summary.php`)
- [x] Giai đoạn 17: Trang Cài đặt (`settings.html`, `settings.css`, `settings.js`, `settings/get|update.php`, `auth/update-profile.php`, `auth/change-password.php`; bổ sung bảng `Settings` vào database)
- [x] Giai đoạn 18-20: Kiểm tra API, frontend, luồng nghiệp vụ (rà soát tĩnh toàn bộ code — xem mục "Kết quả rà soát" bên dưới)

## Kết quả rà soát (Giai đoạn 18-20)

Vì môi trường soạn thảo này không có sẵn PHP/SQL Server để chạy trực tiếp, việc kiểm tra được thực hiện bằng cách rà soát tĩnh (static analysis) toàn bộ mã nguồn:

**Đã kiểm tra và xác nhận KHÔNG có lỗi:**
- Cân bằng ngoặc `{} ()` trong toàn bộ 52 file PHP
- 56 lượt gọi `API.get/post/put/delete` trong JS đều khớp với file PHP tồn tại thật trên đĩa
- Toàn bộ `getElementById(...)` trong mỗi file JS đều khớp với `id="..."` có trong đúng file HTML tương ứng của trang đó
- Tên cột SQL dùng trong PHP (`t.TableId`, `o.OrderId`,...) đều khớp với schema thực tế trích xuất từ `database.sql` (20 bảng)
- Field bắt buộc (`validateRequired`) ở mỗi API khớp với payload JS thực sự gửi lên (kiểm tra tay các API quan trọng: POS checkout, chuyển/gộp bàn, CRUD sản phẩm)
- Mọi câu `OFFSET ... FETCH NEXT` (phân trang) đều có `ORDER BY` đứng trước — bắt buộc theo cú pháp SQL Server
- `login.php`/`logout.php` tự gọi `session_start()` riêng (không qua `requireLogin()`) — đúng vì đây là nơi tạo/hủy session
- `common.js` (chạy trên mọi trang kể cả `login.html`) có guard-check (`if (!btn) return`) nên không lỗi khi thiếu phần tử Header/Sidebar ở `login.html`

**Checklist kiểm thử thủ công khi có môi trường XAMPP/SQL Server thật** (không thể tự động hóa trong môi trường soạn thảo này):
1. Chạy `database.sql` → `seed.sql` → (tùy chọn) `sample_data.sql`, xác nhận không lỗi
2. Cập nhật `backend/config/database.php` đúng thông tin kết nối máy bạn
3. Tạo lại hash mật khẩu admin thật (xem mục "Cài đặt" bước 4 phía trên)
4. Đăng nhập bằng `admin` / mật khẩu vừa tạo → kiểm tra Dashboard hiển thị số liệu (sẽ là 0 nếu chưa có đơn hàng)
5. Vào **Sản phẩm** → **Danh mục**: thêm vài danh mục và sản phẩm mẫu
6. Vào **Bàn**: thêm vài bàn mẫu
7. Vào **POS**: chọn bàn, thêm sản phẩm vào giỏ, thanh toán → kiểm tra Dashboard/Đơn hàng cập nhật đúng, bàn tự chuyển về "Trống"
8. Vào **Đơn hàng**: xem chi tiết đơn vừa tạo, thử in hóa đơn, thử hủy đơn
9. Vào **Kho**: thêm nguyên liệu, thử Nhập/Xuất/Điều chỉnh, xác nhận cảnh báo tồn kho thấp hoạt động khi tồn ≤ ngưỡng
10. Vào **Nhân viên**: tạo 1 tài khoản Cashier, đăng xuất, đăng nhập bằng tài khoản đó → xác nhận bị chặn ở các trang chỉ dành cho Admin/Manager (vd: Báo cáo)
11. Vào **Báo cáo**: đổi khoảng ngày, xác nhận số liệu khớp với đơn hàng đã tạo
12. Vào **Cài đặt**: đổi thông tin quán (chỉ thấy được nếu đăng nhập Admin), đổi mật khẩu, thử đăng nhập lại bằng mật khẩu mới
13. Kiểm tra Responsive: thu nhỏ trình duyệt / mở trên điện thoại, xác nhận Sidebar thu gọn được và bảng có thanh cuộn ngang

## Ghi chú quan trọng: Kho & Sản phẩm

Vì database (mục 19 trong yêu cầu gốc) không có bảng "công thức pha chế" (liên kết Product ↔ Ingredients kèm định lượng), hệ thống **không tự động trừ kho nguyên liệu** khi bán sản phẩm ở POS. Việc nhập/xuất kho được thực hiện **thủ công** ở trang Kho (Giai đoạn 14) thông qua `InventoryTransactions`. Đây là lựa chọn đơn giản hóa phù hợp với đồ án sinh viên. Muốn tự động hoàn toàn, cần bổ sung bảng `ProductIngredients` (product_id, ingredient_id, quantity_per_unit) — có thể làm ở bản mở rộng sau.

## Quy tắc kiến trúc (không được vi phạm)

1. `index.html` luôn là file HTML thật, không đổi thành `.php`.
2. Mỗi chức năng có HTML/CSS/JS/API riêng; phần dùng chung tách vào `common.css`, `common.js`, `api.js`.
3. JavaScript **không bao giờ** kết nối SQL Server trực tiếp — luôn đi qua `API.get/post/put/delete()` → PHP.
4. PHP chỉ trả JSON, không render HTML.
5. Mọi truy vấn SQL dùng Prepared Statement (PDO) để chống SQL Injection.
