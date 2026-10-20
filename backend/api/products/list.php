<?php
/**
 * FILE: backend/api/products/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách sản phẩm, hỗ trợ tìm kiếm theo tên, lọc theo danh
 *    mục, và phân trang.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/products.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET kèm query string ?search=...&categoryId=...&page=...)
 *    --> list.php --> SQL Server (bảng Products JOIN Categories)
 *    --> trả JSON {items, totalItems, page, pageSize} --> products.js render bảng
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

// Đọc tham số từ query string (?search=ca+phe&categoryId=1&page=1&pageSize=10)
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryId = isset($_GET['categoryId']) && $_GET['categoryId'] !== '' ? (int)$_GET['categoryId'] : null;
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize   = isset($_GET['pageSize']) ? max(1, min(100, (int)$_GET['pageSize'])) : 10;
$offset     = ($page - 1) * $pageSize;

// Xây dựng điều kiện WHERE động, nhưng vẫn dùng Prepared Statement (an toàn)
$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = 'p.ProductName LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if ($categoryId !== null) {
    $where[] = 'p.CategoryId = :categoryId';
    $params['categoryId'] = $categoryId;
}
$whereSql = implode(' AND ', $where);

// Đếm tổng số bản ghi (phục vụ phân trang)
$countStmt = $pdo->prepare("SELECT COUNT(*) AS Total FROM Products p WHERE $whereSql");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetch()['Total'];

// Lấy dữ liệu trang hiện tại (dùng OFFSET/FETCH - chuẩn phân trang của SQL Server)
$sql = "
    SELECT p.ProductId, p.ProductName, p.CategoryId, c.CategoryName,
           p.SellPrice, p.CostPrice, p.ImageUrl, p.Description, p.IsActive
    FROM Products p
    INNER JOIN Categories c ON p.CategoryId = c.CategoryId
    WHERE $whereSql
    ORDER BY p.ProductId DESC
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
], 'Lấy danh sách sản phẩm thành công');
