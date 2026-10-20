<?php
/**
 * FILE: backend/api/dashboard/statistics.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Tổng hợp toàn bộ số liệu thống kê cần thiết cho trang Dashboard
 *    (doanh thu hôm nay, doanh thu tháng, số đơn, số khách hàng, số bàn,
 *    đơn hàng gần đây, sản phẩm bán chạy, doanh thu 7 ngày gần nhất).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/dashboard.js
 *    Sử dụng: backend/config/database.php, backend/middleware/auth.php,
 *             backend/utils/response.php
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET) --> statistics.php --> nhiều truy vấn SQL Server
 *    --> gom kết quả thành 1 object --> trả JSON --> dashboard.js vẽ giao diện
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

requireLogin(); // Dashboard yêu cầu phải đăng nhập

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

$pdo = getDbConnection();

/* ---------- 1. Doanh thu hôm nay & số đơn hôm nay ---------- */
$stmt = $pdo->query("
    SELECT ISNULL(SUM(TotalAmount), 0) AS Revenue, COUNT(*) AS OrderCount
    FROM Orders
    WHERE OrderStatus = 'PAID' AND CAST(CreatedAt AS DATE) = CAST(GETDATE() AS DATE)
");
$today = $stmt->fetch();

/* ---------- 2. Doanh thu tháng này ---------- */
$stmt = $pdo->query("
    SELECT ISNULL(SUM(TotalAmount), 0) AS Revenue
    FROM Orders
    WHERE OrderStatus = 'PAID'
      AND MONTH(CreatedAt) = MONTH(GETDATE())
      AND YEAR(CreatedAt) = YEAR(GETDATE())
");
$thisMonth = $stmt->fetch();

/* ---------- 3. Tổng số khách hàng & sản phẩm ---------- */
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) AS Total FROM Customers")->fetch()['Total'];
$totalProducts  = (int)$pdo->query("SELECT COUNT(*) AS Total FROM Products WHERE IsActive = 1")->fetch()['Total'];

/* ---------- 4. Số bàn đang sử dụng / trống ---------- */
$stmt = $pdo->query("
    SELECT ts.StatusCode, COUNT(*) AS Total
    FROM Tables t
    INNER JOIN TableStatuses ts ON t.TableStatusId = ts.TableStatusId
    WHERE t.IsActive = 1
    GROUP BY ts.StatusCode
");
$tableCounts = ['EMPTY' => 0, 'IN_USE' => 0, 'RESERVED' => 0, 'CLEANING' => 0];
foreach ($stmt->fetchAll() as $row) {
    $tableCounts[$row['StatusCode']] = (int)$row['Total'];
}

/* ---------- 5. Đơn hàng gần đây (10 đơn mới nhất) ---------- */
$stmt = $pdo->query("
    SELECT TOP 10
        o.OrderId, o.OrderCode, o.TotalAmount, o.OrderStatus, o.CreatedAt,
        t.TableName, e_u.FullName AS EmployeeName
    FROM Orders o
    LEFT JOIN Tables t ON o.TableId = t.TableId
    LEFT JOIN Employees e ON o.EmployeeId = e.EmployeeId
    LEFT JOIN Users e_u ON e.UserId = e_u.UserId
    ORDER BY o.CreatedAt DESC
");
$recentOrders = $stmt->fetchAll();

/* ---------- 6. Sản phẩm bán chạy (top 5 theo số lượng bán, trong 30 ngày) ---------- */
$stmt = $pdo->query("
    SELECT TOP 5
        p.ProductId, p.ProductName, p.ImageUrl,
        SUM(od.Quantity) AS TotalSold,
        SUM(od.Quantity * od.UnitPrice) AS TotalRevenue
    FROM OrderDetails od
    INNER JOIN Products p ON od.ProductId = p.ProductId
    INNER JOIN Orders o ON od.OrderId = o.OrderId
    WHERE o.OrderStatus = 'PAID' AND o.CreatedAt >= DATEADD(DAY, -30, GETDATE())
    GROUP BY p.ProductId, p.ProductName, p.ImageUrl
    ORDER BY TotalSold DESC
");
$bestSellingProducts = $stmt->fetchAll();

/* ---------- 7. Doanh thu 7 ngày gần nhất (để vẽ biểu đồ) ---------- */
$stmt = $pdo->query("
    SELECT
        CAST(CreatedAt AS DATE) AS OrderDate,
        SUM(TotalAmount) AS Revenue
    FROM Orders
    WHERE OrderStatus = 'PAID' AND CreatedAt >= DATEADD(DAY, -6, CAST(GETDATE() AS DATE))
    GROUP BY CAST(CreatedAt AS DATE)
    ORDER BY OrderDate ASC
");
$revenueByDayRaw = $stmt->fetchAll();

// Đảm bảo đủ 7 ngày liên tiếp, kể cả ngày không có doanh thu (revenue = 0)
$revenueChart = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i day"));
    $revenueChart[$date] = 0;
}
foreach ($revenueByDayRaw as $row) {
    $date = date('Y-m-d', strtotime($row['OrderDate']));
    if (isset($revenueChart[$date])) {
        $revenueChart[$date] = (float)$row['Revenue'];
    }
}

jsonSuccess([
    'todayRevenue'       => (float)$today['Revenue'],
    'todayOrderCount'    => (int)$today['OrderCount'],
    'monthRevenue'       => (float)$thisMonth['Revenue'],
    'totalCustomers'     => $totalCustomers,
    'totalProducts'      => $totalProducts,
    'tablesInUse'        => $tableCounts['IN_USE'],
    'tablesEmpty'        => $tableCounts['EMPTY'],
    'recentOrders'       => $recentOrders,
    'bestSellingProducts'=> $bestSellingProducts,
    'revenueChart'       => $revenueChart,
], 'Lấy thống kê dashboard thành công');
