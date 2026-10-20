<?php
/**
 * FILE: backend/api/customers/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách khách hàng, hỗ trợ tìm kiếm theo tên/SĐT, phân trang.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/customers.js
 *    Sẽ được assets/js/pos.js dùng lại (mở rộng sau) để tìm khách hàng khi bán hàng.
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET ?search=...&page=...) --> list.php --> SQL Server
 *    (bảng Customers) --> trả JSON {items, totalItems,...} --> render bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

$pdo = getDbConnection();

$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = isset($_GET['pageSize']) ? max(1, min(100, (int)$_GET['pageSize'])) : 10;
$offset   = ($page - 1) * $pageSize;

$where = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(FullName LIKE :search OR Phone LIKE :search2)';
    $params['search'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) AS Total FROM Customers WHERE $whereSql");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetch()['Total'];

$sql = "
    SELECT
        c.CustomerId, c.FullName, c.Phone, c.Email, c.LoyaltyPoints, c.CreatedAt,
        (SELECT COUNT(*) FROM Orders o WHERE o.CustomerId = c.CustomerId AND o.OrderStatus = 'PAID') AS TotalOrders,
        (SELECT ISNULL(SUM(o.TotalAmount), 0) FROM Orders o WHERE o.CustomerId = c.CustomerId AND o.OrderStatus = 'PAID') AS TotalSpent
    FROM Customers c
    WHERE $whereSql
    ORDER BY c.CreatedAt DESC
    OFFSET :offset ROWS FETCH NEXT :pageSize ROWS ONLY
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':pageSize', $pageSize, PDO::PARAM_INT);
$stmt->execute();

jsonSuccess([
    'items'      => $stmt->fetchAll(),
    'totalItems' => $totalItems,
    'page'       => $page,
    'pageSize'   => $pageSize,
    'totalPages' => (int)ceil($totalItems / $pageSize),
], 'Lấy danh sách khách hàng thành công');
