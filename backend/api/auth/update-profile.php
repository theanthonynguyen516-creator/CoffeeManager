<?php
/**
 * FILE: backend/api/auth/update-profile.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cho phép người dùng đang đăng nhập tự cập nhật thông tin cá nhân
 *    của chính mình (họ tên, email, SĐT). KHÔNG cho đổi username/vai trò
 *    ở đây (việc đó thuộc quyền Admin ở trang Nhân viên).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/settings.js (form "Tài khoản cá nhân")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    settings.js (fetch PUT {fullName, email, phone})
 *    --> update-profile.php --> UPDATE Users WHERE UserId = (chính mình)
 *    --> trả JSON --> settings.js cập nhật lại tên hiển thị trên Header
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['fullName']);
if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    UPDATE Users SET FullName = :fullName, Email = :email, Phone = :phone, UpdatedAt = GETDATE()
    WHERE UserId = :userId
");
$stmt->execute([
    'fullName' => sanitizeString($input['fullName']),
    'email'    => !empty($input['email']) ? sanitizeString($input['email']) : null,
    'phone'    => !empty($input['phone']) ? sanitizeString($input['phone']) : null,
    'userId'   => $user['UserId'],
]);

// Cập nhật lại session để Header hiển thị đúng tên mới ngay lập tức
$_SESSION['full_name'] = sanitizeString($input['fullName']);

jsonSuccess(null, 'Cập nhật thông tin cá nhân thành công');
