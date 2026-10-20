<?php
/**
 * FILE: backend/api/employees/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 nhân viên. Nếu nhân viên đã từng tạo đơn hàng (Orders.EmployeeId),
 *    không xóa cứng (sẽ phá vỡ lịch sử đơn hàng) mà chỉ khóa tài khoản
 *    (IsActive = 0). Nếu chưa từng tạo đơn nào, xóa cứng cả Users lẫn Employees.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/employees.js (khi bấm nút "Xóa")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    employees.js (fetch DELETE {employeeId, userId})
 *    --> delete.php --> kiểm tra Orders liên quan --> DELETE hoặc khóa tài khoản
 *    --> trả JSON --> employees.js load lại bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận DELETE', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['employeeId', 'userId']);
if (!empty($errors)) {
    jsonError('Thiếu employeeId hoặc userId', 400, $errors);
}

$employeeId = (int)$input['employeeId'];
$userId = (int)$input['userId'];

if ($userId === (int)$user['UserId']) {
    jsonError('Bạn không thể tự xóa tài khoản của chính mình', 400);
}

$pdo = getDbConnection();

$checkOrders = $pdo->prepare("SELECT TOP 1 OrderId FROM Orders WHERE EmployeeId = :id");
$checkOrders->execute(['id' => $employeeId]);

if ($checkOrders->fetch()) {
    // Nhân viên đã từng bán hàng -> chỉ khóa tài khoản để giữ lịch sử đơn hàng
    $pdo->prepare("UPDATE Users SET IsActive = 0 WHERE UserId = :userId")->execute(['userId' => $userId]);
    jsonSuccess(['softDeleted' => true], 'Nhân viên đã từng tạo đơn hàng nên tài khoản chỉ được khóa lại để giữ lịch sử');
} else {
    try {
        $pdo->beginTransaction();
        // Xóa Employees trước (khóa ngoại tham chiếu tới Users), sau đó xóa Users
        $pdo->prepare("DELETE FROM Employees WHERE EmployeeId = :id")->execute(['id' => $employeeId]);
        $pdo->prepare("DELETE FROM Users WHERE UserId = :id")->execute(['id' => $userId]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonError('Xóa nhân viên thất bại, vui lòng thử lại', 500);
    }
    jsonSuccess(['softDeleted' => false], 'Xóa nhân viên thành công');
}
