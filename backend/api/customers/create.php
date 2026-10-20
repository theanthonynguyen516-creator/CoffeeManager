<?php
/**
 * FILE: backend/api/customers/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Thêm 1 khách hàng mới vào hệ thống.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/customers.js (form "Thêm khách hàng")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    customers.js (fetch POST {fullName, phone, email})
 *    --> create.php --> validate --> INSERT vào SQL Server
 *    --> trả JSON khách hàng vừa tạo --> customers.js thêm dòng mới vào bảng
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin(); // mọi nhân viên đều được thêm khách hàng khi bán hàng

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['fullName']);

if (!empty($input['email']) && !validateEmail($input['email'])) {
    $errors['email'] = 'Email không đúng định dạng';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$phone = !empty($input['phone']) ? sanitizeString($input['phone']) : null;

// Số điện thoại là UNIQUE trong database, kiểm tra trùng trước để báo lỗi rõ ràng
if ($phone !== null) {
    $checkPhone = $pdo->prepare("SELECT CustomerId FROM Customers WHERE Phone = :phone");
    $checkPhone->execute(['phone' => $phone]);
    if ($checkPhone->fetch()) {
        jsonError('Số điện thoại đã được sử dụng bởi khách hàng khác', 400, ['phone' => 'Số điện thoại đã tồn tại']);
    }
}

$stmt = $pdo->prepare("
    INSERT INTO Customers (FullName, Phone, Email, LoyaltyPoints)
    OUTPUT INSERTED.CustomerId
    VALUES (:fullName, :phone, :email, 0)
");
$stmt->execute([
    'fullName' => sanitizeString($input['fullName']),
    'phone'    => $phone,
    'email'    => !empty($input['email']) ? sanitizeString($input['email']) : null,
]);
$newId = $stmt->fetch()['CustomerId'];

jsonSuccess(['customerId' => $newId], 'Thêm khách hàng thành công', 201);
