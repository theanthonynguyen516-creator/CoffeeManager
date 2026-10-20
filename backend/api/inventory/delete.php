<?php
/**
 * FILE: backend/api/inventory/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 nguyên liệu khỏi kho. Nếu nguyên liệu đã từng có lịch sử
 *    nhập/xuất (InventoryTransactions), không cho xóa để giữ toàn vẹn
 *    lịch sử kho.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js (khi bấm nút "Xóa")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    inventory.js (fetch DELETE {ingredientId})
 *    --> delete.php --> kiểm tra InventoryTransactions --> DELETE hoặc báo lỗi
 *    --> trả JSON --> inventory.js load lại bảng kho
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
$errors = validateRequired($input, ['ingredientId']);
if (!empty($errors)) {
    jsonError('Thiếu ingredientId', 400, $errors);
}

$pdo = getDbConnection();
$ingredientId = (int)$input['ingredientId'];

$checkIngredient = $pdo->prepare("SELECT IngredientId FROM Ingredients WHERE IngredientId = :id");
$checkIngredient->execute(['id' => $ingredientId]);
if (!$checkIngredient->fetch()) {
    jsonError('Nguyên liệu không tồn tại', 404);
}

$checkTransactions = $pdo->prepare("SELECT TOP 1 TransactionId FROM InventoryTransactions WHERE IngredientId = :id");
$checkTransactions->execute(['id' => $ingredientId]);

if ($checkTransactions->fetch()) {
    jsonError('Không thể xóa vì nguyên liệu này đã có lịch sử nhập/xuất kho', 400);
}

$stmt = $pdo->prepare("DELETE FROM Ingredients WHERE IngredientId = :id");
$stmt->execute(['id' => $ingredientId]);

jsonSuccess(null, 'Xóa nguyên liệu thành công');
