<?php
/**
 * FILE: backend/api/inventory/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Thêm 1 nguyên liệu mới vào kho (khởi tạo số lượng tồn ban đầu).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js (form "Thêm nguyên liệu")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    inventory.js (fetch POST {ingredientName, unit, quantityInStock,
 *                   lowStockThreshold, supplierId})
 *    --> create.php --> validate --> INSERT vào SQL Server
 *    --> trả JSON --> inventory.js thêm dòng mới vào bảng kho
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
$errors = validateRequired($input, ['ingredientName', 'unit']);

if (isset($input['quantityInStock']) && !validateNonNegativeNumber($input['quantityInStock'])) {
    $errors['quantityInStock'] = 'Số lượng tồn phải là số và không được âm';
}
if (isset($input['lowStockThreshold']) && !validateNonNegativeNumber($input['lowStockThreshold'])) {
    $errors['lowStockThreshold'] = 'Ngưỡng cảnh báo phải là số và không được âm';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    INSERT INTO Ingredients (IngredientName, Unit, QuantityInStock, LowStockThreshold, SupplierId)
    OUTPUT INSERTED.IngredientId
    VALUES (:name, :unit, :quantity, :threshold, :supplierId)
");
$stmt->execute([
    'name'      => sanitizeString($input['ingredientName']),
    'unit'      => sanitizeString($input['unit']),
    'quantity'  => isset($input['quantityInStock']) ? (float)$input['quantityInStock'] : 0,
    'threshold' => isset($input['lowStockThreshold']) ? (float)$input['lowStockThreshold'] : 0,
    'supplierId'=> !empty($input['supplierId']) ? (int)$input['supplierId'] : null,
]);
$newId = $stmt->fetch()['IngredientId'];

jsonSuccess(['ingredientId' => $newId], 'Thêm nguyên liệu thành công', 201);
