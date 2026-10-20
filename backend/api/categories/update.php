<?php
/**
 * FILE: backend/api/categories/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật tên/mô tả/trạng thái của 1 danh mục.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/categories.js (form sửa, hoặc nút bật/tắt trạng thái)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    categories.js (fetch PUT {categoryId, categoryName, ...})
 *    --> update.php --> validate --> UPDATE trong SQL Server
 *    --> trả JSON thành công --> categories.js load lại bảng
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
$errors = validateRequired($input, ['categoryId', 'categoryName']);
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$categoryId = (int)$input['categoryId'];
$categoryName = sanitizeString($input['categoryName']);

$checkCategory = $pdo->prepare("SELECT CategoryId FROM Categories WHERE CategoryId = :id");
$checkCategory->execute(['id' => $categoryId]);
if (!$checkCategory->fetch()) {
    jsonError('Danh mục không tồn tại', 404);
}

// Kiểm tra trùng tên với danh mục KHÁC (loại trừ chính nó)
$checkDup = $pdo->prepare("
    SELECT CategoryId FROM Categories
    WHERE LOWER(CategoryName) = LOWER(:name) AND CategoryId != :id
");
$checkDup->execute(['name' => $categoryName, 'id' => $categoryId]);
if ($checkDup->fetch()) {
    jsonError('Tên danh mục đã tồn tại', 400, ['categoryName' => 'Trùng tên với danh mục khác']);
}

$stmt = $pdo->prepare("
    UPDATE Categories
    SET CategoryName = :categoryName,
        Description = :description,
        IsActive = :isActive
    WHERE CategoryId = :categoryId
");
$stmt->execute([
    'categoryName' => $categoryName,
    'description'  => isset($input['description']) ? sanitizeString($input['description']) : null,
    'isActive'     => isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1,
    'categoryId'   => $categoryId,
]);

jsonSuccess(null, 'Cập nhật danh mục thành công');
