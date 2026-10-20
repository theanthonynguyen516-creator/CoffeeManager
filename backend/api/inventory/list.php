<?php
/**
 * FILE: backend/api/inventory/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách nguyên liệu kèm số lượng tồn kho hiện tại và cờ
 *    "sắp hết hàng" (IsLowStock) khi QuantityInStock <= LowStockThreshold.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET ?search=...) --> list.php --> SQL Server
 *    (Ingredients LEFT JOIN Suppliers) --> trả JSON --> render bảng kho
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

$where = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = 'i.IngredientName LIKE :search';
    $params['search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT
        i.IngredientId, i.IngredientName, i.Unit, i.QuantityInStock, i.LowStockThreshold,
        i.SupplierId, s.SupplierName,
        CASE WHEN i.QuantityInStock <= i.LowStockThreshold THEN 1 ELSE 0 END AS IsLowStock
    FROM Ingredients i
    LEFT JOIN Suppliers s ON i.SupplierId = s.SupplierId
    WHERE $whereSql
    ORDER BY IsLowStock DESC, i.IngredientName ASC
");
$stmt->execute($params);

jsonSuccess($stmt->fetchAll(), 'Lấy danh sách nguyên liệu thành công');
