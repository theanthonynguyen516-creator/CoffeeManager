<?php
/**
 * FILE: backend/api/inventory/history.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về lịch sử giao dịch kho (nhập/xuất/điều chỉnh). Có thể lọc
 *    theo 1 nguyên liệu cụ thể (?ingredientId=...) hoặc xem TẤT CẢ.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js (khi bấm nút "Lịch sử kho")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    inventory.js (fetch GET ?ingredientId=... hoặc không truyền gì)
 *    --> history.php --> SQL Server (InventoryTransactions JOIN Ingredients JOIN Employees)
 *    --> trả JSON --> inventory.js render modal lịch sử kho
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
$ingredientId = !empty($_GET['ingredientId']) ? (int)$_GET['ingredientId'] : null;

$where = $ingredientId !== null ? 'WHERE it.IngredientId = :ingredientId' : '';

$sql = "
    SELECT TOP 100
        it.TransactionId, it.IngredientId, i.IngredientName, i.Unit,
        it.TransactionType, it.Quantity, it.Note, it.CreatedAt,
        u.FullName AS EmployeeName
    FROM InventoryTransactions it
    INNER JOIN Ingredients i ON it.IngredientId = i.IngredientId
    LEFT JOIN Employees e ON it.EmployeeId = e.EmployeeId
    LEFT JOIN Users u ON e.UserId = u.UserId
    $where
    ORDER BY it.CreatedAt DESC
";
$stmt = $pdo->prepare($sql);
if ($ingredientId !== null) {
    $stmt->bindValue(':ingredientId', $ingredientId, PDO::PARAM_INT);
}
$stmt->execute();

jsonSuccess($stmt->fetchAll(), 'Lấy lịch sử kho thành công');
