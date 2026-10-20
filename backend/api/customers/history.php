<?php
/**
 * FILE: backend/api/customers/history.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về lịch sử mua hàng (danh sách đơn hàng) của 1 khách hàng cụ thể.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/customers.js (khi bấm nút "Lịch sử mua hàng")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    customers.js (fetch GET ?customerId=...) --> history.php
 *    --> SQL Server (Orders WHERE CustomerId = ...) --> trả JSON danh sách đơn
 *    --> customers.js render modal lịch sử mua hàng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

if (empty($_GET['customerId'])) {
    jsonError('Thiếu customerId', 400);
}

$customerId = (int)$_GET['customerId'];
$pdo = getDbConnection();

$stmt = $pdo->prepare("
    SELECT TOP 50
        o.OrderId, o.OrderCode, o.TotalAmount, o.OrderStatus, o.CreatedAt,
        t.TableName
    FROM Orders o
    LEFT JOIN Tables t ON o.TableId = t.TableId
    WHERE o.CustomerId = :id
    ORDER BY o.CreatedAt DESC
");
$stmt->execute(['id' => $customerId]);

jsonSuccess($stmt->fetchAll(), 'Lấy lịch sử mua hàng thành công');
