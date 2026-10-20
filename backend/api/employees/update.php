<?php
/**
 * FILE: backend/api/employees/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật thông tin nhân viên: hồ sơ (chức danh, lương, địa chỉ),
 *    tài khoản (họ tên, email, SĐT, vai trò), và trạng thái Khóa/Mở
 *    tài khoản (IsActive). Dùng chung 1 API cho cả "sửa thông tin" lẫn
 *    "khóa tài khoản" (chỉ cần gửi isActive = false).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/employees.js (form sửa, hoặc nút khóa/mở tài khoản)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    employees.js (fetch PUT {employeeId, userId, fullName, roleId, isActive,...})
 *    --> update.php --> UPDATE Users + UPDATE Employees (1 TRANSACTION)
 *    --> trả JSON --> employees.js load lại bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin']); // chỉ Admin mới được sửa/khóa nhân viên

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['employeeId', 'userId', 'fullName', 'roleId']);
if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$employeeId = (int)$input['employeeId'];
$userId = (int)$input['userId'];

// Không cho tự khóa chính tài khoản đang đăng nhập (tránh tự khóa mình ra khỏi hệ thống)
if ($userId === (int)$user['UserId'] && isset($input['isActive']) && !$input['isActive']) {
    jsonError('Bạn không thể tự khóa tài khoản của chính mình', 400);
}

try {
    $pdo->beginTransaction();

    $userStmt = $pdo->prepare("
        UPDATE Users
        SET FullName = :fullName, Email = :email, Phone = :phone,
            RoleId = :roleId, IsActive = :isActive, UpdatedAt = GETDATE()
        WHERE UserId = :userId
    ");
    $userStmt->execute([
        'fullName' => sanitizeString($input['fullName']),
        'email'    => !empty($input['email']) ? sanitizeString($input['email']) : null,
        'phone'    => !empty($input['phone']) ? sanitizeString($input['phone']) : null,
        'roleId'   => (int)$input['roleId'],
        'isActive' => isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1,
        'userId'   => $userId,
    ]);

    $empStmt = $pdo->prepare("
        UPDATE Employees
        SET Position = :position, Salary = :salary, HireDate = :hireDate, Address = :address
        WHERE EmployeeId = :employeeId
    ");
    $empStmt->execute([
        'position'   => !empty($input['position']) ? sanitizeString($input['position']) : null,
        'salary'     => isset($input['salary']) ? (float)$input['salary'] : null,
        'hireDate'   => !empty($input['hireDate']) ? $input['hireDate'] : null,
        'address'    => !empty($input['address']) ? sanitizeString($input['address']) : null,
        'employeeId' => $employeeId,
    ]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Cập nhật nhân viên thất bại, vui lòng thử lại', 500);
}

jsonSuccess(null, 'Cập nhật nhân viên thành công');
