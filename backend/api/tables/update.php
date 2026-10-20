<?php
/**
 * FILE: backend/api/tables/update.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cập nhật tên/sức chứa/trạng thái của 1 bàn. Dùng cho cả việc sửa
 *    thông tin bàn lẫn đổi trạng thái nhanh (vd: "Đang dọn" -> "Trống").
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/tables.js (form sửa bàn, hoặc bấm đổi trạng thái)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    tables.js (fetch PUT {tableId, tableName, capacity, statusCode})
 *    --> update.php --> validate --> UPDATE trong SQL Server
 *    --> trả JSON thành công --> tables.js load lại sơ đồ bàn
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/permission.php';

$user = requireLogin();
requireRole($user, ['Admin', 'Manager', 'Cashier', 'Staff']); // ai cũng được đổi trạng thái bàn khi phục vụ

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận PUT', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['tableId']);
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$pdo = getDbConnection();
$tableId = (int)$input['tableId'];

$checkTable = $pdo->prepare("SELECT TableId FROM Tables WHERE TableId = :id");
$checkTable->execute(['id' => $tableId]);
if (!$checkTable->fetch()) {
    jsonError('Bàn không tồn tại', 404);
}

// Xây dựng câu UPDATE động tùy theo field nào được gửi lên, để 1 API
// dùng được cho cả "sửa thông tin" lẫn "chỉ đổi trạng thái"
$setParts = [];
$params = ['tableId' => $tableId];

if (isset($input['tableName'])) {
    $setParts[] = 'TableName = :tableName';
    $params['tableName'] = sanitizeString($input['tableName']);
}
if (isset($input['capacity'])) {
    if (!validateNonNegativeNumber($input['capacity'])) {
        jsonError('Sức chứa phải là số và không được âm', 400);
    }
    $setParts[] = 'Capacity = :capacity';
    $params['capacity'] = (int)$input['capacity'];
}
if (isset($input['statusCode'])) {
    $statusStmt = $pdo->prepare("SELECT TableStatusId FROM TableStatuses WHERE StatusCode = :code");
    $statusStmt->execute(['code' => $input['statusCode']]);
    $status = $statusStmt->fetch();
    if (!$status) {
        jsonError('Trạng thái bàn không hợp lệ', 400);
    }
    $setParts[] = 'TableStatusId = :statusId';
    $params['statusId'] = $status['TableStatusId'];
}

if (empty($setParts)) {
    jsonError('Không có dữ liệu nào để cập nhật', 400);
}

$sql = "UPDATE Tables SET " . implode(', ', $setParts) . " WHERE TableId = :tableId";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

jsonSuccess(null, 'Cập nhật bàn thành công');
