<?php
/**
 * FILE: backend/api/customers/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật thông tin 1 khách hàng (tên, SĐT, email). Có thể chỉnh
 *    tay điểm tích lũy nếu cần (vd: cộng điểm khuyến mãi thủ công).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/customers.js (form "Sửa khách hàng")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    customers.js (fetch PUT {customerId, fullName, phone, email, loyaltyPoints})
 *    --> update.php --> validate --> UPDATE trong SQL Server
 *    --> trả JSON thành công --> customers.js load lại bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['customerId', 'fullName']);

if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (isset($input['loyaltyPoints']) && !validateNonNegativeNumber($input['loyaltyPoints'])) {
    $errors['loyaltyPoints'] = 'Điểm tích lũy phải là số và không được âm';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$customerId = (int)$input['customerId'];
$phone = !empty($input['phone']) ? sanitizeString($input['phone']) : null;

$checkCustomer = $pdo->prepare("SELECT CustomerId FROM Customers WHERE CustomerId = :id");
$checkCustomer->execute(['id' => $customerId]);
if (!$checkCustomer->fetch()) {
    jsonError('Khách hàng không tồn tại', 404);
}

if ($phone !== null) {
    $checkPhone = $pdo->prepare("SELECT CustomerId FROM Customers WHERE Phone = :phone AND CustomerId != :id");
    $checkPhone->execute(['phone' => $phone, 'id' => $customerId]);
    if ($checkPhone->fetch()) {
        jsonError('Số điện thoại đã được sử dụng bởi khách hàng khác', 400, ['phone' => 'Số điện thoại đã tồn tại']);
    }
}

$stmt = $pdo->prepare("
    UPDATE Customers
    SET FullName = :fullName,
        Phone = :phone,
        Email = :email,
        LoyaltyPoints = :loyaltyPoints
    WHERE CustomerId = :customerId
");
$stmt->execute([
    'fullName'      => sanitizeString($input['fullName']),
    'phone'         => $phone,
    'email'         => !empty($input['email']) ? sanitizeString($input['email']) : null,
    'loyaltyPoints' => isset($input['loyaltyPoints']) ? (int)$input['loyaltyPoints'] : 0,
    'customerId'    => $customerId,
]);

jsonSuccess(null, 'Cập nhật khách hàng thành công');
