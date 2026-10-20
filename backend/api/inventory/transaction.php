<?php
/**
 * FILE: backend/api/inventory/transaction.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Ghi nhận 1 giao dịch kho: Nhập kho (IMPORT), Xuất kho (EXPORT), hoặc
 *    Điều chỉnh (ADJUST). Đây là CÁCH DUY NHẤT để thay đổi QuantityInStock
 *    của nguyên liệu, đảm bảo mọi thay đổi tồn kho đều có lịch sử rõ ràng.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/inventory.js (nút "Nhập kho" / "Xuất kho" / "Điều chỉnh")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    inventory.js (fetch POST {ingredientId, transactionType, quantity, note})
 *    --> transaction.php --> tính số lượng mới -->
 *        INSERT InventoryTransactions + UPDATE Ingredients.QuantityInStock
 *        (bọc trong 1 TRANSACTION)
 *    --> trả JSON --> inventory.js load lại bảng kho
 *
 *    Ý nghĩa từng loại:
 *    - IMPORT: nhập thêm hàng -> QuantityInStock += quantity
 *    - EXPORT: xuất hàng ra (hao hụt, dùng nội bộ...) -> QuantityInStock -= quantity
 *    - ADJUST: điều chỉnh về ĐÚNG BẰNG số lượng nhập vào (dùng khi kiểm kê
 *      phát hiện số liệu thực tế khác với hệ thống)
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
$errors = validateRequired($input, ['ingredientId', 'transactionType', 'quantity']);

$allowedTypes = ['IMPORT', 'EXPORT', 'ADJUST'];
if (isset($input['transactionType']) && !in_array($input['transactionType'], $allowedTypes, true)) {
    $errors['transactionType'] = 'Loại giao dịch không hợp lệ';
}
if (isset($input['quantity']) && !is_numeric($input['quantity'])) {
    $errors['quantity'] = 'Số lượng phải là số';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$ingredientId = (int)$input['ingredientId'];
$transactionType = $input['transactionType'];
$quantity = (float)$input['quantity'];
$note = isset($input['note']) ? sanitizeString($input['note']) : null;

// Tìm EmployeeId của người thực hiện (để ghi vào lịch sử)
$empStmt = $pdo->prepare("SELECT EmployeeId FROM Employees WHERE UserId = :userId");
$empStmt->execute(['userId' => $user['UserId']]);
$employee = $empStmt->fetch();
$employeeId = $employee ? $employee['EmployeeId'] : null;

$ingredientStmt = $pdo->prepare("SELECT IngredientId, QuantityInStock FROM Ingredients WHERE IngredientId = :id");
$ingredientStmt->execute(['id' => $ingredientId]);
$ingredient = $ingredientStmt->fetch();
if (!$ingredient) {
    jsonError('Nguyên liệu không tồn tại', 404);
}

$currentStock = (float)$ingredient['QuantityInStock'];

// Tính số lượng tồn MỚI tùy theo loại giao dịch
if ($transactionType === 'IMPORT') {
    if ($quantity <= 0) jsonError('Số lượng nhập phải lớn hơn 0', 400);
    $newStock = $currentStock + $quantity;
} elseif ($transactionType === 'EXPORT') {
    if ($quantity <= 0) jsonError('Số lượng xuất phải lớn hơn 0', 400);
    if ($quantity > $currentStock) jsonError('Số lượng xuất vượt quá tồn kho hiện có', 400);
    $newStock = $currentStock - $quantity;
} else { // ADJUST
    if ($quantity < 0) jsonError('Số lượng điều chỉnh không được âm', 400);
    $newStock = $quantity; // ADJUST: đặt lại tồn kho = đúng giá trị nhập vào
}

try {
    $pdo->beginTransaction();

    // 1. Ghi lịch sử giao dịch. Với ADJUST, lưu phần CHÊNH LỆCH thực tế (có thể âm)
    //    để lịch sử phản ánh đúng việc kho đã tăng/giảm bao nhiêu, không lưu số tuyệt đối.
    $loggedQuantity = ($transactionType === 'ADJUST') ? ($newStock - $currentStock) : $quantity;

    $pdo->prepare("
        INSERT INTO InventoryTransactions (IngredientId, TransactionType, Quantity, Note, EmployeeId)
        VALUES (:ingredientId, :type, :quantity, :note, :employeeId)
    ")->execute([
        'ingredientId' => $ingredientId,
        'type'         => $transactionType,
        'quantity'     => $loggedQuantity,
        'note'         => $note,
        'employeeId'   => $employeeId,
    ]);

    // 2. Cập nhật số lượng tồn kho mới
    $pdo->prepare("UPDATE Ingredients SET QuantityInStock = :newStock WHERE IngredientId = :id")
        ->execute(['newStock' => $newStock, 'id' => $ingredientId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Ghi nhận giao dịch kho thất bại, vui lòng thử lại', 500);
}

jsonSuccess(['newStock' => $newStock], 'Ghi nhận giao dịch kho thành công');
