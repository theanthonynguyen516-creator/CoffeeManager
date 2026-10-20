<?php
/**
 * FILE: backend/api/products/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Thêm 1 sản phẩm mới vào database.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/products.js (khi submit form "Thêm sản phẩm")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    products.js (fetch POST {productName, categoryId, sellPrice,...})
 *    --> create.php --> validate --> INSERT vào SQL Server
 *    --> trả JSON sản phẩm vừa tạo --> products.js thêm dòng mới vào bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager']); // chỉ Admin/Manager được thêm sản phẩm

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['productName', 'categoryId', 'sellPrice']);

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

// Kiểm tra danh mục có tồn tại không
$checkCategory = $pdo->prepare("SELECT CategoryId FROM Categories WHERE CategoryId = :id");
$checkCategory->execute(['id' => (int)$input['categoryId']]);
if (!$checkCategory->fetch()) {
    jsonError('Danh mục không tồn tại', 400);
}

$stmt = $pdo->prepare("
    INSERT INTO Products (ProductName, CategoryId, SellPrice, CostPrice, ImageUrl, Description, IsActive)
    OUTPUT INSERTED.ProductId
    VALUES (:productName, :categoryId, :sellPrice, :costPrice, :imageUrl, :description, 1)
");
$stmt->execute([
    'productName' => sanitizeString($input['productName']),
    'categoryId'  => (int)$input['categoryId'],
    'sellPrice'   => (float)$input['sellPrice'],
    'costPrice'   => isset($input['costPrice']) ? (float)$input['costPrice'] : 0,
    'imageUrl'    => isset($input['imageUrl']) ? sanitizeString($input['imageUrl']) : null,
    'description' => isset($input['description']) ? sanitizeString($input['description']) : null,
]);
$newId = $stmt->fetch()['ProductId'];

jsonSuccess(['productId' => $newId], 'Thêm sản phẩm thành công', 201);
