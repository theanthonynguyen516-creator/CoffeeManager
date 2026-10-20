<?php
/**
 * FILE: backend/api/employees/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Tạo 1 nhân viên mới: đồng thời tạo tài khoản đăng nhập (bảng Users)
 *    VÀ hồ sơ nhân viên (bảng Employees), vì 2 bảng này liên kết 1-1.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/employees.js (form "Thêm nhân viên")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    employees.js (fetch POST {username, password, fullName, email, phone,
 *                   roleId, position, salary, hireDate, address})
 *    --> create.php --> validate --> password_hash() --> INSERT Users
 *        --> INSERT Employees (cùng 1 TRANSACTION)
 *    --> trả JSON --> employees.js thêm dòng mới vào bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin']); // chỉ Admin mới được tạo tài khoản nhân viên mới

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['username', 'password', 'fullName', 'roleId']);

if (isset($input['password']) && strlen($input['password']) < 6) {
    $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
}
if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$username = sanitizeString($input['username']);

$checkUsername = $pdo->prepare("SELECT UserId FROM Users WHERE Username = :username");
$checkUsername->execute(['username' => $username]);
if ($checkUsername->fetch()) {
    jsonError('Tên đăng nhập đã tồn tại', 400, ['username' => 'Trùng tên đăng nhập']);
}

$checkRole = $pdo->prepare("SELECT RoleId FROM Roles WHERE RoleId = :id");
$checkRole->execute(['id' => (int)$input['roleId']]);
if (!$checkRole->fetch()) {
    jsonError('Vai trò không hợp lệ', 400);
}

try {
    $pdo->beginTransaction();

    // 1. Tạo tài khoản đăng nhập (Users) - mật khẩu luôn được băm bằng password_hash()
    $userStmt = $pdo->prepare("
        INSERT INTO Users (Username, PasswordHash, FullName, Email, Phone, RoleId, IsActive)
        OUTPUT INSERTED.UserId
        VALUES (:username, :passwordHash, :fullName, :email, :phone, :roleId, 1)
    ");
    $userStmt->execute([
        'username'     => $username,
        'passwordHash' => password_hash($input['password'], PASSWORD_DEFAULT),
        'fullName'     => sanitizeString($input['fullName']),
        'email'        => !empty($input['email']) ? sanitizeString($input['email']) : null,
        'phone'        => !empty($input['phone']) ? sanitizeString($input['phone']) : null,
        'roleId'       => (int)$input['roleId'],
    ]);
    $newUserId = $userStmt->fetch()['UserId'];

    // 2. Tạo hồ sơ nhân viên (Employees) liên kết 1-1 với tài khoản vừa tạo
    $empStmt = $pdo->prepare("
        INSERT INTO Employees (UserId, Position, Salary, HireDate, Address)
        OUTPUT INSERTED.EmployeeId
        VALUES (:userId, :position, :salary, :hireDate, :address)
    ");
    $empStmt->execute([
        'userId'   => $newUserId,
        'position' => !empty($input['position']) ? sanitizeString($input['position']) : null,
        'salary'   => isset($input['salary']) ? (float)$input['salary'] : null,
        'hireDate' => !empty($input['hireDate']) ? $input['hireDate'] : date('Y-m-d'),
        'address'  => !empty($input['address']) ? sanitizeString($input['address']) : null,
    ]);
    $newEmployeeId = $empStmt->fetch()['EmployeeId'];

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Tạo nhân viên thất bại, vui lòng thử lại', 500);
}

jsonSuccess(['employeeId' => $newEmployeeId, 'userId' => $newUserId], 'Thêm nhân viên thành công', 201);
