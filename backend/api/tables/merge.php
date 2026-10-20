<?php
/**
 * FILE: backend/api/tables/merge.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    "Gộp bàn": gộp toàn bộ món ăn/thức uống (OrderDetails) từ đơn hàng
 *    đang mở tại bàn nguồn VÀO đơn hàng đang mở tại bàn đích. Sau khi
 *    gộp, đơn hàng nguồn được hủy (CANCELLED) và bàn nguồn chuyển về
 *    "Trống". Dùng khi khách gộp 2 bàn ngồi chung thành 1.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/tables.js (nút "Gộp bàn")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    tables.js (fetch POST {sourceTableId, targetTableId})
 *    --> merge.php --> UPDATE OrderDetails.OrderId (chuyển món)
 *        --> tính lại SubTotal/TotalAmount đơn đích
 *        --> hủy đơn nguồn --> bàn nguồn về Trống
 *    --> trả JSON --> tables.js load lại sơ đồ bàn
 *
 *    Toàn bộ thao tác được bọc trong 1 TRANSACTION.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['sourceTableId', 'targetTableId']);
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$sourceTableId = (int)$input['sourceTableId'];
$targetTableId = (int)$input['targetTableId'];

if ($sourceTableId === $targetTableId) {
    jsonError('Bàn nguồn và bàn đích không được trùng nhau', 400);
}

$pdo = getDbConnection();

// Tìm đơn hàng đang mở tại cả 2 bàn
$sourceOrderStmt = $pdo->prepare("SELECT TOP 1 OrderId FROM Orders WHERE TableId = :id AND OrderStatus = 'PENDING'");
$sourceOrderStmt->execute(['id' => $sourceTableId]);
$sourceOrder = $sourceOrderStmt->fetch();

$targetOrderStmt = $pdo->prepare("SELECT TOP 1 OrderId FROM Orders WHERE TableId = :id AND OrderStatus = 'PENDING'");
$targetOrderStmt->execute(['id' => $targetTableId]);
$targetOrder = $targetOrderStmt->fetch();

if (!$sourceOrder || !$targetOrder) {
    jsonError('Cả 2 bàn đều phải đang có đơn hàng mở thì mới gộp được', 400);
}

$emptyStatusId = $pdo->query("SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY'")->fetch()['TableStatusId'];

try {
    $pdo->beginTransaction();

    // 1. Chuyển toàn bộ món từ đơn nguồn sang đơn đích
    $pdo->prepare("UPDATE OrderDetails SET OrderId = :targetOrderId WHERE OrderId = :sourceOrderId")
        ->execute(['targetOrderId' => $targetOrder['OrderId'], 'sourceOrderId' => $sourceOrder['OrderId']]);

    // 2. Tính lại SubTotal/TotalAmount của đơn đích dựa trên các món vừa gộp
    $recalcStmt = $pdo->prepare("
        SELECT ISNULL(SUM(Quantity * UnitPrice), 0) AS SubTotal
        FROM OrderDetails WHERE OrderId = :orderId
    ");
    $recalcStmt->execute(['orderId' => $targetOrder['OrderId']]);
    $newSubTotal = (float)$recalcStmt->fetch()['SubTotal'];

    $pdo->prepare("
        UPDATE Orders
        SET SubTotal = :subTotal, TotalAmount = :subTotal - DiscountAmount, UpdatedAt = GETDATE()
        WHERE OrderId = :orderId
    ")->execute(['subTotal' => $newSubTotal, 'orderId' => $targetOrder['OrderId']]);

    // 3. Hủy đơn hàng nguồn (đã rỗng món)
    $pdo->prepare("UPDATE Orders SET OrderStatus = 'CANCELLED', UpdatedAt = GETDATE() WHERE OrderId = :orderId")
        ->execute(['orderId' => $sourceOrder['OrderId']]);

    // 4. Bàn nguồn -> Trống
    $pdo->prepare("UPDATE Tables SET TableStatusId = :statusId WHERE TableId = :id")
        ->execute(['statusId' => $emptyStatusId, 'id' => $sourceTableId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Gộp bàn thất bại, vui lòng thử lại', 500);
}

jsonSuccess(null, 'Gộp bàn thành công');
