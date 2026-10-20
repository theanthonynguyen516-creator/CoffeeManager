/* =====================================================================
   FILE: database/seed.sql
   MỤC ĐÍCH: Chèn dữ liệu bắt buộc phải có để hệ thống chạy được
             (vai trò, tài khoản admin, trạng thái bàn...)
   LƯU Ý: Chạy SAU khi đã chạy database.sql
   ===================================================================== */

USE CoffeeManagerDB;
GO

/* ---------- 1. Roles ---------- */
INSERT INTO Roles (RoleName, Description) VALUES
(N'Admin',    N'Toàn quyền quản trị hệ thống'),
(N'Manager',  N'Quản lý quán, xem báo cáo, quản lý nhân viên'),
(N'Cashier',  N'Thu ngân, bán hàng tại quầy'),
(N'Staff',    N'Nhân viên phục vụ');
GO

/* ---------- 2. Permissions (ví dụ cơ bản) ---------- */
INSERT INTO Permissions (PermissionCode, Description) VALUES
(N'product.view',   N'Xem sản phẩm'),
(N'product.manage', N'Thêm/Sửa/Xóa sản phẩm'),
(N'order.create',   N'Tạo đơn hàng'),
(N'order.manage',   N'Quản lý toàn bộ đơn hàng'),
(N'report.view',    N'Xem báo cáo'),
(N'employee.manage',N'Quản lý nhân viên'),
(N'inventory.manage',N'Quản lý kho'),
(N'settings.manage', N'Thay đổi cấu hình hệ thống');
GO

/* Admin có tất cả quyền */
INSERT INTO RolePermissions (RoleId, PermissionId)
SELECT (SELECT RoleId FROM Roles WHERE RoleName = N'Admin'), PermissionId FROM Permissions;
GO

/* ---------- 3. Tài khoản Admin mặc định ----------
   Username: admin
   Password: Admin@123
   (Hash bên dưới được tạo bằng PHP password_hash('Admin@123', PASSWORD_DEFAULT))
*/
INSERT INTO Users (Username, PasswordHash, FullName, Email, RoleId, IsActive)
VALUES (
    'admin',
    '$2y$10$k3G0hV1p0m3F6h8I0m8Zn.4qzYV1r0z1cQ8m1WowH1lUEuF9Yy3S6', -- Admin@123 (sẽ tạo lại ở bước cài đặt PHP)
    N'Quản trị viên',
    'admin@coffeemanager.local',
    (SELECT RoleId FROM Roles WHERE RoleName = N'Admin'),
    1
);
GO

INSERT INTO Employees (UserId, Position, HireDate)
VALUES ((SELECT UserId FROM Users WHERE Username = 'admin'), N'Quản trị viên', GETDATE());
GO

/* ---------- 4. Trạng thái bàn ---------- */
INSERT INTO TableStatuses (StatusCode, StatusName) VALUES
('EMPTY',    N'Trống'),
('IN_USE',   N'Đang sử dụng'),
('RESERVED', N'Đã đặt'),
('CLEANING', N'Đang dọn');
GO

/* ---------- 5. Cài đặt hệ thống mặc định ---------- */
INSERT INTO Settings (SettingKey, SettingValue) VALUES
('ShopName', N'CoffeeManager'),
('ShopAddress', N'123 Đường Nguyễn Huệ, Quận 1, TP.HCM'),
('ShopPhone', N'0900 000 000'),
('ShopLogo', N''),
('LoyaltyPointsPer10k', N'1');
GO

PRINT 'Chèn dữ liệu seed thành công! Đăng nhập bằng admin / Admin@123';
GO
