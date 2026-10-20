<?php
/**
 * FILE: backend/api/inventory/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật THÔNG TIN nguyên liệu: tên, đơn vị, ngưỡng cảnh báo, nhà
 *    cung cấp. LƯU Ý: KHÔNG cho sửa trực tiếp QuantityInStock ở đây -
 *    muốn thay đổi số lượng tồn phải đi qua transaction.php (Nhập/Xuất/
 *    Điều chỉnh), để LUÔN có lịch sử ghi lại trong InventoryTransactions,
 *    tránh trường hợp số tồn kho "tự nhiên" thay đổi mà không rõ lý do.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js (form "Sửa nguyên liệu")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    inventory.js (fetch PUT {ingredientId, ingredientName, unit,...})
 *    --> update.php --> UPDATE Ingredients (không đụng QuantityInStock)
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['ingredientId', 'ingredientName', 'unit']);

if (isset($input['lowStockThreshold']) && !validateNonNegativeNumber($input['lowStockThreshold'])) {
    $errors['lowStockThreshold'] = 'Ngưỡng cảnh báo phải là số và không được âm';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$ingredientId = (int)$input['ingredientId'];

$checkIngredient = $pdo->prepare("SELECT IngredientId FROM Ingredients WHERE IngredientId = :id");
$checkIngredient->execute(['id' => $ingredientId]);
if (!$checkIngredient->fetch()) {
    jsonError('Nguyên liệu không tồn tại', 404);
}

$stmt = $pdo->prepare("
    UPDATE Ingredients
    SET IngredientName = :name, Unit = :unit, LowStockThreshold = :threshold, SupplierId = :supplierId
    WHERE IngredientId = :id
");
$stmt->execute([
    'name'       => sanitizeString($input['ingredientName']),
    'unit'       => sanitizeString($input['unit']),
    'threshold'  => isset($input['lowStockThreshold']) ? (float)$input['lowStockThreshold'] : 0,
    'supplierId' => !empty($input['supplierId']) ? (int)$input['supplierId'] : null,
    'id'         => $ingredientId,
]);

jsonSuccess(null, 'Cập nhật nguyên liệu thành công');
