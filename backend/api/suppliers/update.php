<?php
/**
 * FILE: backend/api/suppliers/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật thông tin 1 nhà cung cấp (tên, người liên hệ, SĐT, email,
 *    địa chỉ, trạng thái hoạt động).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/suppliers.js (form sửa)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    suppliers.js (fetch PUT {supplierId, supplierName,...})
 *    --> update.php --> validate --> UPDATE trong SQL Server
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

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['supplierId', 'supplierName']);
if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$supplierId = (int)$input['supplierId'];

$checkSupplier = $pdo->prepare("SELECT SupplierId FROM Suppliers WHERE SupplierId = :id");
$checkSupplier->execute(['id' => $supplierId]);
if (!$checkSupplier->fetch()) {
    jsonError('Nhà cung cấp không tồn tại', 404);
}

$stmt = $pdo->prepare("
    UPDATE Suppliers
    SET SupplierName = :name, ContactName = :contactName, Phone = :phone,
        Email = :email, Address = :address, IsActive = :isActive
    WHERE SupplierId = :id
");
$stmt->execute([
    'name'        => sanitizeString($input['supplierName']),
    'contactName' => !empty($input['contactName']) ? sanitizeString($input['contactName']) : null,
    'phone'       => !empty($input['phone']) ? sanitizeString($input['phone']) : null,
    'email'       => !empty($input['email']) ? sanitizeString($input['email']) : null,
    'address'     => !empty($input['address']) ? sanitizeString($input['address']) : null,
    'isActive'    => isset($input['isActive']) ? (int)(bool)$input['isActive'] : 1,
    'id'          => $supplierId,
]);

jsonSuccess(null, 'Cập nhật nhà cung cấp thành công');
