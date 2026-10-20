<?php
/**
 * FILE: backend/api/employees/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách nhân viên (kết hợp thông tin tài khoản Users + hồ
 *    sơ Employees + vai trò Roles). Hỗ trợ tìm kiếm theo tên, lọc theo
 *    vai trò, phân trang.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/employees.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET ?search=...&roleId=...&page=...) --> list.php
 *    --> SQL Server (Users JOIN Employees JOIN Roles) --> trả JSON --> render bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager']); // chỉ quản lý mới xem được danh sách nhân viên

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

$pdo = getDbConnection();

$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleId   = isset($_GET['roleId']) && $_GET['roleId'] !== '' ? (int)$_GET['roleId'] : null;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$pageSize = isset($_GET['pageSize']) ? max(1, min(100, (int)$_GET['pageSize'])) : 10;
$offset   = ($page - 1) * $pageSize;

$where = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(u.FullName LIKE :search OR u.Username LIKE :search2)';
    $params['search'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}
if ($roleId !== null) {
    $where[] = 'u.RoleId = :roleId';
    $params['roleId'] = $roleId;
}
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS Total FROM Users u INNER JOIN Employees e ON u.UserId = e.UserId WHERE $whereSql
");
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetch()['Total'];

$sql = "
    SELECT
        e.EmployeeId, u.UserId, u.Username, u.FullName, u.Email, u.Phone, u.IsActive,
        r.RoleId, r.RoleName,
        e.Position, e.Salary, e.HireDate, e.Address
    FROM Employees e
    INNER JOIN Users u ON e.UserId = u.UserId
    INNER JOIN Roles r ON u.RoleId = r.RoleId
    WHERE $whereSql
    ORDER BY u.CreatedAt DESC
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
], 'Lấy danh sách nhân viên thành công');
