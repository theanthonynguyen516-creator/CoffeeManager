<?php
/**
 * FILE: backend/api/products/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 sản phẩm. Nếu sản phẩm ĐÃ từng xuất hiện trong đơn hàng
 *    (OrderDetails), việc xóa cứng sẽ làm hỏng dữ liệu lịch sử đơn hàng
 *    cũ -> thay vào đó chỉ chuyển IsActive = 0 (ẩn sản phẩm, gọi là
 *    "xóa mềm"). Nếu sản phẩm CHƯA từng bán, sẽ xóa cứng khỏi database.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/products.js (khi bấm nút "Xóa")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    products.js (fetch DELETE {productId})
 *    --> delete.php --> kiểm tra OrderDetails --> DELETE hoặc UPDATE IsActive
 *    --> trả JSON thành công --> products.js xóa dòng khỏi bảng (hoặc ẩn đi)
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
$errors = validateRequired($input, ['productId']);
if (!empty($errors)) {
    jsonError('Thiếu productId', 400, $errors);
}

$pdo = getDbConnection();
$productId = (int)$input['productId'];

// Kiểm tra sản phẩm có tồn tại không
$checkProduct = $pdo->prepare("SELECT ProductId FROM Products WHERE ProductId = :id");
$checkProduct->execute(['id' => $productId]);
if (!$checkProduct->fetch()) {
    jsonError('Sản phẩm không tồn tại', 404);
}

// Kiểm tra sản phẩm đã từng nằm trong đơn hàng nào chưa
$checkUsed = $pdo->prepare("SELECT TOP 1 OrderDetailId FROM OrderDetails WHERE ProductId = :id");
$checkUsed->execute(['id' => $productId]);
$isUsed = (bool)$checkUsed->fetch();

if ($isUsed) {
    // Xóa mềm: chỉ ẩn sản phẩm để bảo toàn dữ liệu lịch sử đơn hàng cũ
    $stmt = $pdo->prepare("UPDATE Products SET IsActive = 0, UpdatedAt = GETDATE() WHERE ProductId = :id");
    $stmt->execute(['id' => $productId]);
    jsonSuccess(['softDeleted' => true], 'Sản phẩm đã từng được bán nên chỉ được ẩn đi (không xóa vĩnh viễn) để giữ lịch sử đơn hàng');
} else {
    // Xóa cứng vì sản phẩm chưa từng được bán
    $stmt = $pdo->prepare("DELETE FROM Products WHERE ProductId = :id");
    $stmt->execute(['id' => $productId]);
    jsonSuccess(['softDeleted' => false], 'Xóa sản phẩm thành công');
}
