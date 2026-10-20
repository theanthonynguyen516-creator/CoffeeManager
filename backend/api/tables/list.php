<?php
/**
 * FILE: backend/api/tables/list.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về danh sách toàn bộ bàn kèm trạng thái. Nếu bàn đang "Đang sử
 *    dụng", trả kèm thông tin đơn hàng (PENDING) đang mở tại bàn đó để
 *    giao diện hiển thị (vd: tổng tiền tạm tính).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/tables.js, và sẽ được assets/js/pos.js (Giai
 *    đoạn 10) dùng lại để hiển thị sơ đồ bàn khi bán hàng.
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET) --> list.php --> SQL Server (Tables JOIN TableStatuses
 *    JOIN Orders đang PENDING) --> trả JSON --> JS vẽ sơ đồ bàn
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận GET', 405);
}

$pdo = getDbConnection();

$stmt = $pdo->query("
    SELECT
        t.TableId, t.TableName, t.Capacity, t.IsActive,
        ts.TableStatusId, ts.StatusCode, ts.StatusName,
        o.OrderId AS CurrentOrderId, o.OrderCode AS CurrentOrderCode, o.TotalAmount AS CurrentOrderTotal
    FROM Tables t
    INNER JOIN TableStatuses ts ON t.TableStatusId = ts.TableStatusId
    OUTER APPLY (
        SELECT TOP 1 OrderId, OrderCode, TotalAmount
        FROM Orders o2
        WHERE o2.TableId = t.TableId AND o2.OrderStatus = 'PENDING'
        ORDER BY o2.CreatedAt DESC
    ) o
    WHERE t.IsActive = 1
    ORDER BY t.TableName ASC
");

jsonSuccess($stmt->fetchAll(), 'Lấy danh sách bàn thành công');
