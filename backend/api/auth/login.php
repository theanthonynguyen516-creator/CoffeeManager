<?php
/**
 * FILE: backend/api/auth/login.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý đăng nhập: nhận username/password, kiểm tra trong database,
 *    nếu đúng thì tạo session cho người dùng.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/auth.js (trang đăng nhập)
 *    Sử dụng: backend/config/database.php, backend/utils/response.php,
 *             backend/utils/validation.php
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch POST {username, password})
 *    --> login.php --> SQL Server (kiểm tra Users)
 *    --> tạo Session --> trả JSON {success, user}
 *    --> JS lưu thông tin & chuyển hướng vào index.html
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['username', 'password']);
if (!empty($errors)) {
    jsonError('Vui lòng nhập đầy đủ thông tin', 400, $errors);
}

$username = sanitizeString($input['username']);
$password = (string)$input['password'];

$pdo = getDbConnection();

// Prepared statement để chống SQL Injection
$stmt = $pdo->prepare("
    SELECT u.UserId, u.Username, u.PasswordHash, u.FullName, u.IsActive,
           r.RoleId, r.RoleName
    FROM Users u
    INNER JOIN Roles r ON u.RoleId = r.RoleId
    WHERE u.Username = :username
");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    jsonError('Tên đăng nhập hoặc mật khẩu không đúng', 401);
}

if ((int)$user['IsActive'] !== 1) {
    jsonError('Tài khoản của bạn đã bị khóa', 403);
}

// So sánh mật khẩu bằng password_verify (KHÔNG BAO GIỜ so sánh plaintext)
if (!password_verify($password, $user['PasswordHash'])) {
    jsonError('Tên đăng nhập hoặc mật khẩu không đúng', 401);
}

// Đăng nhập thành công -> tạo session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_regenerate_id(true); // chống session fixation

$_SESSION['user_id']   = $user['UserId'];
$_SESSION['username']  = $user['Username'];
$_SESSION['role_id']   = $user['RoleId'];
$_SESSION['role_name'] = $user['RoleName'];
$_SESSION['full_name'] = $user['FullName'];

jsonSuccess([
    'userId'   => $user['UserId'],
    'username' => $user['Username'],
    'fullName' => $user['FullName'],
    'roleName' => $user['RoleName'],
], 'Đăng nhập thành công');
