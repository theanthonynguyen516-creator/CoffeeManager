<?php
/**
 * FILE: backend/api/auth/me.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Trả về thông tin của user đang đăng nhập (dựa vào session).
 *    Dùng để: hiển thị tên/avatar trên Header, và để mỗi trang HTML tự
 *    kiểm tra "mình đã đăng nhập chưa" khi vừa load trang.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/auth.js (chạy ở đầu mỗi trang, trừ login.html)
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    JS (fetch GET) --> me.php --> đọc $_SESSION
 *    --> trả JSON {userId, username, roleName,...} hoặc lỗi 401 nếu chưa đăng nhập
 *    --> JS hiển thị lên giao diện hoặc redirect về login nếu 401
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin(); // nếu chưa đăng nhập, hàm này tự trả lỗi 401 và dừng

jsonSuccess([
    'userId'   => $user['UserId'],
    'username' => $user['Username'],
    'roleName' => $user['RoleName'],
    'fullName' => $_SESSION['full_name'] ?? '',
], 'Lấy thông tin thành công');
