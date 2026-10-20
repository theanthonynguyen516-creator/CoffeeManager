/**
 * FILE: assets/js/reports.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý bộ lọc ngày (tùy chỉnh hoặc nhanh 7/30/90 ngày), gọi API báo
 *    cáo, và vẽ toàn bộ giao diện: thẻ tổng quan, biểu đồ doanh thu theo
 *    ngày, bảng sản phẩm bán chạy, doanh thu theo danh mục/nhân viên/
 *    phương thức thanh toán.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/reports.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/reports/summary.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    reports.html load -> reports.js gọi summary.php (mặc định 30 ngày)
 *    -> SQL Server -> JSON tổng hợp -> reports.js render toàn bộ báo cáo
 *    Đổi bộ lọc ngày -> gọi lại summary.php với fromDate/toDate mới
 * -----------------------------------------------------------------------
 */

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();

    setDefaultDateRange(30); // mặc định xem báo cáo 30 ngày gần nhất
    await loadReport();

    setupFilterEvents();
});

/* ================= BỘ LỌC NGÀY ================= */

function setDefaultDateRange(days) {
    const toDate = new Date();
    const fromDate = new Date();
    fromDate.setDate(fromDate.getDate() - (days - 1));

    document.getElementById("from-date-input").value = formatDateForInput(fromDate);
    document.getElementById("to-date-input").value = formatDateForInput(toDate);
}

function formatDateForInput(date) {
    return date.toISOString().split("T")[0]; // yyyy-mm-dd
}

function setupFilterEvents() {
    document.getElementById("apply-filter-btn").addEventListener("click", loadReport);

    document.querySelectorAll("[data-quick-range]").forEach((btn) => {
        btn.addEventListener("click", () => {
            document.querySelectorAll("[data-quick-range]").forEach((b) => b.classList.remove("active"));
            btn.classList.add("active");
            setDefaultDateRange(Number(btn.dataset.quickRange));
            loadReport();
        });
    });
}

/* ================= TẢI BÁO CÁO ================= */

async function loadReport() {
    const fromDate = document.getElementById("from-date-input").value;
    const toDate = document.getElementById("to-date-input").value;

    if (!fromDate || !toDate) {
        showToast("Vui lòng chọn đầy đủ khoảng ngày", "warning");
        return;
    }
    if (fromDate > toDate) {
        showToast('"Từ ngày" phải nhỏ hơn hoặc bằng "Đến ngày"', "warning");
        return;
    }

    try {
        const res = await API.get(`/backend/api/reports/summary.php?fromDate=${fromDate}&toDate=${toDate}`);
        const report = res.data;

        renderOverviewCards(report);
        renderRevenueChart(report.revenueByDay);
        renderTopProducts(report.topProducts);
        renderCategoryRevenue(report.revenueByCategory);
        renderEmployeeRevenue(report.revenueByEmployee);
        renderPaymentMethodRevenue(report.revenueByPaymentMethod);
    } catch (err) {
        showToast(err.message || "Không tải được báo cáo", "error");
    }
}

/* ================= THẺ TỔNG QUAN ================= */

function renderOverviewCards(report) {
    document.getElementById("report-total-revenue").textContent = formatCurrency(report.totalRevenue);
    document.getElementById("report-total-orders").textContent = report.totalOrders;
    document.getElementById("report-avg-order").textContent = formatCurrency(report.averageOrderValue);
}

/* ================= BIỂU ĐỒ DOANH THU THEO NGÀY ================= */

function renderRevenueChart(revenueByDay) {
    const container = document.getElementById("report-revenue-chart");
    container.innerHTML = "";

    if (!revenueByDay || revenueByDay.length === 0) {
        container.innerHTML = `<p class="table-empty">Không có dữ liệu trong khoảng thời gian này</p>`;
        return;
    }

    const maxValue = Math.max(...revenueByDay.map((d) => d.Revenue), 1);

    container.innerHTML = revenueByDay.map((d) => {
        const heightPercent = (d.Revenue / maxValue) * 100;
        return `
            <div class="bar-column">
                <div class="bar-value">${d.Revenue > 0 ? formatCurrency(d.Revenue) : ""}</div>
                <div class="bar" style="height: ${heightPercent}%"></div>
                <div class="bar-label">${formatShortDate(d.OrderDate)}</div>
            </div>
        `;
    }).join("");
}

function formatShortDate(isoDate) {
    const date = new Date(isoDate);
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    return `${day}/${month}`;
}

/* ================= CÁC BẢNG BÁO CÁO CHI TIẾT ================= */

function renderTopProducts(products) {
    const tbody = document.getElementById("top-products-tbody");
    if (!products || products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="table-empty">Không có dữ liệu</td></tr>`;
        return;
    }
    tbody.innerHTML = products.map((p) => `
        <tr><td>${p.ProductName}</td><td>${p.TotalSold}</td><td>${formatCurrency(p.TotalRevenue)}</td></tr>
    `).join("");
}

function renderCategoryRevenue(categories) {
    const tbody = document.getElementById("category-revenue-tbody");
    if (!categories || categories.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="table-empty">Không có dữ liệu</td></tr>`;
        return;
    }
    tbody.innerHTML = categories.map((c) => `
        <tr><td>${c.CategoryName}</td><td>${c.TotalSold}</td><td>${formatCurrency(c.Revenue)}</td></tr>
    `).join("");
}

function renderEmployeeRevenue(employees) {
    const tbody = document.getElementById("employee-revenue-tbody");
    if (!employees || employees.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="table-empty">Không có dữ liệu</td></tr>`;
        return;
    }
    tbody.innerHTML = employees.map((e) => `
        <tr><td>${e.FullName}</td><td>${e.OrderCount}</td><td>${formatCurrency(e.Revenue)}</td></tr>
    `).join("");
}

function translatePaymentMethod(method) {
    const map = { CASH: "Tiền mặt", CARD: "Thẻ", TRANSFER: "Chuyển khoản", MOMO: "Ví MoMo", VNPAY: "VNPay" };
    return map[method] || method;
}

function renderPaymentMethodRevenue(payments) {
    const tbody = document.getElementById("payment-revenue-tbody");
    if (!payments || payments.length === 0) {
        tbody.innerHTML = `<tr><td colspan="3" class="table-empty">Không có dữ liệu</td></tr>`;
        return;
    }
    tbody.innerHTML = payments.map((p) => `
        <tr><td>${translatePaymentMethod(p.PaymentMethod)}</td><td>${p.TransactionCount}</td><td>${formatCurrency(p.TotalAmount)}</td></tr>
    `).join("");
}
