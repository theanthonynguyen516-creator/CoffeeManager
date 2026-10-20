/**
 * FILE: assets/js/dashboard.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Lấy dữ liệu thống kê và render toàn bộ giao diện trang Dashboard
 *    (index.html): các thẻ số liệu, biểu đồ doanh thu 7 ngày, bảng đơn
 *    hàng gần đây, danh sách sản phẩm bán chạy.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã được nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong index.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/dashboard/statistics.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    Trang load xong --> dashboard.js gọi API.get(statistics.php)
 *    --> PHP truy vấn SQL Server --> trả JSON
 *    --> dashboard.js đổ dữ liệu vào các phần tử HTML tương ứng
 * -----------------------------------------------------------------------
 */

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();   // hiển thị tên/vai trò trên Header (từ common.js)
    await loadDashboardStats(); // tải số liệu thống kê
});

async function loadDashboardStats() {
    try {
        const res = await API.get("/backend/api/dashboard/statistics.php");
        const stats = res.data;

        renderStatCards(stats);
        renderRevenueChart(stats.revenueChart);
        renderRecentOrders(stats.recentOrders);
        renderBestSellingProducts(stats.bestSellingProducts);
    } catch (err) {
        showToast(err.message || "Không tải được dữ liệu dashboard", "error");
    }
}

/** Đổ dữ liệu vào 6 thẻ Card thống kê tổng quan */
function renderStatCards(stats) {
    document.getElementById("stat-today-revenue").textContent = formatCurrency(stats.todayRevenue);
    document.getElementById("stat-month-revenue").textContent = formatCurrency(stats.monthRevenue);
    document.getElementById("stat-order-count").textContent = stats.todayOrderCount;
    document.getElementById("stat-customer-count").textContent = stats.totalCustomers;
    document.getElementById("stat-product-count").textContent = stats.totalProducts;
    document.getElementById("stat-tables").textContent = `${stats.tablesInUse} / ${stats.tablesEmpty}`;
}

/** Vẽ biểu đồ cột doanh thu 7 ngày bằng div thuần (không cần thư viện ngoài) */
function renderRevenueChart(revenueChart) {
    const container = document.getElementById("revenue-chart");
    container.innerHTML = "";

    const dates = Object.keys(revenueChart);
    const values = Object.values(revenueChart);
    const maxValue = Math.max(...values, 1); // tránh chia cho 0

    dates.forEach((date, index) => {
        const value = values[index];
        const heightPercent = (value / maxValue) * 100;

        const column = document.createElement("div");
        column.className = "bar-column";
        column.innerHTML = `
            <div class="bar-value">${value > 0 ? formatCurrency(value) : ""}</div>
            <div class="bar" style="height: ${heightPercent}%"></div>
            <div class="bar-label">${formatShortDate(date)}</div>
        `;
        container.appendChild(column);
    });
}

/** Định dạng ngày ngắn gọn cho biểu đồ, vd: "22/08" */
function formatShortDate(isoDate) {
    const date = new Date(isoDate);
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    return `${day}/${month}`;
}

/** Đổ dữ liệu vào bảng đơn hàng gần đây */
function renderRecentOrders(orders) {
    const tbody = document.getElementById("recent-orders-tbody");

    if (!orders || orders.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Chưa có đơn hàng nào</td></tr>`;
        return;
    }

    tbody.innerHTML = orders.map((order) => `
        <tr>
            <td>${order.OrderCode}</td>
            <td>${order.TableName || "Mang về"}</td>
            <td>${order.EmployeeName || "-"}</td>
            <td>${formatCurrency(order.TotalAmount)}</td>
            <td>${renderOrderStatusBadge(order.OrderStatus)}</td>
            <td>${formatDateTime(order.CreatedAt)}</td>
        </tr>
    `).join("");
}

/** Trả về HTML badge màu tương ứng trạng thái đơn hàng */
function renderOrderStatusBadge(status) {
    const map = {
        PENDING:   { label: "Đang xử lý", cls: "badge-warning" },
        PAID:      { label: "Đã thanh toán", cls: "badge-success" },
        CANCELLED: { label: "Đã hủy", cls: "badge-danger" },
    };
    const info = map[status] || { label: status, cls: "badge-muted" };
    return `<span class="badge ${info.cls}">${info.label}</span>`;
}

/** Đổ dữ liệu vào danh sách sản phẩm bán chạy */
function renderBestSellingProducts(products) {
    const list = document.getElementById("best-selling-list");

    if (!products || products.length === 0) {
        list.innerHTML = `<li class="table-empty">Chưa có dữ liệu bán hàng</li>`;
        return;
    }

    list.innerHTML = products.map((p) => `
        <li>
            <div>
                <div class="product-name">${p.ProductName}</div>
                <div class="product-sold">Đã bán: ${p.TotalSold}</div>
            </div>
            <div>${formatCurrency(p.TotalRevenue)}</div>
        </li>
    `).join("");
}
