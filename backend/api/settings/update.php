<?php
/**
 * FILE: backend/api/settings/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật cài đặt hệ thống (tên quán, địa chỉ, SĐT, logo...). Chỉ
 *    Admin mới được thay đổi cấu hình toàn hệ thống.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/settings.js (form "Thông tin quán")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    settings.js (fetch PUT {ShopName, ShopAddress, ShopPhone, ShopLogo})
 *    --> update.php --> UPDATE từng dòng trong bảng Settings (key-value)
 *    --> trả JSON --> settings.js hiện thông báo thành công
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();

// Chỉ cho phép cập nhật những key đã biết trước (whitelist), tránh việc
// client gửi lên key lạ rồi ghi bừa vào bảng Settings
$allowedKeys = ['ShopName', 'ShopAddress', 'ShopPhone', 'ShopLogo', 'LoyaltyPointsPer10k'];

$pdo = getDbConnection();
$stmt = $pdo->prepare("
    UPDATE Settings SET SettingValue = :value, UpdatedAt = GETDATE() WHERE SettingKey = :key
");

foreach ($allowedKeys as $key) {
    if (isset($input[$key])) {
        $stmt->execute(['value' => sanitizeString($input[$key]), 'key' => $key]);
    }
}

jsonSuccess(null, 'Cập nhật cài đặt thành công');
