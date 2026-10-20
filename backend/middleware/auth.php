<?php
/**
 * FILE: backend/middleware/auth.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Kiểm tra người dùng đã đăng nhập chưa (dựa vào PHP Session).
 *    Nếu chưa đăng nhập -> trả lỗi 401 và dừng luôn (exit), API phía sau
 *    sẽ không bao giờ chạy tới.
 *
 * 2. File này giao tiếp với file nào?
 *    Được require ở ĐẦU các file API cần đăng nhập, vd:
 *    backend/api/products/create.php, backend/api/orders/create.php...
 *
 * 3. File này gọi API nào?
 *    Không gọi API nào, chỉ đọc session PHP có sẵn.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch, có kèm cookie session) --> PHP kiểm tra $_SESSION
 *    --> nếu hợp lệ, cho phép file API chạy tiếp
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../utils/response.php';

/**
 * Bắt đầu session nếu chưa có, và trả về thông tin user đang đăng nhập.
 * Nếu chưa đăng nhập, dừng request ngay với lỗi 401.
 *
 * @return array Thông tin user: ['UserId' => ..., 'Username' => ..., 'RoleId' => ..., 'RoleName' => ...]
 */
function requireLogin(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id'])) {
        jsonError('Bạn cần đăng nhập để thực hiện thao tác này', 401);
    }

    return [
        'UserId'   => $_SESSION['user_id'],
        'Username' => $_SESSION['username'],
        'RoleId'   => $_SESSION['role_id'],
        'RoleName' => $_SESSION['role_name'],
    ];
}
