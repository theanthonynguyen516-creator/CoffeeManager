<?php
/**
 * FILE: backend/utils/validation.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Chứa các hàm kiểm tra dữ liệu đầu vào (validation) dùng chung,
 *    tránh việc mỗi API tự viết lại logic kiểm tra rời rạc.
 *
 * 2. File này giao tiếp với file nào?
 *    Được require bởi các file trong backend/api/**.php (create.php, update.php...)
 *
 * 3. File này gọi API nào?
 *    Không gọi API nào, chỉ là hàm hỗ trợ (helper).
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    backend/api/*.php --(gọi validateRequired, validateNumeric...)--> trả về true/false
 * -----------------------------------------------------------------------
 */

/**
 * Kiểm tra các field bắt buộc có tồn tại và không rỗng.
 *
 * @param array $data   Dữ liệu đầu vào (thường lấy từ getJsonInput())
 * @param array $fields Danh sách tên field bắt buộc
 * @return array        Danh sách lỗi (rỗng nếu hợp lệ)
 */
function validateRequired(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $errors[$field] = "Trường '$field' không được để trống";
        }
    }
    return $errors;
}

/**
 * Kiểm tra một giá trị có phải số và >= 0 hay không (dùng cho giá tiền, số lượng...).
 */
function validateNonNegativeNumber($value): bool
{
    return is_numeric($value) && (float)$value >= 0;
}

/**
 * Kiểm tra định dạng email cơ bản.
 */
function validateEmail(?string $email): bool
{
    if (empty($email)) return true; // email có thể null/optional
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Làm sạch chuỗi đầu vào (loại bỏ khoảng trắng thừa, thẻ HTML nguy hiểm).
 */
function sanitizeString(?string $value): string
{
    if ($value === null) return '';
    return trim(strip_tags($value));
}
