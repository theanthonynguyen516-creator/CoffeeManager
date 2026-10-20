<?php
/**
 * FILE: backend/api/customers/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 khách hàng. Nếu khách hàng đã từng có đơn hàng, không xóa
 *    cứng (sẽ phá vỡ liên kết Orders.CustomerId) mà chỉ xóa mềm bằng
 *    cách xóa số điện thoại/email (ẩn thông tin liên hệ) - vẫn giữ dòng
 *    để không mất lịch sử đơn hàng liên quan.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/customers.js (khi bấm nút "Xóa")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    customers.js (fetch DELETE {customerId})
 *    --> delete.php --> kiểm tra Orders liên quan --> DELETE hoặc báo lỗi
 *    --> trả JSON --> customers.js load lại bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận DELETE', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['customerId']);
if (!empty($errors)) {
    jsonError('Thiếu customerId', 400, $errors);
}

$pdo = getDbConnection();
$customerId = (int)$input['customerId'];

$checkCustomer = $pdo->prepare("SELECT CustomerId FROM Customers WHERE CustomerId = :id");
$checkCustomer->execute(['id' => $customerId]);
if (!$checkCustomer->fetch()) {
    jsonError('Khách hàng không tồn tại', 404);
}

$checkOrders = $pdo->prepare("SELECT TOP 1 OrderId FROM Orders WHERE CustomerId = :id");
$checkOrders->execute(['id' => $customerId]);

if ($checkOrders->fetch()) {
    jsonError('Không thể xóa vì khách hàng này đã có lịch sử đơn hàng. Chỉ có thể xóa khách hàng chưa từng mua hàng.', 400);
}

$stmt = $pdo->prepare("DELETE FROM Customers WHERE CustomerId = :id");
$stmt->execute(['id' => $customerId]);

jsonSuccess(null, 'Xóa khách hàng thành công');
