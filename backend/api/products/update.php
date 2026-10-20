<?php
/**
 * FILE: backend/api/products/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật thông tin 1 sản phẩm đã có (tên, giá, danh mục, hình ảnh,
 *    mô tả, hoặc trạng thái Bật/Tắt).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/products.js (khi submit form "Sửa sản phẩm"
 *    hoặc khi bấm nút bật/tắt trạng thái)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    products.js (fetch PUT {productId, productName, ...})
 *    --> update.php --> validate --> UPDATE trong SQL Server
 *    --> trả JSON thành công --> products.js cập nhật lại dòng trong bảng
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
$errors = validateRequired($input, ['productId', 'productName', 'categoryId', 'sellPrice']);

if (isset($input['sellPrice']) && !validateNonNegativeNumber($input['sellPrice'])) {
    $errors['sellPrice'] = 'Giá bán phải là số và không được âm';
}
if (isset($input['costPrice']) && !validateNonNegativeNumber($input['costPrice'])) {
    $errors['costPrice'] = 'Giá vốn phải là số và không được âm';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$productId = (int)$input['productId'];

// Kiểm tra sản phẩm có tồn tại không
$checkProduct = $pdo->prepare("SELECT ProductId FROM Products WHERE ProductId = :id");
$checkProduct->execute(['id' => $productId]);
if (!$checkProduct->fetch()) {
    jsonError('Sản phẩm không tồn tại', 404);
}

$stmt = $pdo->prepare("
    UPDATE Products
    SET ProductName = :productName,
        CategoryId = :categoryId,
        SellPrice = :sellPrice,
        CostPrice = :costPrice,
        ImageUrl = :imageUrl,
        Description = :description,
        IsActive = :isActive,
        UpdatedAt = GETDATE()
    WHERE ProductId = :productId
");
$stmt->execute([
    'productName' => sanitizeString($input['productName']),
    'categoryId'  => (int)$input['categoryId'],
    'sellPrice'   => (float)$input['sellPrice'],
    'costPrice'   => isset($input['costPrice']) ? (float)$input['costPrice'] : 0,
    'imageUrl'    => isset($input['imageUrl']) ? sanitizeString($input['imageUrl']) : null,
    'description' => isset($input['description']) ? sanitizeString($input['description']) : null,
    'isActive'    => isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1,
    'productId'   => $productId,
]);

jsonSuccess(null, 'Cập nhật sản phẩm thành công');
