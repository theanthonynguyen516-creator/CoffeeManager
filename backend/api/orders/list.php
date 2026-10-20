<?php
/**
 * FILE: backend/api/orders/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách đơn hàng, hỗ trợ tìm kiếm theo mã đơn, lọc theo
 *    khoảng ngày, lọc theo trạng thái, và phân trang.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/orders.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET kèm ?search=...&fromDate=...&toDate=...&status=...&page=...)
 *    --> list.php --> SQL Server (Orders JOIN Tables/Employees/Customers)
 *    --> trả JSON {items, totalItems, page, totalPages} --> orders.js render bảng
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
$fromDate = isset($_GET['fromDate']) && $_GET['fromDate'] !== '' ? $_GET['fromDate'] : null; // yyyy-mm-dd
$toDate   = isset($_GET['toDate']) && $_GET['toDate'] !== '' ? $_GET['toDate'] : null;
$status   = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = isset($_GET['pageSize']) ? max(1, min(100, (int)$_GET['pageSize'])) : 10;
$offset   = ($page - 1) * $pageSize;

$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = 'o.OrderCode LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if ($fromDate !== null) {
    $where[] = 'CAST(o.CreatedAt AS DATE) >= :fromDate';
    $params['fromDate'] = $fromDate;
}
if ($toDate !== null) {
    $where[] = 'CAST(o.CreatedAt AS DATE) <= :toDate';
    $params['toDate'] = $toDate;
}
if ($status !== null) {
    $where[] = 'o.OrderStatus = :status';
    $params['status'] = $status;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) AS Total FROM Orders o WHERE $whereSql");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetch()['Total'];

$sql = "
    SELECT
        o.OrderId, o.OrderCode, o.SubTotal, o.DiscountAmount, o.TotalAmount,
        o.OrderStatus, o.CreatedAt,
        t.TableName,
        u.FullName AS EmployeeName,
        c.FullName AS CustomerName
    FROM Orders o
    LEFT JOIN Tables t ON o.TableId = t.TableId
    LEFT JOIN Employees e ON o.EmployeeId = e.EmployeeId
    LEFT JOIN Users u ON e.UserId = u.UserId
    LEFT JOIN Customers c ON o.CustomerId = c.CustomerId
    WHERE $whereSql
    ORDER BY o.CreatedAt DESC
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
], 'Lấy danh sách đơn hàng thành công');
