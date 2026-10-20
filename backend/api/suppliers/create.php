<?php
/**
 * FILE: backend/api/suppliers/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Thêm 1 nhà cung cấp mới.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/suppliers.js (form "Thêm nhà cung cấp")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    suppliers.js (fetch POST {supplierName, contactName, phone, email, address})
 *    --> create.php --> validate --> INSERT vào SQL Server
 *    --> trả JSON --> suppliers.js thêm dòng mới vào bảng
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
$errors = validateRequired($input, ['supplierName']);
if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare("
    INSERT INTO Suppliers (SupplierName, ContactName, Phone, Email, Address, IsActive)
    OUTPUT INSERTED.SupplierId
    VALUES (:name, :contactName, :phone, :email, :address, 1)
");
$stmt->execute([
    'name'        => sanitizeString($input['supplierName']),
    'contactName' => !empty($input['contactName']) ? sanitizeString($input['contactName']) : null,
    'phone'       => !empty($input['phone']) ? sanitizeString($input['phone']) : null,
    'email'       => !empty($input['email']) ? sanitizeString($input['email']) : null,
    'address'     => !empty($input['address']) ? sanitizeString($input['address']) : null,
]);
$newId = $stmt->fetch()['SupplierId'];

jsonSuccess(['supplierId' => $newId], 'Thêm nhà cung cấp thành công', 201);
