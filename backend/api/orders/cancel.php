<?php
/**
 * FILE: backend/api/orders/cancel.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Hủy 1 đơn hàng (chuyển OrderStatus sang CANCELLED). Chỉ Admin/Manager
 *    mới được hủy đơn (vì đơn ở hệ thống này đã thanh toán ngay tại POS,
 *    hủy đơn ảnh hưởng tới doanh thu nên cần quyền cao hơn).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/orders.js (khi bấm nút "Hủy đơn")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    orders.js (fetch POST {orderId, reason})
 *    --> cancel.php --> kiểm tra trạng thái hiện tại --> UPDATE Orders
 *    --> trả JSON --> orders.js load lại danh sách đơn hàng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['orderId']);
if (!empty($errors)) {
    jsonError('Thiếu orderId', 400, $errors);
}

$pdo = getDbConnection();
$orderId = (int)$input['orderId'];

$stmt = $pdo->prepare("SELECT OrderId, OrderStatus FROM Orders WHERE OrderId = :id");
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    jsonError('Không tìm thấy đơn hàng', 404);
}
if ($order['OrderStatus'] === 'CANCELLED') {
    jsonError('Đơn hàng này đã bị hủy trước đó', 400);
}

$reason = isset($input['reason']) ? sanitizeString($input['reason']) : null;

$updateStmt = $pdo->prepare("
    UPDATE Orders
    SET OrderStatus = 'CANCELLED',
        Note = CASE WHEN :reason IS NOT NULL THEN CONCAT(ISNULL(Note, ''), ' [Hủy: ', :reason2, ']') ELSE Note END,
        UpdatedAt = GETDATE()
    WHERE OrderId = :id
");
$updateStmt->execute(['reason' => $reason, 'reason2' => $reason, 'id' => $orderId]);

jsonSuccess(null, 'Hủy đơn hàng thành công');
