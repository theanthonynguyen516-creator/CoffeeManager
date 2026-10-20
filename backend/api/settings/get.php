<?php
/**
 * FILE: backend/api/settings/get.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về toàn bộ cài đặt hệ thống hiện tại (tên quán, địa chỉ, SĐT,
 *    logo...) dưới dạng object key-value, dễ dùng ở frontend.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/settings.js
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET) --> get.php --> SQL Server (bảng Settings)
 *    --> trả JSON {ShopName: "...", ShopAddress: "...",...} --> đổ vào form
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

requireLogin(); // mọi nhân viên đều xem được thông tin quán (vd: hiển thị trên hóa đơn)

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

$pdo = getDbConnection();
$stmt = $pdo->query("SELECT SettingKey, SettingValue FROM Settings");

// Chuyển từ dạng bảng (nhiều dòng) sang object key-value cho dễ dùng ở JS
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['SettingKey']] = $row['SettingValue'];
}

jsonSuccess($settings, 'Lấy cài đặt thành công');
