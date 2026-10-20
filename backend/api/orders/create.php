<?php
/**
 * FILE: backend/api/orders/create.php
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ nghiệp vụ tạo đơn hàng VÀ thanh toán cùng lúc (mô hình
 *    quán cà phê: khách gọi món -> thu ngân bấm thanh toán ngay tại POS).
 *    Gồm: tính lại giá từ database (KHÔNG tin giá client gửi lên để tránh
 *    gian lận), tính giảm giá, tạo Order + OrderDetails + Payment, và cập
 *    nhật trạng thái bàn.
 *
 * 2. File này giao tiếp với file nào?
 *    Được gọi từ: assets/js/pos.js (khi bấm nút "Thanh toán")
 *
 * 3. File này gọi API nào?
 *    Đây chính là 1 API, không gọi API khác.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    pos.js (fetch POST {tableId, customerId, items:[{productId,quantity,note}],
 *             discountType, discountValue, paymentMethod, amountPaid})
 *    --> create.php --> SQL Server:
 *        1) Lấy giá thật của từng sản phẩm từ bảng Products
 *        2) INSERT Orders
 *        3) INSERT OrderDetails (từng món)
 *        4) INSERT Payments
 *        5) UPDATE Tables (giải phóng bàn nếu có chọn bàn)
 *    --> trả JSON {orderId, orderCode, totalAmount, changeAmount}
 *    --> pos.js hiển thị hóa đơn & xóa giỏ hàng
 *
 *    GHI CHÚ QUAN TRỌNG: Vì database (mục 19) chưa có bảng "công thức"
 *    (Product -> Ingredients + định lượng), hệ thống KHÔNG thể tự động
 *    trừ kho nguyên liệu khi bán 1 sản phẩm (vd: bán 1 ly cà phê sữa thì
 *    không biết chính xác trừ bao nhiêu gram cà phê, bao nhiêu ml sữa).
 *    Việc trừ/nhập kho được thực hiện THỦ CÔNG ở trang Kho (Giai đoạn 14)
 *    thông qua bảng InventoryTransactions. Đây là lựa chọn đơn giản hóa
 *    phù hợp với đồ án sinh viên; muốn tự động hoàn toàn cần thêm bảng
 *    "công thức pha chế" (Recipe/ProductIngredients) - có thể mở rộng sau.
 * -----------------------------------------------------------------------
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/validation.php';
require_once __DIR__ . '/../../middleware/auth.php';

$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Phương thức không hợp lệ, chỉ chấp nhận POST', 405);
}

$input = getJsonInput();
$errors = validateRequired($input, ['items', 'paymentMethod']);
if (!empty($errors)) {
    jsonError('Dữ liệu không hợp lệ', 400, $errors);
}

$items = $input['items'];
if (!is_array($items) || count($items) === 0) {
    jsonError('Giỏ hàng đang trống, không thể thanh toán', 400);
}

$allowedPaymentMethods = ['CASH', 'CARD', 'TRANSFER', 'MOMO', 'VNPAY'];
if (!in_array($input['paymentMethod'], $allowedPaymentMethods, true)) {
    jsonError('Phương thức thanh toán không hợp lệ', 400);
}

$tableId = !empty($input['tableId']) ? (int)$input['tableId'] : null;
$customerId = !empty($input['customerId']) ? (int)$input['customerId'] : null;
$discountType = $input['discountType'] ?? null;   // 'PERCENT' | 'AMOUNT' | null
$discountValue = isset($input['discountValue']) ? (float)$input['discountValue'] : 0;
$note = isset($input['note']) ? sanitizeString($input['note']) : null;

$pdo = getDbConnection();

// Tìm EmployeeId tương ứng với user đang đăng nhập (bảng Employees liên kết 1-1 với Users)
$empStmt = $pdo->prepare("SELECT EmployeeId FROM Employees WHERE UserId = :userId");
$empStmt->execute(['userId' => $user['UserId']]);
$employee = $empStmt->fetch();
if (!$employee) {
    jsonError('Tài khoản của bạn chưa được gắn với hồ sơ nhân viên, không thể tạo đơn hàng', 400);
}
$employeeId = $employee['EmployeeId'];

// Lấy giá THẬT của từng sản phẩm trong giỏ từ database (không tin giá do client gửi lên)
$productIds = array_map(fn($item) => (int)$item['productId'], $items);
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$productStmt = $pdo->prepare("SELECT ProductId, ProductName, SellPrice FROM Products WHERE ProductId IN ($placeholders) AND IsActive = 1");
$productStmt->execute($productIds);
$productsById = [];
foreach ($productStmt->fetchAll() as $p) {
    $productsById[$p['ProductId']] = $p;
}

$subTotal = 0;
$validatedItems = [];
foreach ($items as $item) {
    $productId = (int)$item['productId'];
    $quantity = (int)($item['quantity'] ?? 0);

    if ($quantity <= 0) {
        jsonError('Số lượng món phải lớn hơn 0', 400);
    }
    if (!isset($productsById[$productId])) {
        jsonError('Có sản phẩm trong giỏ hàng không tồn tại hoặc đã ngừng bán', 400);
    }

    $unitPrice = (float)$productsById[$productId]['SellPrice'];
    $subTotal += $unitPrice * $quantity;

    $validatedItems[] = [
        'productId' => $productId,
        'quantity'  => $quantity,
        'unitPrice' => $unitPrice,
        'note'      => isset($item['note']) ? sanitizeString($item['note']) : null,
    ];
}

// Tính tiền giảm giá
$discountAmount = 0;
if ($discountType === 'PERCENT' && $discountValue > 0) {
    $discountAmount = $subTotal * min($discountValue, 100) / 100;
} elseif ($discountType === 'AMOUNT' && $discountValue > 0) {
    $discountAmount = min($discountValue, $subTotal);
}
$totalAmount = $subTotal - $discountAmount;

$amountPaid = isset($input['amountPaid']) ? (float)$input['amountPaid'] : $totalAmount;
if ($amountPaid < $totalAmount) {
    jsonError('Số tiền khách trả không đủ so với tổng hóa đơn', 400);
}
$changeAmount = $amountPaid - $totalAmount;

// Sinh mã đơn hàng duy nhất, vd: ORD20260822143501842
$orderCode = 'ORD' . date('YmdHis') . rand(10, 99);

try {
    $pdo->beginTransaction();

    // 1. Tạo đơn hàng, trạng thái PAID vì POS thanh toán ngay tại quầy
    $orderStmt = $pdo->prepare("
        INSERT INTO Orders (OrderCode, TableId, EmployeeId, CustomerId, SubTotal, DiscountAmount, TotalAmount, OrderStatus, Note)
        OUTPUT INSERTED.OrderId
        VALUES (:orderCode, :tableId, :employeeId, :customerId, :subTotal, :discountAmount, :totalAmount, 'PAID', :note)
    ");
    $orderStmt->execute([
        'orderCode'      => $orderCode,
        'tableId'        => $tableId,
        'employeeId'     => $employeeId,
        'customerId'     => $customerId,
        'subTotal'       => $subTotal,
        'discountAmount' => $discountAmount,
        'totalAmount'    => $totalAmount,
        'note'           => $note,
    ]);
    $orderId = $orderStmt->fetch()['OrderId'];

    // 2. Thêm từng món vào OrderDetails
    $detailStmt = $pdo->prepare("
        INSERT INTO OrderDetails (OrderId, ProductId, Quantity, UnitPrice, Note)
        VALUES (:orderId, :productId, :quantity, :unitPrice, :note)
    ");
    foreach ($validatedItems as $item) {
        $detailStmt->execute([
            'orderId'   => $orderId,
            'productId' => $item['productId'],
            'quantity'  => $item['quantity'],
            'unitPrice' => $item['unitPrice'],
            'note'      => $item['note'],
        ]);
    }

    // 3. Ghi nhận thanh toán
    $paymentStmt = $pdo->prepare("
        INSERT INTO Payments (OrderId, PaymentMethod, AmountPaid)
        VALUES (:orderId, :paymentMethod, :amountPaid)
    ");
    $paymentStmt->execute([
        'orderId'       => $orderId,
        'paymentMethod' => $input['paymentMethod'],
        'amountPaid'    => $amountPaid,
    ]);

    // 4. Nếu có chọn bàn: đơn đã thanh toán xong -> giải phóng bàn về "Trống"
    if ($tableId !== null) {
        $emptyStatusId = $pdo->query("SELECT TableStatusId FROM TableStatuses WHERE StatusCode = 'EMPTY'")->fetch()['TableStatusId'];
        $pdo->prepare("UPDATE Tables SET TableStatusId = :statusId WHERE TableId = :tableId")
            ->execute(['statusId' => $emptyStatusId, 'tableId' => $tableId]);
    }

    // 5. Nếu có khách hàng: cộng điểm tích lũy (1 điểm mỗi 10.000đ, có thể tùy chỉnh)
    if ($customerId !== null) {
        $pointsEarned = (int)floor($totalAmount / 10000);
        $pdo->prepare("UPDATE Customers SET LoyaltyPoints = LoyaltyPoints + :points WHERE CustomerId = :id")
            ->execute(['points' => $pointsEarned, 'id' => $customerId]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Tạo đơn hàng thất bại, vui lòng thử lại', 500);
}

jsonSuccess([
    'orderId'       => $orderId,
    'orderCode'     => $orderCode,
    'subTotal'      => $subTotal,
    'discountAmount'=> $discountAmount,
    'totalAmount'   => $totalAmount,
    'amountPaid'    => $amountPaid,
    'changeAmount'  => $changeAmount,
], 'Thanh toán thành công', 201);
