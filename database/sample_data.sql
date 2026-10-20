/* =====================================================================
   FILE: database/sample_data.sql
   MỤC ĐÍCH: Dữ liệu demo để test giao diện (không bắt buộc phải chạy)
   LƯU Ý: Chạy SAU database.sql và seed.sql
   ===================================================================== */

USE CoffeeManagerDB;
GO

/* ---------- Danh mục ---------- */
INSERT INTO Categories (CategoryName, Description) VALUES
(N'Cà phê',       N'Các loại cà phê pha máy, pha phin'),
(N'Trà sữa',      N'Trà sữa các loại'),
(N'Nước ép',      N'Nước ép trái cây tươi'),
(N'Bánh ngọt',    N'Bánh ngọt ăn kèm');
GO

/* ---------- Sản phẩm ---------- */
INSERT INTO Products (ProductName, CategoryId, SellPrice, CostPrice, Description) VALUES
(N'Cà phê đen',     (SELECT CategoryId FROM Categories WHERE CategoryName = N'Cà phê'), 25000, 10000, N'Cà phê phin truyền thống'),
(N'Cà phê sữa',     (SELECT CategoryId FROM Categories WHERE CategoryName = N'Cà phê'), 29000, 12000, N'Cà phê phin sữa đá'),
(N'Bạc xỉu',        (SELECT CategoryId FROM Categories WHERE CategoryName = N'Cà phê'), 32000, 13000, N'Nhiều sữa ít cà phê'),
(N'Trà sữa trân châu', (SELECT CategoryId FROM Categories WHERE CategoryName = N'Trà sữa'), 35000, 15000, N'Trân châu đen dai ngon'),
(N'Nước ép cam',    (SELECT CategoryId FROM Categories WHERE CategoryName = N'Nước ép'), 30000, 14000, N'Cam vắt tươi 100%'),
(N'Bánh tiramisu',  (SELECT CategoryId FROM Categories WHERE CategoryName = N'Bánh ngọt'), 40000, 20000, N'Bánh tiramisu Ý');
GO

/* ---------- Bàn ---------- */
INSERT INTO Tables (TableName, Capacity, TableStatusId) VALUES
(N'Bàn 01', 4, (SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY')),
(N'Bàn 02', 4, (SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY')),
(N'Bàn 03', 2, (SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'IN_USE')),
(N'Bàn 04', 6, (SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'RESERVED')),
(N'Bàn 05', 4, (SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY'));
GO

/* ---------- Khách hàng ---------- */
INSERT INTO Customers (FullName, Phone, Email, LoyaltyPoints) VALUES
(N'Nguyễn Văn A', '0901111111', 'a.nguyen@example.com', 120),
(N'Trần Thị B',   '0902222222', 'b.tran@example.com', 45);
GO

/* ---------- Nhà cung cấp ---------- */
INSERT INTO Suppliers (SupplierName, ContactName, Phone, Email, Address) VALUES
(N'Công ty Cà phê Trung Nguyên', N'Lê Văn C', '0281234567', 'contact@trungnguyen.example', N'TP.HCM'),
(N'Công ty Sữa Vinamilk', N'Phạm Thị D', '0287654321', 'contact@vinamilk.example', N'TP.HCM');
GO

/* ---------- Nguyên liệu ---------- */
INSERT INTO Ingredients (IngredientName, Unit, QuantityInStock, LowStockThreshold, SupplierId) VALUES
(N'Cà phê hạt', N'kg', 20, 5, (SELECT SupplierId FROM Suppliers WHERE SupplierName = N'Công ty Cà phê Trung Nguyên')),
(N'Sữa đặc',    N'lon', 50, 10, (SELECT SupplierId FROM Suppliers WHERE SupplierName = N'Công ty Sữa Vinamilk')),
(N'Trân châu',  N'kg', 8, 3, NULL);
GO

PRINT 'Chèn dữ liệu demo thành công!';
GO
