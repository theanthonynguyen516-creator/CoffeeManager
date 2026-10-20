<?php
/**
 * FILE: backend/api/auth/change-password.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cho phép người dùng đang đăng nhập tự đổi mật khẩu. Bắt buộc phải
 *    nhập đúng mật khẩu HIỆN TẠI trước khi đổi (bảo mật cơ bản, tránh
 *    trường hợp ai đó ngồi vào máy đã đăng nhập sẵn rồi đổi mật khẩu người khác).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/settings.js (form "Đổi mật khẩu")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    settings.js (fetch PUT {currentPassword, newPassword})
 *    --> change-password.php --> password_verify() mật khẩu cũ
 *        --> nếu đúng: password_hash() mật khẩu mới --> UPDATE Users
 *    --> trả JSON --> settings.js hiện thông báo thành công
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
$errors = validateRequired($input, ['currentPassword', 'newPassword']);
if (isset($input['newPassword']) && strlen($input['newPassword']) < 6) {
    $errors['newPassword'] = 'Mật khẩu mới phải có ít nhất 6 ký tự';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("SELECT PasswordHash FROM Users WHERE UserId = :userId");
$stmt->execute(['userId' => $user['UserId']]);
$row = $stmt->fetch();

if (!$row || !password_verify($input['currentPassword'], $row['PasswordHash'])) {
    jsonError('Mật khẩu hiện tại không đúng', 400, ['currentPassword' => 'Mật khẩu không đúng']);
}

$newHash = password_hash($input['newPassword'], PASSWORD_DEFAULT);
$pdo->prepare("UPDATE Users SET PasswordHash = :hash, UpdatedAt = GETDATE() WHERE UserId = :userId")
    ->execute(['hash' => $newHash, 'userId' => $user['UserId']]);

jsonSuccess(null, 'Đổi mật khẩu thành công');
