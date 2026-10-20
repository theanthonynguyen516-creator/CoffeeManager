<?php
/**
 * FILE: backend/api/employees/roles.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách 4 vai trò cố định trong hệ thống (Admin, Manager,
 *    Cashier, Staff) để đổ vào dropdown "Phân quyền" khi thêm/sửa nhân viên.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/employees.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET) --> roles.php --> SQL Server (bảng Roles)
 *    --> trả JSON --> employees.js đổ vào <select>
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

$pdo = getDbConnection();
$stmt = $pdo->query("SELECT RoleId, RoleName, Description FROM Roles ORDER BY RoleId ASC");

jsonSuccess($stmt->fetchAll(), 'Lấy danh sách vai trò thành công');
