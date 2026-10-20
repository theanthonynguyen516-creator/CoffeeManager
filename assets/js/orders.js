/**
 * FILE: assets/js/orders.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác của trang Đơn hàng: tải danh sách, tìm
 *    kiếm, lọc ngày/trạng thái, phân trang, xem chi tiết, hủy đơn, in hóa đơn.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/orders.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/orders/list.php, detail.php, cancel.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    orders.html load -> orders.js gọi list.php -> render bảng
 *    Xem chi tiết -> gọi detail.php -> render modal + lưu tạm để in
 *    Hủy đơn -> gọi cancel.php -> SQL Server -> JSON -> load lại bảng
 * -----------------------------------------------------------------------
 */

let currentPage = 1;
const PAGE_SIZE = 10;
let currentOrderDetail = null; // lưu tạm đơn đang xem để dùng cho chức năng in hóa đơn

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadOrders();
    setupFilterEvents();
});

/* ================= TẢI DANH SÁCH ĐƠN HÀNG ================= */

async function loadOrders() {
    const tbody = document.getElementById("orders-tbody");
    tbody.innerHTML = `<tr><td colspan="8" class="table-empty">Đang tải...</td></tr>`;

    const params = new URLSearchParams({ page: currentPage, pageSize: PAGE_SIZE });

    const search = document.getElementById("search-input").value.trim();
    const fromDate = document.getElementById("from-date-input").value;
    const toDate = document.getElementById("to-date-input").value;
    const status = document.getElementById("status-filter").value;

    if (search) params.append("search", search);
    if (fromDate) params.append("fromDate", fromDate);
    if (toDate) params.append("toDate", toDate);
    if (status) params.append("status", status);

    try {
        const res = await API.get(`/backend/api/orders/list.php?${params.toString()}`);
        renderOrdersTable(res.data.items);
        renderPagination(res.data.page, res.data.totalPages);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="8" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được đơn hàng", "error");
    }
}

function renderOrdersTable(orders) {
    const tbody = document.getElementById("orders-tbody");

    if (!orders || orders.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="table-empty">Không tìm thấy đơn hàng nào</td></tr>`;
        return;
    }

    tbody.innerHTML = orders.map((o) => `
        <tr>
            <td>${o.OrderCode}</td>
            <td>${o.TableName || "Mang về"}</td>
            <td>${o.EmployeeName || "-"}</td>
            <td>${o.CustomerName || "Khách lẻ"}</td>
            <td>${formatCurrency(o.TotalAmount)}</td>
            <td>${renderOrderStatusBadge(o.OrderStatus)}</td>
            <td>${formatDateTime(o.CreatedAt)}</td>
            <td class="actions-cell">
                <button class="btn btn-secondary btn-sm" onclick="onViewOrderDetail(${o.OrderId})">Xem</button>
                ${o.OrderStatus !== 'CANCELLED'
                    ? `<button class="btn btn-danger btn-sm" onclick="onCancelOrder(${o.OrderId}, '${o.OrderCode}')">Hủy</button>`
                    : ""}
            </td>
        </tr>
    `).join("");
}

function renderOrderStatusBadge(status) {
    const map = {
        PENDING:   { label: "Đang xử lý", cls: "badge-warning" },
        PAID:      { label: "Đã thanh toán", cls: "badge-success" },
        CANCELLED: { label: "Đã hủy", cls: "badge-danger" },
    };
    const info = map[status] || { label: status, cls: "badge-muted" };
    return `<span class="badge ${info.cls}">${info.label}</span>`;
}

/* ================= PHÂN TRANG ================= */

function renderPagination(page, totalPages) {
    const container = document.getElementById("pagination-container");
    container.innerHTML = "";
    if (totalPages <= 1) return;

    const prevBtn = document.createElement("button");
    prevBtn.textContent = "‹";
    prevBtn.disabled = page <= 1;
    prevBtn.onclick = () => { currentPage = page - 1; loadOrders(); };
    container.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        if (i === page) btn.classList.add("active");
        btn.onclick = () => { currentPage = i; loadOrders(); };
        container.appendChild(btn);
    }

    const nextBtn = document.createElement("button");
    nextBtn.textContent = "›";
    nextBtn.disabled = page >= totalPages;
    nextBtn.onclick = () => { currentPage = page + 1; loadOrders(); };
    container.appendChild(nextBtn);
}

/* ================= BỘ LỌC ================= */

function setupFilterEvents() {
    let searchTimeout;
    document.getElementById("search-input").addEventListener("input", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadOrders(); }, 400);
    });

    ["from-date-input", "to-date-input", "status-filter"].forEach((id) => {
        document.getElementById(id).addEventListener("change", () => { currentPage = 1; loadOrders(); });
    });

    document.getElementById("reset-filter-btn").addEventListener("click", () => {
        document.getElementById("search-input").value = "";
        document.getElementById("from-date-input").value = "";
        document.getElementById("to-date-input").value = "";
        document.getElementById("status-filter").value = "";
        currentPage = 1;
        loadOrders();
    });
}

/* ================= XEM CHI TIẾT ĐƠN HÀNG ================= */

async function onViewOrderDetail(orderId) {
    openModal("order-detail-modal");
    const content = document.getElementById("order-detail-content");
    content.innerHTML = `<p class="table-empty">Đang tải...</p>`;

    try {
        const res = await API.get(`/backend/api/orders/detail.php?orderId=${orderId}`);
        currentOrderDetail = res.data;
        renderOrderDetail(currentOrderDetail);
    } catch (err) {
        content.innerHTML = `<p class="table-empty">Lỗi tải chi tiết đơn hàng</p>`;
        showToast(err.message || "Không tải được chi tiết đơn hàng", "error");
    }
}

function renderOrderDetail(order) {
    const content = document.getElementById("order-detail-content");
    const payment = order.payments && order.payments[0];

    content.innerHTML = `
        <div class="order-info-grid">
            <div><div class="label">Mã đơn</div><div class="value">${order.OrderCode}</div></div>
            <div><div class="label">Trạng thái</div><div class="value">${renderOrderStatusBadge(order.OrderStatus)}</div></div>
            <div><div class="label">Bàn</div><div class="value">${order.TableName || "Mang về"}</div></div>
            <div><div class="label">Nhân viên</div><div class="value">${order.EmployeeName || "-"}</div></div>
            <div><div class="label">Khách hàng</div><div class="value">${order.CustomerName || "Khách lẻ"}</div></div>
            <div><div class="label">Thời gian</div><div class="value">${formatDateTime(order.CreatedAt)}</div></div>
            ${payment ? `<div><div class="label">Thanh toán</div><div class="value">${translatePaymentMethod(payment.PaymentMethod)}</div></div>` : ""}
        </div>

        <table class="order-items-table">
            <thead>
                <tr><th>Món</th><th>SL</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th></tr>
            </thead>
            <tbody>
                ${order.items.map((item) => `
                    <tr>
                        <td>${item.ProductName}${item.Note ? `<br><span class="text-muted" style="font-size:11px;">${item.Note}</span>` : ""}</td>
                        <td>${item.Quantity}</td>
                        <td class="text-right">${formatCurrency(item.UnitPrice)}</td>
                        <td class="text-right">${formatCurrency(item.LineTotal)}</td>
                    </tr>
                `).join("")}
            </tbody>
        </table>

        <div class="order-totals">
            <div class="pos-summary-row"><span>Tạm tính</span><span>${formatCurrency(order.SubTotal)}</span></div>
            <div class="pos-summary-row"><span>Giảm giá</span><span>${formatCurrency(order.DiscountAmount)}</span></div>
            <div class="pos-summary-row pos-total-row"><span>Tổng cộng</span><span>${formatCurrency(order.TotalAmount)}</span></div>
        </div>
    `;
}

function translatePaymentMethod(method) {
    const map = { CASH: "Tiền mặt", CARD: "Thẻ", TRANSFER: "Chuyển khoản", MOMO: "Ví MoMo", VNPAY: "VNPay" };
    return map[method] || method;
}

/* ================= HỦY ĐƠN HÀNG ================= */

async function onCancelOrder(orderId, orderCode) {
    const reason = prompt(`Nhập lý do hủy đơn "${orderCode}" (có thể bỏ trống):`);
    if (reason === null) return; // người dùng bấm Cancel trên hộp thoại prompt

    try {
        const res = await API.post("/backend/api/orders/cancel.php", { orderId, reason });
        showToast(res.message || "Hủy đơn hàng thành công", "success");
        loadOrders();
    } catch (err) {
        showToast(err.message || "Hủy đơn hàng thất bại", "error");
    }
}

/* ================= IN HÓA ĐƠN ================= */

/**
 * In hóa đơn của đơn hàng đang xem trong modal. Cách làm: đổ dữ liệu vào
 * #invoice-print-area (ẩn khỏi giao diện thường), rồi gọi window.print().
 * CSS trong orders.css (@media print) sẽ ẩn toàn bộ trang, chỉ hiện vùng này.
 */
function printCurrentInvoice() {
    if (!currentOrderDetail) return;
    const order = currentOrderDetail;

    const invoiceHtml = `
        <h2>CoffeeManager</h2>
        <p style="text-align:center;">Hóa đơn bán hàng</p>
        <hr>
        <div class="invoice-line"><span>Mã đơn:</span><span>${order.OrderCode}</span></div>
        <div class="invoice-line"><span>Thời gian:</span><span>${formatDateTime(order.CreatedAt)}</span></div>
        <div class="invoice-line"><span>Bàn:</span><span>${order.TableName || "Mang về"}</span></div>
        <div class="invoice-line"><span>Nhân viên:</span><span>${order.EmployeeName || "-"}</span></div>
        <hr>
        ${order.items.map((item) => `
            <div class="invoice-line">
                <span>${item.ProductName} x${item.Quantity}</span>
                <span>${formatCurrency(item.Quantity * item.UnitPrice)}</span>
            </div>
        `).join("")}
        <hr>
        <div class="invoice-line"><span>Tạm tính:</span><span>${formatCurrency(order.SubTotal)}</span></div>
        <div class="invoice-line"><span>Giảm giá:</span><span>${formatCurrency(order.DiscountAmount)}</span></div>
        <div class="invoice-line" style="font-weight:bold;"><span>TỔNG CỘNG:</span><span>${formatCurrency(order.TotalAmount)}</span></div>
        <hr>
        <p style="text-align:center;">Cảm ơn quý khách!</p>
    `;

    document.getElementById("invoice-print-area").innerHTML = invoiceHtml;
    window.print();
}
