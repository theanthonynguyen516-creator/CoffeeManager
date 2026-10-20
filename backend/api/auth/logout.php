<?php
/**
 * FILE: backend/api/auth/logout.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Hủy session hiện tại (đăng xuất người dùng).
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/common.js (nút "Đăng xuất" trên Header)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch POST, không cần body) --> logout.php --> hủy $_SESSION
 *    --> trả JSON success --> JS chuyển hướng về trang đăng nhập
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../utils/response.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
session_destroy();

jsonSuccess(null, 'Đăng xuất thành công');
