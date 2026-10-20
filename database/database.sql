/* =====================================================================
   FILE: database/database.sql
   MỤC ĐÍCH: Tạo toàn bộ database + bảng cho hệ thống CoffeeManager
   DÙNG CHO: Microsoft SQL Server (SSMS / sqlcmd)
   CÁCH DÙNG: Mở file này trong SSMS -> Execute (F5)
   ===================================================================== */

IF DB_ID('CoffeeManagerDB') IS NOT NULL
BEGIN
    ALTER DATABASE CoffeeManagerDB SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE CoffeeManagerDB;
END
GO

CREATE DATABASE CoffeeManagerDB;
GO

USE CoffeeManagerDB;
GO

/* =====================================================================
   NHÓM 1: PHÂN QUYỀN NGƯỜI DÙNG
   Roles          : danh sách vai trò (Admin, Manager, Cashier, Staff)
   Permissions    : danh sách quyền hạn (vd: product.create, order.delete)
   RolePermissions: 1 Role có nhiều Permission (bảng n-n)
   Users          : tài khoản đăng nhập hệ thống
   ===================================================================== */

CREATE TABLE Roles (
    RoleId          INT IDENTITY(1,1) PRIMARY KEY,
    RoleName        NVARCHAR(50) NOT NULL UNIQUE,      -- Admin, Manager, Cashier, Staff
    Description     NVARCHAR(255) NULL,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE()
);
GO

CREATE TABLE Permissions (
    PermissionId    INT IDENTITY(1,1) PRIMARY KEY,
    PermissionCode  NVARCHAR(100) NOT NULL UNIQUE,     -- vd: product.create
    Description     NVARCHAR(255) NULL
);
GO

CREATE TABLE RolePermissions (
    RoleId          INT NOT NULL,
    PermissionId    INT NOT NULL,
    PRIMARY KEY (RoleId, PermissionId),
    CONSTRAINT FK_RolePermissions_Role FOREIGN KEY (RoleId) REFERENCES Roles(RoleId) ON DELETE CASCADE,
    CONSTRAINT FK_RolePermissions_Permission FOREIGN KEY (PermissionId) REFERENCES Permissions(PermissionId) ON DELETE CASCADE
);
GO

CREATE TABLE Users (
    UserId          INT IDENTITY(1,1) PRIMARY KEY,
    Username        NVARCHAR(50) NOT NULL UNIQUE,
    PasswordHash    NVARCHAR(255) NOT NULL,            -- luôn lưu password_hash(), KHÔNG lưu plaintext
    FullName        NVARCHAR(100) NOT NULL,
    Email           NVARCHAR(100) NULL UNIQUE,
    Phone           NVARCHAR(20) NULL,
    RoleId          INT NOT NULL,
    IsActive        BIT NOT NULL DEFAULT 1,            -- 1 = đang hoạt động, 0 = bị khóa
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    UpdatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Users_Role FOREIGN KEY (RoleId) REFERENCES Roles(RoleId)
);
GO
CREATE INDEX IX_Users_RoleId ON Users(RoleId);
GO

/* =====================================================================
   NHÓM 2: DANH MỤC & SẢN PHẨM
   ===================================================================== */

CREATE TABLE Categories (
    CategoryId      INT IDENTITY(1,1) PRIMARY KEY,
    CategoryName    NVARCHAR(100) NOT NULL,
    Description     NVARCHAR(255) NULL,
    IsActive        BIT NOT NULL DEFAULT 1,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE()
);
GO

CREATE TABLE Products (
    ProductId       INT IDENTITY(1,1) PRIMARY KEY,
    ProductName     NVARCHAR(150) NOT NULL,
    CategoryId      INT NOT NULL,
    SellPrice       DECIMAL(18,2) NOT NULL CHECK (SellPrice >= 0),   -- giá bán
    CostPrice       DECIMAL(18,2) NOT NULL DEFAULT 0 CHECK (CostPrice >= 0), -- giá vốn
    ImageUrl        NVARCHAR(255) NULL,
    Description     NVARCHAR(500) NULL,
    IsActive        BIT NOT NULL DEFAULT 1,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    UpdatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Products_Category FOREIGN KEY (CategoryId) REFERENCES Categories(CategoryId)
);
GO
CREATE INDEX IX_Products_CategoryId ON Products(CategoryId);
CREATE INDEX IX_Products_Name ON Products(ProductName);
GO

/* =====================================================================
   NHÓM 3: BÀN
   ===================================================================== */

CREATE TABLE TableStatuses (
    TableStatusId   INT IDENTITY(1,1) PRIMARY KEY,
    StatusCode      NVARCHAR(30) NOT NULL UNIQUE,   -- EMPTY, IN_USE, RESERVED, CLEANING
    StatusName      NVARCHAR(50) NOT NULL           -- Trống, Đang sử dụng, Đã đặt, Đang dọn
);
GO

CREATE TABLE Tables (
    TableId         INT IDENTITY(1,1) PRIMARY KEY,
    TableName       NVARCHAR(50) NOT NULL,
    Capacity        INT NOT NULL DEFAULT 4 CHECK (Capacity > 0),
    TableStatusId   INT NOT NULL,
    IsActive        BIT NOT NULL DEFAULT 1,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Tables_Status FOREIGN KEY (TableStatusId) REFERENCES TableStatuses(TableStatusId)
);
GO

/* =====================================================================
   NHÓM 4: KHÁCH HÀNG
   ===================================================================== */

CREATE TABLE Customers (
    CustomerId      INT IDENTITY(1,1) PRIMARY KEY,
    FullName        NVARCHAR(100) NOT NULL,
    Phone           NVARCHAR(20) NULL UNIQUE,
    Email           NVARCHAR(100) NULL,
    LoyaltyPoints   INT NOT NULL DEFAULT 0,          -- điểm tích lũy
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE()
);
GO

/* =====================================================================
   NHÓM 5: NHÂN VIÊN (tách riêng khỏi Users để lưu thông tin nghiệp vụ)
   ===================================================================== */

CREATE TABLE Employees (
    EmployeeId      INT IDENTITY(1,1) PRIMARY KEY,
    UserId          INT NOT NULL UNIQUE,             -- liên kết 1-1 với tài khoản đăng nhập
    Position        NVARCHAR(100) NULL,               -- chức danh
    Salary          DECIMAL(18,2) NULL,
    HireDate        DATE NULL,
    Address         NVARCHAR(255) NULL,
    CONSTRAINT FK_Employees_User FOREIGN KEY (UserId) REFERENCES Users(UserId) ON DELETE CASCADE
);
GO

/* =====================================================================
   NHÓM 6: ĐƠN HÀNG & THANH TOÁN
   ===================================================================== */

CREATE TABLE Discounts (
    DiscountId      INT IDENTITY(1,1) PRIMARY KEY,
    DiscountCode    NVARCHAR(50) NOT NULL UNIQUE,
    DiscountType    NVARCHAR(20) NOT NULL CHECK (DiscountType IN ('PERCENT','AMOUNT')),
    DiscountValue   DECIMAL(18,2) NOT NULL CHECK (DiscountValue >= 0),
    IsActive        BIT NOT NULL DEFAULT 1,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE()
);
GO

CREATE TABLE Orders (
    OrderId         INT IDENTITY(1,1) PRIMARY KEY,
    OrderCode       NVARCHAR(30) NOT NULL UNIQUE,     -- vd: ORD202608220001
    TableId         INT NULL,                          -- NULL nếu là đơn mang về
    EmployeeId      INT NOT NULL,                       -- nhân viên tạo đơn
    CustomerId      INT NULL,
    DiscountId      INT NULL,
    SubTotal        DECIMAL(18,2) NOT NULL DEFAULT 0,
    DiscountAmount  DECIMAL(18,2) NOT NULL DEFAULT 0,
    TotalAmount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    OrderStatus     NVARCHAR(20) NOT NULL DEFAULT 'PENDING'
                    CHECK (OrderStatus IN ('PENDING','PAID','CANCELLED')),
    Note            NVARCHAR(500) NULL,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    UpdatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Orders_Table FOREIGN KEY (TableId) REFERENCES Tables(TableId),
    CONSTRAINT FK_Orders_Employee FOREIGN KEY (EmployeeId) REFERENCES Employees(EmployeeId),
    CONSTRAINT FK_Orders_Customer FOREIGN KEY (CustomerId) REFERENCES Customers(CustomerId),
    CONSTRAINT FK_Orders_Discount FOREIGN KEY (DiscountId) REFERENCES Discounts(DiscountId)
);
GO
CREATE INDEX IX_Orders_CreatedAt ON Orders(CreatedAt);
CREATE INDEX IX_Orders_Status ON Orders(OrderStatus);
GO

CREATE TABLE OrderDetails (
    OrderDetailId   INT IDENTITY(1,1) PRIMARY KEY,
    OrderId         INT NOT NULL,
    ProductId       INT NOT NULL,
    Quantity        INT NOT NULL CHECK (Quantity > 0),
    UnitPrice       DECIMAL(18,2) NOT NULL,           -- giá tại thời điểm bán (không đổi dù giá SP sau này đổi)
    Note            NVARCHAR(255) NULL,               -- ghi chú món (vd: ít đá, không đường)
    CONSTRAINT FK_OrderDetails_Order FOREIGN KEY (OrderId) REFERENCES Orders(OrderId) ON DELETE CASCADE,
    CONSTRAINT FK_OrderDetails_Product FOREIGN KEY (ProductId) REFERENCES Products(ProductId)
);
GO
CREATE INDEX IX_OrderDetails_OrderId ON OrderDetails(OrderId);
GO

CREATE TABLE Payments (
    PaymentId       INT IDENTITY(1,1) PRIMARY KEY,
    OrderId         INT NOT NULL,
    PaymentMethod   NVARCHAR(30) NOT NULL CHECK (PaymentMethod IN ('CASH','CARD','TRANSFER','MOMO','VNPAY')),
    AmountPaid      DECIMAL(18,2) NOT NULL,
    PaidAt          DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Payments_Order FOREIGN KEY (OrderId) REFERENCES Orders(OrderId) ON DELETE CASCADE
);
GO

/* =====================================================================
   NHÓM 7: KHO & NHÀ CUNG CẤP
   ===================================================================== */

CREATE TABLE Suppliers (
    SupplierId      INT IDENTITY(1,1) PRIMARY KEY,
    SupplierName    NVARCHAR(150) NOT NULL,
    ContactName     NVARCHAR(100) NULL,
    Phone           NVARCHAR(20) NULL,
    Email           NVARCHAR(100) NULL,
    Address         NVARCHAR(255) NULL,
    IsActive        BIT NOT NULL DEFAULT 1,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE()
);
GO

CREATE TABLE Ingredients (
    IngredientId    INT IDENTITY(1,1) PRIMARY KEY,
    IngredientName  NVARCHAR(150) NOT NULL,
    Unit            NVARCHAR(30) NOT NULL,            -- kg, lít, gói...
    QuantityInStock DECIMAL(18,2) NOT NULL DEFAULT 0,
    LowStockThreshold DECIMAL(18,2) NOT NULL DEFAULT 0, -- ngưỡng cảnh báo tồn kho thấp
    SupplierId      INT NULL,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_Ingredients_Supplier FOREIGN KEY (SupplierId) REFERENCES Suppliers(SupplierId)
);
GO

CREATE TABLE InventoryTransactions (
    TransactionId   INT IDENTITY(1,1) PRIMARY KEY,
    IngredientId    INT NOT NULL,
    TransactionType NVARCHAR(20) NOT NULL CHECK (TransactionType IN ('IMPORT','EXPORT','ADJUST')),
    Quantity        DECIMAL(18,2) NOT NULL,
    Note            NVARCHAR(255) NULL,
    EmployeeId      INT NULL,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_InvTrans_Ingredient FOREIGN KEY (IngredientId) REFERENCES Ingredients(IngredientId),
    CONSTRAINT FK_InvTrans_Employee FOREIGN KEY (EmployeeId) REFERENCES Employees(EmployeeId)
);
GO

/* =====================================================================
   NHÓM 8: NHẬT KÝ HỆ THỐNG
   ===================================================================== */

CREATE TABLE ActionLogs (
    LogId           INT IDENTITY(1,1) PRIMARY KEY,
    UserId          INT NULL,
    ActionType      NVARCHAR(50) NOT NULL,   -- vd: CREATE_PRODUCT, DELETE_ORDER
    Description     NVARCHAR(500) NULL,
    CreatedAt       DATETIME NOT NULL DEFAULT GETDATE(),
    CONSTRAINT FK_ActionLogs_User FOREIGN KEY (UserId) REFERENCES Users(UserId)
);
GO

/* =====================================================================
   NHÓM 9: CÀI ĐẶT HỆ THỐNG
   Bảng bổ sung so với danh sách gốc (mục 19), cần thiết để trang Cài đặt
   (Giai đoạn 17) có nơi lưu thông tin quán/cấu hình. Thiết kế dạng
   key-value đơn giản, dễ mở rộng thêm cấu hình mới mà không cần ALTER TABLE.
   ===================================================================== */

CREATE TABLE Settings (
    SettingKey      NVARCHAR(50) NOT NULL PRIMARY KEY,   -- vd: ShopName, ShopAddress
    SettingValue    NVARCHAR(500) NULL,
    UpdatedAt       DATETIME NOT NULL DEFAULT GETDATE()
);
GO

PRINT 'Tạo database CoffeeManagerDB thành công!';
GO
