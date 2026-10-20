<?php
/**
 * FILE: backend/api/categories/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 danh mục. Nếu danh mục ĐANG có sản phẩm bên trong, không cho
 *    xóa (vì Products.CategoryId là NOT NULL, xóa sẽ gây lỗi ràng buộc
 *    khóa ngoại) -> yêu cầu người dùng chuyển/xóa hết sản phẩm trước.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/categories.js (khi bấm nút "Xóa")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    categories.js (fetch DELETE {categoryId})
 *    --> delete.php --> kiểm tra Products liên quan --> DELETE hoặc báo lỗi
 *    --> trả JSON --> categories.js load lại bảng
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
$errors = validateRequired($input, ['categoryId']);
if (!empty($errors)) {
    jsonError('Thiếu categoryId', 400, $errors);
}

$pdo = getDbConnection();
$categoryId = (int)$input['categoryId'];

$checkCategory = $pdo->prepare("SELECT CategoryId FROM Categories WHERE CategoryId = :id");
$checkCategory->execute(['id' => $categoryId]);
if (!$checkCategory->fetch()) {
    jsonError('Danh mục không tồn tại', 404);
}

// Kiểm tra còn sản phẩm nào thuộc danh mục này không
$checkProducts = $pdo->prepare("SELECT COUNT(*) AS Total FROM Products WHERE CategoryId = :id");
$checkProducts->execute(['id' => $categoryId]);
$productCount = (int)$checkProducts->fetch()['Total'];

if ($productCount > 0) {
    jsonError(
        "Không thể xóa vì danh mục này đang có $productCount sản phẩm. Hãy chuyển sản phẩm sang danh mục khác trước.",
        400
    );
}

$stmt = $pdo->prepare("DELETE FROM Categories WHERE CategoryId = :id");
$stmt->execute(['id' => $categoryId]);

jsonSuccess(null, 'Xóa danh mục thành công');
