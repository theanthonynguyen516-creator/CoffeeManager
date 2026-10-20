<?php
/**
 * FILE: backend/api/reports/summary.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Tổng hợp toàn bộ số liệu báo cáo trong 1 khoảng thời gian (fromDate
 *    -> toDate): tổng doanh thu, tổng số đơn, doanh thu theo từng ngày,
 *    sản phẩm bán chạy, doanh thu theo danh mục, doanh thu theo nhân
 *    viên, doanh thu theo phương thức thanh toán.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/reports.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET ?fromDate=...&toDate=...) --> summary.php
 *    --> nhiều truy vấn SQL Server (GROUP BY theo từng tiêu chí)
 *    --> gom thành 1 object JSON lớn --> reports.js vẽ các bảng/biểu đồ
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager']); // báo cáo doanh thu chỉ dành cho quản lý

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

// Mặc định lấy báo cáo 30 ngày gần nhất nếu không truyền khoảng ngày
$fromDate = !empty($_GET['fromDate']) ? $_GET['fromDate'] : date('Y-m-d', strtotime('-29 days'));
$toDate   = !empty($_GET['toDate']) ? $_GET['toDate'] : date('Y-m-d');

$pdo = getDbConnection();
$dateParams = ['fromDate' => $fromDate, 'toDate' => $toDate];
$dateCondition = "o.OrderStatus = 'PAID' AND CAST(o.CreatedAt AS DATE) BETWEEN :fromDate AND :toDate";

/* ---------- 1. Tổng quan: doanh thu, số đơn, giá trị đơn trung bình ---------- */
$stmt = $pdo->prepare("
    SELECT ISNULL(SUM(o.TotalAmount), 0) AS TotalRevenue, COUNT(*) AS TotalOrders
    FROM Orders o WHERE $dateCondition
");
$stmt->execute($dateParams);
$overview = $stmt->fetch();
$totalRevenue = (float)$overview['TotalRevenue'];
$totalOrders = (int)$overview['TotalOrders'];

/* ---------- 2. Doanh thu theo từng ngày (dữ liệu vẽ biểu đồ) ---------- */
$stmt = $pdo->prepare("
    SELECT CAST(o.CreatedAt AS DATE) AS OrderDate, SUM(o.TotalAmount) AS Revenue, COUNT(*) AS OrderCount
    FROM Orders o WHERE $dateCondition
    GROUP BY CAST(o.CreatedAt AS DATE)
    ORDER BY OrderDate ASC
");
$stmt->execute($dateParams);
$revenueByDay = $stmt->fetchAll();

/* ---------- 3. Sản phẩm bán chạy (top 10) ---------- */
$stmt = $pdo->prepare("
    SELECT p.ProductId, p.ProductName, SUM(od.Quantity) AS TotalSold, SUM(od.Quantity * od.UnitPrice) AS TotalRevenue
    FROM OrderDetails od
    INNER JOIN Products p ON od.ProductId = p.ProductId
    INNER JOIN Orders o ON od.OrderId = o.OrderId
    WHERE $dateCondition
    GROUP BY p.ProductId, p.ProductName
    ORDER BY TotalSold DESC
    OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY
");
$stmt->execute($dateParams);
$topProducts = $stmt->fetchAll();

/* ---------- 4. Doanh thu theo danh mục ---------- */
$stmt = $pdo->prepare("
    SELECT c.CategoryId, c.CategoryName, SUM(od.Quantity * od.UnitPrice) AS Revenue, SUM(od.Quantity) AS TotalSold
    FROM OrderDetails od
    INNER JOIN Products p ON od.ProductId = p.ProductId
    INNER JOIN Categories c ON p.CategoryId = c.CategoryId
    INNER JOIN Orders o ON od.OrderId = o.OrderId
    WHERE $dateCondition
    GROUP BY c.CategoryId, c.CategoryName
    ORDER BY Revenue DESC
");
$stmt->execute($dateParams);
$revenueByCategory = $stmt->fetchAll();

/* ---------- 5. Doanh thu theo nhân viên ---------- */
$stmt = $pdo->prepare("
    SELECT u.UserId, u.FullName, SUM(o.TotalAmount) AS Revenue, COUNT(*) AS OrderCount
    FROM Orders o
    INNER JOIN Employees e ON o.EmployeeId = e.EmployeeId
    INNER JOIN Users u ON e.UserId = u.UserId
    WHERE $dateCondition
    GROUP BY u.UserId, u.FullName
    ORDER BY Revenue DESC
");
$stmt->execute($dateParams);
$revenueByEmployee = $stmt->fetchAll();

/* ---------- 6. Doanh thu theo phương thức thanh toán ---------- */
$stmt = $pdo->prepare("
    SELECT pay.PaymentMethod, SUM(pay.AmountPaid) AS TotalAmount, COUNT(*) AS TransactionCount
    FROM Payments pay
    INNER JOIN Orders o ON pay.OrderId = o.OrderId
    WHERE $dateCondition
    GROUP BY pay.PaymentMethod
    ORDER BY TotalAmount DESC
");
$stmt->execute($dateParams);
$revenueByPaymentMethod = $stmt->fetchAll();

jsonSuccess([
    'fromDate'   => $fromDate,
    'toDate'     => $toDate,
    'totalRevenue' => $totalRevenue,
    'totalOrders'  => $totalOrders,
    'averageOrderValue' => $totalOrders > 0 ? round($totalRevenue / $totalOrders) : 0,
    'revenueByDay'          => $revenueByDay,
    'topProducts'           => $topProducts,
    'revenueByCategory'     => $revenueByCategory,
    'revenueByEmployee'     => $revenueByEmployee,
    'revenueByPaymentMethod'=> $revenueByPaymentMethod,
], 'Lấy báo cáo thành công');
