<?php
/**
 * FILE: backend/api/suppliers/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách nhà cung cấp. Mặc định chỉ trả nhà cung cấp đang
 *    hoạt động (dùng cho dropdown ở trang Kho). Truyền thêm
 *    ?includeInactive=1 để lấy TẤT CẢ (dùng cho trang quản lý Nhà cung cấp).
 *    Hỗ trợ tìm kiếm theo tên qua ?search=...
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js (đổ vào dropdown "Nhà cung cấp")
 *    Được gọi từ: assets/js/suppliers.js (bảng quản lý nhà cung cấp)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET, kèm ?search=...&includeInactive=1) --> list.php
 *    --> SQL Server (bảng Suppliers) --> trả JSON --> JS đổ vào <select> hoặc render bảng
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
    $where[] = 'SupplierName LIKE :search';
    $params['search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT SupplierId, SupplierName, ContactName, Phone, Email, Address, IsActive,
           (SELECT COUNT(*) FROM Ingredients ing WHERE ing.SupplierId = Suppliers.SupplierId) AS IngredientCount
    FROM Suppliers
    WHERE $whereSql
    ORDER BY SupplierName ASC
");
$stmt->execute($params);

jsonSuccess($stmt->fetchAll(), 'Lấy danh sách nhà cung cấp thành công');
