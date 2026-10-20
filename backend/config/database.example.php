<?php
/**
 * FILE: backend/config/database.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Tạo và trả về kết nối tới Microsoft SQL Server bằng driver sqlsrv.
 *    MỌI file PHP nào cần truy vấn database đều require file này.
 *
 * 2. File này giao tiếp với file nào?
 *    Được require bởi tất cả các file trong backend/api/**.php
 *
 * 3. File này gọi API nào?
 *    Không gọi API nào cả, đây là lớp thấp nhất (kết nối DB thuần).
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    backend/api/*.php  --(gọi hàm getDbConnection())-->  database.php
 *    --(kết nối)--> SQL Server
 *
 * LƯU Ý BẢO MẬT:
 *    - File này KHÔNG được đưa ra ngoài thư mục backend/.
 *    - KHÔNG bao giờ echo/trả thông tin kết nối (host, user, password) ra JSON.
 *    - Yêu cầu cài extension "sqlsrv" và "pdo_sqlsrv" cho PHP
 *      (tải tại: https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server)
 *
 * SỬA LỖI "Phản hồi không hợp lệ từ server" / "response.json() failed":
 *    Lỗi này KHÔNG phải do JavaScript, mà do PHP đang in ra HTML/text thay
 *    vì JSON thuần (thường là do PHP Warning/Notice hiện ra trước khi
 *    header Content-Type: application/json được gửi). Hai dòng ini_set()
 *    bên dưới tắt việc HIỂN THỊ lỗi ra trình duyệt (chỉ ghi vào log), giúp
 *    JSON luôn "sạch". Nếu vẫn lỗi, đặt DEBUG_MODE = true bên dưới để xem
 *    thông báo lỗi kết nối THẬT (chỉ bật khi đang tự phát triển, PHẢI tắt
 *    lại khi triển khai thật).
 * -----------------------------------------------------------------------
 */

// ==== CHỈ BẬT KHI ĐANG DEBUG - PHẢI ĐẶT LẠI false KHI DÙNG THẬT ====
define('DEBUG_MODE', true);

// Tắt hiển thị lỗi PHP ra trình duyệt (nguyên nhân phổ biến nhất khiến JSON
// bị "bẩn" và JS báo "phản hồi không hợp lệ từ server"). Lỗi vẫn được ghi
// vào error log của PHP để bạn xem trong Apache/PHP log nếu cần.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

/**
 * BẮT LỖI TOÀN CỤC - áp dụng cho MỌI file API (vì database.php được
 * require_once ĐẦU TIÊN ở tất cả các file backend/api/**.php).
 *
 * Vấn đề: khi tắt display_errors (ở trên), nếu có LỖI PHP NGHIÊM TRỌNG
 * (Fatal Error) xảy ra ở bất kỳ đâu trong code, PHP sẽ trả về TRANG TRẮNG
 * (rỗng) thay vì HTML lỗi. Chuỗi rỗng KHÔNG PHẢI là JSON hợp lệ, nên
 * JavaScript báo "Phản hồi từ server không hợp lệ" - dù nguyên nhân
 * thật sự là lỗi PHP, không phải lỗi JS hay lỗi kết nối.
 *
 * 2 hàm dưới đây đảm bảo: dù lỗi gì xảy ra ở đâu, PHP LUÔN LUÔN trả về
 * JSON hợp lệ kèm THÔNG BÁO LỖI THẬT (vì DEBUG_MODE = true), để bạn thấy
 * ngay nguyên nhân trong Toast màu đỏ trên giao diện thay vì phải đoán.
 */

// Bắt các Exception không được try/catch ở đâu khác
set_exception_handler(function ($e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => DEBUG_MODE
            ? 'Lỗi hệ thống: ' . $e->getMessage() . ' (tại ' . basename($e->getFile()) . ' dòng ' . $e->getLine() . ')'
            : 'Đã xảy ra lỗi hệ thống, vui lòng thử lại.',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Bắt Fatal Error (lỗi cú pháp logic, gọi hàm không tồn tại, out of memory,...)
// - loại lỗi mà try/catch bình thường KHÔNG bắt được
register_shutdown_function(function () {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => DEBUG_MODE
                ? 'Lỗi PHP nghiêm trọng: ' . $error['message'] . ' (tại ' . basename($error['file']) . ' dòng ' . $error['line'] . ')'
                : 'Đã xảy ra lỗi hệ thống nghiêm trọng, vui lòng thử lại.',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    }
});

// ==== CẤU HÌNH KẾT NỐI - SỬA LẠI CHO ĐÚNG MÔI TRƯỜNG CỦA BẠN ====
define('DB_SERVER', 'DESKTOP-KD06');     // Tên server SQL Server của bạn
define('DB_NAME', 'CoffeeManagerDB');       // Tên database đã tạo ở database.sql
define('DB_USER', 'DoAn');                  // Username SQL Server của bạn
define('DB_PASSWORD', 'Khongdan@2006');     // Password SQL Server của bạn

/**
 * Hàm tạo kết nối tới SQL Server bằng PDO (khuyến nghị vì hỗ trợ Prepared Statement
 * rất tiện, giúp chống SQL Injection).
 *
 * @return PDO
 */
function getDbConnection()
{
    static $pdo = null; // giữ lại kết nối đã tạo, tránh tạo kết nối mới nhiều lần

    if ($pdo !== null) {
        return $pdo;
    }

    // Kiểm tra driver pdo_sqlsrv đã được cài cho PHP chưa - đây là nguyên
    // nhân RẤT PHỔ BIẾN gây lỗi "could not find driver" / trang trắng
    if (!extension_loaded('pdo_sqlsrv')) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Thiếu extension pdo_sqlsrv cho PHP. Hãy cài driver tại: '
                . 'https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server '
                . 'rồi thêm extension=pdo_sqlsrv vào php.ini và khởi động lại Apache/PHP.',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Chuỗi kết nối PDO dành cho SQL Server (yêu cầu driver pdo_sqlsrv)
        $dsn = "sqlsrv:Server=" . DB_SERVER . ";Database=" . DB_NAME;

        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // để lỗi SQL ném ra Exception thay vì im lặng
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // trả kết quả dạng mảng kết hợp
        ]);

        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            // DEBUG_MODE = true sẽ cho xem lỗi kết nối THẬT (vd: sai server,
            // sai mật khẩu, chưa bật TCP/IP...) để bạn biết chính xác cần sửa gì.
            // Khi triển khai thật, đặt DEBUG_MODE = false để không lộ thông tin nhạy cảm.
            'message' => DEBUG_MODE
                ? 'Không thể kết nối cơ sở dữ liệu. Chi tiết lỗi: ' . $e->getMessage()
                : 'Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình.',
            'data'    => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
