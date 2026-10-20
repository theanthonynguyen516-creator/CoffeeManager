<?php
/**
 * FILE: backend/api/tables/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Thêm 1 bàn mới, mặc định trạng thái "Trống" (EMPTY).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/tables.js (form "Thêm bàn")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    tables.js (fetch POST {tableName, capacity})
 *    --> create.php --> validate --> INSERT vào SQL Server
 *    --> trả JSON bàn vừa tạo --> tables.js thêm thẻ bàn mới vào sơ đồ
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
$errors = validateRequired($input, ['tableName']);
if (isset($input['capacity']) && !validateNonNegativeNumber($input['capacity'])) {
    $errors['capacity'] = 'Sức chứa phải là số và không được âm';
}
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();

$emptyStatusId = $pdo->query("SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY'")->fetch()['TableStatusId'];

$stmt = $pdo->prepare("
    INSERT INTO Tables (TableName, Capacity, TableStatusId, IsActive)
    OUTPUT INSERTED.TableId
    VALUES (:tableName, :capacity, :statusId, 1)
");
$stmt->execute([
    'tableName' => sanitizeString($input['tableName']),
    'capacity'  => isset($input['capacity']) ? (int)$input['capacity'] : 4,
    'statusId'  => $emptyStatusId,
]);
$newId = $stmt->fetch()['TableId'];

jsonSuccess(['tableId' => $newId], 'Thêm bàn thành công', 201);
