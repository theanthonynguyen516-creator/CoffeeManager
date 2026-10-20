/**
 * FILE: assets/js/customers.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác trang Khách hàng: tải danh sách, tìm kiếm,
 *    phân trang, thêm/sửa/xóa, xem lịch sử mua hàng.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/customers.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/customers/list.php, create.php, update.php, delete.php, history.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    customers.html load -> customers.js gọi list.php -> render bảng
 *    Thêm/sửa/xóa -> gọi create/update/delete.php -> SQL Server -> JSON
 *    Xem lịch sử -> gọi history.php -> render modal lịch sử
 * -----------------------------------------------------------------------
 */

let currentPage = 1;
const PAGE_SIZE = 10;
let customersListCache = []; // lưu tạm danh sách trang hiện tại để tra cứu khi mở modal sửa

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadCustomers();

    setupToolbarEvents();
    setupCustomerFormEvents();
});

/* ================= TẢI DANH SÁCH KHÁCH HÀNG ================= */

async function loadCustomers() {
    const tbody = document.getElementById("customers-tbody");
    tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Đang tải...</td></tr>`;

    const search = document.getElementById("search-input").value.trim();
    const params = new URLSearchParams({ page: currentPage, pageSize: PAGE_SIZE });
    if (search) params.append("search", search);

    try {
        const res = await API.get(`/backend/api/customers/list.php?${params.toString()}`);
        customersListCache = res.data.items;
        renderCustomersTable(customersListCache);
        renderPagination(res.data.page, res.data.totalPages);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được khách hàng", "error");
    }
}

function renderCustomersTable(customers) {
    const tbody = document.getElementById("customers-tbody");

    if (!customers || customers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Không tìm thấy khách hàng nào</td></tr>`;
        return;
    }

    tbody.innerHTML = customers.map((c) => `
        <tr>
            <td class="customer-name-cell">${c.FullName}</td>
            <td>${c.Phone || "-"}</td>
            <td>${c.Email || "-"}</td>
            <td><span class="loyalty-points-badge">⭐ ${c.LoyaltyPoints}</span></td>
            <td>${c.TotalOrders}</td>
            <td>${formatCurrency(c.TotalSpent)}</td>
            <td class="actions-cell">
                <button class="btn btn-secondary btn-sm" onclick="onViewHistory(${c.CustomerId}, '${escapeQuote(c.FullName)}')">Lịch sử</button>
                <button class="btn btn-secondary btn-sm" onclick="onEditCustomer(${c.CustomerId})">Sửa</button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteCustomer(${c.CustomerId}, '${escapeQuote(c.FullName)}')">Xóa</button>
            </td>
        </tr>
    `).join("");
}

function escapeQuote(text) {
    return String(text).replace(/'/g, "\\'");
}

/* ================= PHÂN TRANG ================= */

function renderPagination(page, totalPages) {
    const container = document.getElementById("pagination-container");
    container.innerHTML = "";
    if (totalPages <= 1) return;

    const prevBtn = document.createElement("button");
    prevBtn.textContent = "‹";
    prevBtn.disabled = page <= 1;
    prevBtn.onclick = () => { currentPage = page - 1; loadCustomers(); };
    container.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        if (i === page) btn.classList.add("active");
        btn.onclick = () => { currentPage = i; loadCustomers(); };
        container.appendChild(btn);
    }

    const nextBtn = document.createElement("button");
    nextBtn.textContent = "›";
    nextBtn.disabled = page >= totalPages;
    nextBtn.onclick = () => { currentPage = page + 1; loadCustomers(); };
    container.appendChild(nextBtn);
}

/* ================= THANH CÔNG CỤ ================= */

function setupToolbarEvents() {
    let searchTimeout;
    document.getElementById("search-input").addEventListener("input", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadCustomers(); }, 400);
    });

    document.getElementById("add-customer-btn").addEventListener("click", openAddCustomerModal);
}

/* ================= MODAL THÊM / SỬA KHÁCH HÀNG ================= */

function openAddCustomerModal() {
    document.getElementById("customer-modal-title").textContent = "Thêm khách hàng";
    document.getElementById("customer-form").reset();
    document.getElementById("customer-id").value = "";
    document.getElementById("customer-points-group").style.display = "none"; // khách mới luôn 0 điểm
    openModal("customer-modal");
}

function onEditCustomer(customerId) {
    const customer = customersListCache.find((c) => Number(c.CustomerId) === Number(customerId));
    if (!customer) {
        showToast("Không tìm thấy khách hàng", "error");
        return;
    }

    document.getElementById("customer-modal-title").textContent = "Sửa khách hàng";
    document.getElementById("customer-id").value = customer.CustomerId;
    document.getElementById("customer-name").value = customer.FullName;
    document.getElementById("customer-phone").value = customer.Phone || "";
    document.getElementById("customer-email").value = customer.Email || "";
    document.getElementById("customer-points").value = customer.LoyaltyPoints;
    document.getElementById("customer-points-group").style.display = "block"; // cho phép chỉnh tay khi sửa

    openModal("customer-modal");
}

function setupCustomerFormEvents() {
    document.getElementById("customer-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const customerId = document.getElementById("customer-id").value;
        const payload = {
            fullName: document.getElementById("customer-name").value.trim(),
            phone: document.getElementById("customer-phone").value.trim(),
            email: document.getElementById("customer-email").value.trim(),
        };

        const submitBtn = document.getElementById("customer-submit-btn");
        submitBtn.disabled = true;

        try {
            if (customerId) {
                payload.customerId = Number(customerId);
                payload.loyaltyPoints = Number(document.getElementById("customer-points").value) || 0;
                await API.put("/backend/api/customers/update.php", payload);
                showToast("Cập nhật khách hàng thành công", "success");
            } else {
                await API.post("/backend/api/customers/create.php", payload);
                showToast("Thêm khách hàng thành công", "success");
            }
            closeModal("customer-modal");
            loadCustomers();
        } catch (err) {
            showToast(err.message || "Lưu khách hàng thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= XÓA KHÁCH HÀNG ================= */

async function onDeleteCustomer(customerId, customerName) {
    if (!confirm(`Bạn có chắc muốn xóa khách hàng "${customerName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/customers/delete.php", { customerId });
        showToast(res.message || "Xóa khách hàng thành công", "success");
        loadCustomers();
    } catch (err) {
        showToast(err.message || "Xóa khách hàng thất bại", "error");
    }
}

/* ================= LỊCH SỬ MUA HÀNG ================= */

async function onViewHistory(customerId, customerName) {
    document.getElementById("history-modal-title").textContent = `Lịch sử mua hàng - ${customerName}`;
    openModal("history-modal");

    const tbody = document.getElementById("history-tbody");
    tbody.innerHTML = `<tr><td colspan="5" class="table-empty">Đang tải...</td></tr>`;

    try {
        const res = await API.get(`/backend/api/customers/history.php?customerId=${customerId}`);
        renderHistoryTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty">Lỗi tải lịch sử</td></tr>`;
        showToast(err.message || "Không tải được lịch sử mua hàng", "error");
    }
}

function renderHistoryTable(orders) {
    const tbody = document.getElementById("history-tbody");

    if (!orders || orders.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty">Khách hàng chưa có đơn hàng nào</td></tr>`;
        return;
    }

    const statusMap = {
        PENDING:   { label: "Đang xử lý", cls: "badge-warning" },
        PAID:      { label: "Đã thanh toán", cls: "badge-success" },
        CANCELLED: { label: "Đã hủy", cls: "badge-danger" },
    };

    tbody.innerHTML = orders.map((o) => {
        const status = statusMap[o.OrderStatus] || { label: o.OrderStatus, cls: "badge-muted" };
        return `
            <tr>
                <td>${o.OrderCode}</td>
                <td>${o.TableName || "Mang về"}</td>
                <td>${formatCurrency(o.TotalAmount)}</td>
                <td><span class="badge ${status.cls}">${status.label}</span></td>
                <td>${formatDateTime(o.CreatedAt)}</td>
            </tr>
        `;
    }).join("");
}
