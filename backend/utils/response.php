<?php
/**
 * FILE: backend/utils/response.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Chuẩn hóa cách trả JSON cho TẤT CẢ API, tránh mỗi file viết một kiểu.
 *
 * 2. File này giao tiếp với file nào?
 *    Được require bởi các file trong backend/api/**.php
 *
 * 3. File này gọi API nào?
 *    Không gọi API nào, chỉ là hàm hỗ trợ (helper).
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    backend/api/*.php --(gọi jsonSuccess/jsonError)--> trả JSON --> trình duyệt (JS fetch)
 * -----------------------------------------------------------------------
 */

/**
 * Trả về JSON khi xử lý thành công.
 *
 * @param mixed  $data    Dữ liệu trả về (mảng, object...)
 * @param string $message Thông báo cho người dùng
 * @param int    $code    HTTP status code (mặc định 200)
 */
function jsonSuccess($data = [], string $message = 'Thành công', int $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Trả về JSON khi xử lý thất bại.
 *
 * @param string $message Thông báo lỗi
 * @param int    $code    HTTP status code (400 = lỗi dữ liệu, 401 = chưa đăng nhập,
 *                         403 = không đủ quyền, 404 = không tìm thấy, 500 = lỗi server)
 * @param mixed  $errors  Chi tiết lỗi (vd: lỗi validate từng field)
 */
function jsonError(string $message = 'Có lỗi xảy ra', int $code = 400, $errors = null)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'errors'  => $errors,
        'data'    => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Đọc dữ liệu JSON gửi lên từ JavaScript (fetch với body JSON).
 *
 * @return array
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
