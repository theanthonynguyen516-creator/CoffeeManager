<?php
/**
 * FILE: backend/test-connection.php
 * -----------------------------------------------------------------------
 * MỤC ĐÍCH: File dùng để TỰ KIỂM TRA kết nối SQL Server, KHÔNG cần đăng
 * nhập, KHÔNG cần frontend. Mở trực tiếp bằng trình duyệt:
 *
 *     http://localhost/CoffeeManager/backend/test-connection.php
 *
 * Nếu thấy "KẾT NỐI THÀNH CÔNG" + danh sách bảng -> database.php đã đúng,
 * lỗi nằm ở chỗ khác (xem gợi ý cuối trang này).
 *
 * QUAN TRỌNG: XÓA FILE NÀY sau khi debug xong, không để lộ trên môi
 * trường thật vì nó không yêu cầu đăng nhập.
 * -----------------------------------------------------------------------
 */

// Hiện lỗi trực tiếp ra màn hình CHỈ TRONG FILE NÀY (mục đích debug)
ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:monospace; font-size:14px; padding:20px;'>";

echo "===== 1. KIỂM TRA PHP =====\n";
echo "Phiên bản PHP: " . phpversion() . "\n";

echo "\n===== 2. KIỂM TRA DRIVER SQL SERVER =====\n";
if (extension_loaded('pdo_sqlsrv')) {
    echo "✅ Đã cài pdo_sqlsrv\n";
} else {
    echo "❌ CHƯA cài pdo_sqlsrv - đây thường là nguyên nhân chính!\n";
    echo "   Tải driver tại: https://learn.microsoft.com/sql/connect/php/download-drivers-php-sql-server\n";
    echo "   Sau khi tải, thêm 2 dòng sau vào file php.ini (tìm bằng lệnh: php --ini):\n";
    echo "       extension=sqlsrv\n";
    echo "       extension=pdo_sqlsrv\n";
    echo "   Rồi khởi động lại Apache/Web server.\n";
}
if (extension_loaded('sqlsrv')) {
    echo "✅ Đã cài sqlsrv\n";
} else {
    echo "❌ CHƯA cài sqlsrv\n";
}

echo "\n===== 3. THỬ KẾT NỐI SQL SERVER =====\n";
require_once __DIR__ . '/config/database.php';

// Không dùng lại hàm getDbConnection() ở đây vì hàm đó tự in JSON khi lỗi;
// ta tự kết nối lại để in thông báo dạng chữ dễ đọc hơn khi debug.
try {
    $dsn = "sqlsrv:Server=" . DB_SERVER . ";Database=" . DB_NAME;
    echo "Đang kết nối tới: Server=" . DB_SERVER . " ; Database=" . DB_NAME . " ; User=" . DB_USER . "\n";

    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo "✅ KẾT NỐI THÀNH CÔNG!\n";

    echo "\n===== 4. DANH SÁCH BẢNG TRONG DATABASE =====\n";
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) === 0) {
        echo "⚠️  Kết nối được nhưng database CHƯA CÓ BẢNG NÀO.\n";
        echo "   -> Bạn cần chạy database/database.sql (rồi seed.sql) trong SSMS trước.\n";
    } else {
        echo "Tìm thấy " . count($tables) . " bảng:\n";
        foreach ($tables as $t) {
            echo "  - $t\n";
        }
    }

    echo "\n===== 5. KIỂM TRA TÀI KHOẢN ADMIN ĐÃ CÓ CHƯA =====\n";
    if (in_array('Users', $tables)) {
        $stmt = $pdo->query("SELECT Username, LEN(PasswordHash) AS HashLength FROM Users WHERE Username = 'admin'");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            echo "✅ Tài khoản 'admin' đã tồn tại (độ dài PasswordHash: {$admin['HashLength']} ký tự).\n";
            if ((int)$admin['HashLength'] < 59) {
                echo "⚠️  PasswordHash có vẻ KHÔNG PHẢI hash thật của bcrypt (thường dài 60 ký tự).\n";
                echo "   -> Bạn cần tạo lại hash thật, xem hướng dẫn ở README.md mục 'Cài đặt bước 4'.\n";
            }
        } else {
            echo "❌ Chưa thấy tài khoản 'admin' -> hãy chạy database/seed.sql\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ KẾT NỐI THẤT BẠI!\n";
    echo "Thông báo lỗi thật từ PDO:\n";
    echo $e->getMessage() . "\n";

    echo "\n----- GỢI Ý SỬA LỖI THƯỜNG GẶP -----\n";
    echo "1. Nếu lỗi nhắc đến 'Login failed' hoặc 'authentication':\n";
    echo "   - Kiểm tra lại Username/Password trong backend/config/database.php\n";
    echo "   - Vào SSMS > chuột phải server > Properties > Security, đảm bảo đã bật\n";
    echo "     'SQL Server and Windows Authentication mode' (không chỉ Windows Authentication).\n";
    echo "   - Vào SSMS > Security > Logins > chuột phải user 'DoAn' > Properties, đảm bảo\n";
    echo "     tài khoản chưa bị khóa (Status > Login: Enabled).\n\n";

    echo "2. Nếu lỗi nhắc đến 'server was not found' hoặc 'network-related':\n";
    echo "   - Tên server DESKTOP-Q4MH6E2 có thể cần thêm tên instance, vd:\n";
    echo "     DESKTOP-Q4MH6E2\\SQLEXPRESS (nếu bạn cài SQL Server Express).\n";
    echo "   - Kiểm tra tên instance: mở SSMS, xem tên hiển thị ngay khi đăng nhập\n";
    echo "     (thường có dạng TÊNMÁY\\SQLEXPRESS).\n";
    echo "   - Vào SQL Server Configuration Manager > SQL Server Network Configuration\n";
    echo "     > Protocols for [instance] > bật TCP/IP (nếu đang Disabled) > khởi động lại dịch vụ SQL Server.\n\n";

    echo "3. Nếu lỗi 'could not find driver':\n";
    echo "   - Xem lại mục 2 phía trên (chưa cài pdo_sqlsrv).\n\n";

    echo "4. Nếu database CoffeeManagerDB chưa tồn tại:\n";
    echo "   - Mở SSMS, chạy lần lượt database/database.sql rồi database/seed.sql\n";
}

echo "</pre>";
