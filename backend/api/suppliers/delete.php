<?php
/**
 * FILE: backend/api/suppliers/delete.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xóa 1 nhà cung cấp. Nếu nhà cung cấp đang được gán cho nguyên liệu
 *    nào đó (Ingredients.SupplierId), không cho xóa cứng để tránh mất
 *    liên kết - yêu cầu gỡ nhà cung cấp khỏi nguyên liệu trước.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/suppliers.js (khi bấm nút "Xóa")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    suppliers.js (fetch DELETE {supplierId})
 *    --> delete.php --> kiểm tra Ingredients liên quan --> DELETE hoặc báo lỗi
 *    --> trả JSON --> suppliers.js load lại bảng
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
$errors = validateRequired($input, ['supplierId']);
if (!empty($errors)) {
    jsonError('Thiếu supplierId', 400, $errors);
}

$pdo = getDbConnection();
$supplierId = (int)$input['supplierId'];

$checkSupplier = $pdo->prepare("SELECT SupplierId FROM Suppliers WHERE SupplierId = :id");
$checkSupplier->execute(['id' => $supplierId]);
if (!$checkSupplier->fetch()) {
    jsonError('Nhà cung cấp không tồn tại', 404);
}

$checkIngredients = $pdo->prepare("SELECT COUNT(*) AS Total FROM Ingredients WHERE SupplierId = :id");
$checkIngredients->execute(['id' => $supplierId]);
$ingredientCount = (int)$checkIngredients->fetch()['Total'];

if ($ingredientCount > 0) {
    jsonError(
        "Không thể xóa vì nhà cung cấp này đang được gán cho $ingredientCount nguyên liệu. Hãy gỡ liên kết trước.",
        400
    );
}

$stmt = $pdo->prepare("DELETE FROM Suppliers WHERE SupplierId = :id");
$stmt->execute(['id' => $supplierId]);

jsonSuccess(null, 'Xóa nhà cung cấp thành công');
