<?php
/**
 * FILE: backend/api/categories/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Thêm 1 danh mục sản phẩm mới.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/categories.js (khi submit form "Thêm danh mục")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    categories.js (fetch POST {categoryName, description})
 *    --> create.php --> validate --> INSERT vào SQL Server
 *    --> trả JSON danh mục vừa tạo --> categories.js thêm dòng mới vào bảng
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
$errors = validateRequired($input, ['categoryName']);
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$categoryName = sanitizeString($input['categoryName']);

// Kiểm tra trùng tên danh mục (không phân biệt hoa thường)
$checkDup = $pdo->prepare("SELECT CategoryId FROM Categories WHERE LOWER(CategoryName) = LOWER(:name)");
$checkDup->execute(['name' => $categoryName]);
if ($checkDup->fetch()) {
    jsonError('Tên danh mục đã tồn tại', 400, ['categoryName' => 'Trùng tên với danh mục khác']);
}

$stmt = $pdo->prepare("
    INSERT INTO Categories (CategoryName, Description, IsActive)
    OUTPUT INSERTED.CategoryId
    VALUES (:categoryName, :description, 1)
");
$stmt->execute([
    'categoryName' => $categoryName,
    'description'  => isset($input['description']) ? sanitizeString($input['description']) : null,
]);
$newId = $stmt->fetch()['CategoryId'];

jsonSuccess(['categoryId' => $newId], 'Thêm danh mục thành công', 201);
