<?php
/**
 * FILE: backend/middleware/permission.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Kiểm tra user đang đăng nhập có đủ QUYỀN (theo vai trò/role) để thực
 *    hiện thao tác hay không. Ví dụ: chỉ Admin/Manager mới được xóa sản phẩm.
 *
 * 2. File này giao tiếp với file nào?
 *    Được require SAU auth.php ở các API cần giới hạn quyền, vd:
 *    backend/api/employees/delete.php (chỉ Admin mới được xóa nhân viên)
 *
 * 3. File này gọi API nào?
 *    Không gọi API nào, chỉ so sánh RoleName với danh sách cho phép.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    auth.php trả về $user (đã đăng nhập) --> permission.php kiểm tra
 *    $user['RoleName'] có nằm trong danh sách cho phép không.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../utils/response.php';

/**
 * Yêu cầu user phải có 1 trong các vai trò được liệt kê, nếu không sẽ trả lỗi 403.
 *
 * @param array $user         Thông tin user (lấy từ requireLogin())
 * @param array $allowedRoles Danh sách role được phép, vd: ['Admin', 'Manager']
 */
function requireRole(array $user, array $allowedRoles): void
{
    if (!in_array($user['RoleName'], $allowedRoles, true)) {
        jsonError('Bạn không có quyền thực hiện thao tác này', 403);
    }
}
