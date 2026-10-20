<?php
/**
 * FILE: backend/api/tables/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 bàn. Chỉ cho phép xóa khi bàn đang ở trạng thái "Trống"
 *    (không có đơn hàng đang mở), để tránh mất dữ liệu đơn hàng đang xử lý.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/tables.js (khi bấm nút "Xóa bàn")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    tables.js (fetch DELETE {tableId})
 *    --> delete.php --> kiểm tra trạng thái --> DELETE hoặc báo lỗi
 *    --> trả JSON --> tables.js load lại sơ đồ bàn
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
$errors = validateRequired($input, ['tableId']);
if (!empty($errors)) {
    jsonError('Thiếu tableId', 400, $errors);
}

$pdo = getDbConnection();
$tableId = (int)$input['tableId'];

$stmt = $pdo->prepare("
    SELECT t.TableId, ts.StatusCode
    FROM Tables t
    INNER JOIN TableStatuses ts ON t.TableStatusId = ts.TableStatusId
    WHERE t.TableId = :id
");
$stmt->execute(['id' => $tableId]);
$table = $stmt->fetch();

if (!$table) {
    jsonError('Bàn không tồn tại', 404);
}
if ($table['StatusCode'] !== 'EMPTY') {
    jsonError('Chỉ có thể xóa bàn đang ở trạng thái "Trống"', 400);
}

// Nếu bàn đã từng có trong lịch sử đơn hàng (Orders.TableId), chỉ ẩn đi (xóa mềm)
$checkUsed = $pdo->prepare("SELECT TOP 1 OrderId FROM Orders WHERE TableId = :id");
$checkUsed->execute(['id' => $tableId]);

if ($checkUsed->fetch()) {
    $pdo->prepare("UPDATE Tables SET IsActive = 0 WHERE TableId = :id")->execute(['id' => $tableId]);
    jsonSuccess(['softDeleted' => true], 'Bàn đã từng có đơn hàng nên chỉ được ẩn đi để giữ lịch sử');
} else {
    $pdo->prepare("DELETE FROM Tables WHERE TableId = :id")->execute(['id' => $tableId]);
    jsonSuccess(['softDeleted' => false], 'Xóa bàn thành công');
}
