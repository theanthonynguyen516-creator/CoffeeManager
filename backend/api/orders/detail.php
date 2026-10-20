<?php
/**
 * FILE: backend/api/orders/detail.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về thông tin đầy đủ của 1 đơn hàng: thông tin chung, danh sách
 *    món (OrderDetails), và thông tin thanh toán (Payments). Dùng để
 *    hiển thị modal "Xem chi tiết" và in hóa đơn.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/orders.js (khi bấm nút "Xem chi tiết" hoặc "In hóa đơn")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET ?orderId=...) --> detail.php --> SQL Server
 *    (Orders + OrderDetails + Products + Payments) --> trả JSON đầy đủ
 *    --> orders.js render modal chi tiết / hóa đơn in
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

if (empty($_GET['orderId'])) {
    jsonError('Thiếu orderId', 400);
}

$orderId = (int)$_GET['orderId'];
$pdo = getDbConnection();

// 1. Thông tin chung của đơn hàng
$orderStmt = $pdo->prepare("
    SELECT
        o.OrderId, o.OrderCode, o.SubTotal, o.DiscountAmount, o.TotalAmount,
        o.OrderStatus, o.Note, o.CreatedAt,
        t.TableName,
        u.FullName AS EmployeeName,
        c.FullName AS CustomerName, c.Phone AS CustomerPhone
    FROM Orders o
    LEFT JOIN Tables t ON o.TableId = t.TableId
    LEFT JOIN Employees e ON o.EmployeeId = e.EmployeeId
    LEFT JOIN Users u ON e.UserId = u.UserId
    LEFT JOIN Customers c ON o.CustomerId = c.CustomerId
    WHERE o.OrderId = :id
");
$orderStmt->execute(['id' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    jsonError('Không tìm thấy đơn hàng', 404);
}

// 2. Danh sách món trong đơn
$detailsStmt = $pdo->prepare("
    SELECT od.OrderDetailId, od.ProductId, p.ProductName, od.Quantity, od.UnitPrice, od.Note,
           (od.Quantity * od.UnitPrice) AS LineTotal
    FROM OrderDetails od
    INNER JOIN Products p ON od.ProductId = p.ProductId
    WHERE od.OrderId = :id
");
$detailsStmt->execute(['id' => $orderId]);
$order['items'] = $detailsStmt->fetchAll();

// 3. Thông tin thanh toán
$paymentStmt = $pdo->prepare("
    SELECT PaymentId, PaymentMethod, AmountPaid, PaidAt
    FROM Payments WHERE OrderId = :id
");
$paymentStmt->execute(['id' => $orderId]);
$order['payments'] = $paymentStmt->fetchAll();

jsonSuccess($order, 'Lấy chi tiết đơn hàng thành công');
