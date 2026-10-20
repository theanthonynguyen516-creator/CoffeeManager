<?php
/**
 * FILE: backend/api/tables/transfer.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    "Chuyển bàn": di chuyển đơn hàng đang mở (PENDING) từ bàn nguồn sang
 *    bàn đích. Bàn nguồn chuyển về "Trống", bàn đích chuyển sang
 *    "Đang sử dụng".
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/tables.js (nút "Chuyển bàn")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    tables.js (fetch POST {fromTableId, toTableId})
 *    --> transfer.php --> UPDATE Orders.TableId + UPDATE Tables.TableStatusId (2 bàn)
 *    --> trả JSON --> tables.js load lại sơ đồ bàn
 *
 *    Toàn bộ thao tác được bọc trong 1 TRANSACTION để đảm bảo tính toàn
 *    vẹn: hoặc cả 3 câu UPDATE cùng thành công, hoặc không câu nào được
 *    áp dụng (tránh trường hợp đơn hàng "kẹt" giữa 2 bàn nếu có lỗi).
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin(); // mọi nhân viên đều được chuyển bàn khi phục vụ

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['fromTableId', 'toTableId']);
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$fromTableId = (int)$input['fromTableId'];
$toTableId = (int)$input['toTableId'];

if ($fromTableId === $toTableId) {
    jsonError('Bàn nguồn và bàn đích không được trùng nhau', 400);
}

$pdo = getDbConnection();

// Kiểm tra bàn đích phải đang "Trống" trước khi chuyển
$toTableStmt = $pdo->prepare("
    SELECT t.TableId, ts.StatusCode
    FROM Tables t INNER JOIN TableStatuses ts ON t.TableStatusId = ts.TableStatusId
    WHERE t.TableId = :id
");
$toTableStmt->execute(['id' => $toTableId]);
$toTable = $toTableStmt->fetch();
if (!$toTable) {
    jsonError('Bàn đích không tồn tại', 404);
}
if ($toTable['StatusCode'] !== 'EMPTY') {
    jsonError('Bàn đích phải đang ở trạng thái "Trống"', 400);
}

// Tìm đơn hàng đang mở (PENDING) tại bàn nguồn
$orderStmt = $pdo->prepare("SELECT TOP 1 OrderId FROM Orders WHERE TableId = :id AND OrderStatus = 'PENDING'");
$orderStmt->execute(['id' => $fromTableId]);
$order = $orderStmt->fetch();
if (!$order) {
    jsonError('Bàn nguồn không có đơn hàng đang mở nào để chuyển', 400);
}

$emptyStatusId = $pdo->query("SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY'")->fetch()['TableStatusId'];
$inUseStatusId = $pdo->query("SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'IN_USE'")->fetch()['TableStatusId'];

try {
    $pdo->beginTransaction();

    // 1. Chuyển đơn hàng sang bàn đích
    $pdo->prepare("UPDATE Orders SET TableId = :toTableId, UpdatedAt = GETDATE() WHERE OrderId = :orderId")
        ->execute(['toTableId' => $toTableId, 'orderId' => $order['OrderId']]);

    // 2. Bàn đích -> Đang sử dụng
    $pdo->prepare("UPDATE Tables SET TableStatusId = :statusId WHERE TableId = :id")
        ->execute(['statusId' => $inUseStatusId, 'id' => $toTableId]);

    // 3. Bàn nguồn -> Trống
    $pdo->prepare("UPDATE Tables SET TableStatusId = :statusId WHERE TableId = :id")
        ->execute(['statusId' => $emptyStatusId, 'id' => $fromTableId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Chuyển bàn thất bại, vui lòng thử lại', 500);
}

jsonSuccess(null, 'Chuyển bàn thành công');
