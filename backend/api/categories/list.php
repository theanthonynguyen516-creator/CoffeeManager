<?php
/**
 * FILE: backend/api/categories/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách danh mục sản phẩm. Mặc định chỉ trả danh mục đang
 *    hoạt động (dùng cho dropdown lọc/form ở trang Sản phẩm). Truyền thêm
 *    ?includeInactive=1 để lấy TẤT CẢ (dùng cho trang quản lý Danh mục).
 *    Hỗ trợ tìm kiếm theo tên qua ?search=...
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/products.js (đổ vào dropdown lọc danh mục)
 *    Được gọi từ: assets/js/categories.js (bảng quản lý danh mục)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET, kèm ?search=...&includeInactive=1) --> list.php
 *    --> SQL Server (bảng Categories) --> trả JSON mảng danh mục
 *    --> JS đổ vào <select> hoặc render bảng
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

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$includeInactive = isset($_GET['includeInactive']) && $_GET['includeInactive'] === '1';

$where = $includeInactive ? ['1 = 1'] : ['IsActive = 1'];
$params = [];
if ($search !== '') {
    $where[] = 'CategoryName LIKE :search';
    $params['search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT CategoryId, CategoryName, Description, IsActive,
           (SELECT COUNT(*) FROM Products p WHERE p.CategoryId = Categories.CategoryId) AS ProductCount
    FROM Categories
    WHERE $whereSql
    ORDER BY CategoryName ASC
");
$stmt->execute($params);

jsonSuccess($stmt->fetchAll(), 'Lấy danh sách danh mục thành công');
