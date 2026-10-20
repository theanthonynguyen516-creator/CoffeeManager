/**
 * FILE: assets/js/common.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Chứa các hàm tiện ích dùng CHUNG cho mọi trang: hiện toast thông báo,
 *    mở/đóng modal, thu gọn sidebar, format tiền tệ/ngày giờ, xử lý nút
 *    đăng xuất trên Header. Được nhúng ở MỌI trang HTML.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js đã được nhúng trước đó (để gọi logout).
 *    Được các file .js khác (products.js, pos.js...) gọi hàm showToast(),
 *    openModal(), formatCurrency()...
 *
 * 3. File này gọi API nào?
 *    backend/api/auth/logout.php (khi bấm nút Đăng xuất)
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    Người dùng bấm nút "Đăng xuất" trên Header
 *    --> common.js gọi API.post("/backend/api/auth/logout.php")
 *    --> PHP hủy session --> JS chuyển hướng về trang đăng nhập
 * -----------------------------------------------------------------------
 */

/* ================= TOAST THÔNG BÁO ================= */

/**
 * Hiển thị 1 thông báo nổi (toast) ở góc phải màn hình.
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} type
 */
function showToast(message, type = "info") {
    let container = document.getElementById("toast-container");
    if (!container) {
        container = document.createElement("div");
        container.id = "toast-container";
        document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

/* ================= MODAL ================= */

/** Mở modal theo id (vd: openModal('product-modal')) */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove("hidden");
}

/** Đóng modal theo id */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add("hidden");
}

/* ================= SIDEBAR ================= */

/** Thu gọn / mở rộng sidebar (desktop) hoặc ẩn/hiện (mobile) */
function toggleSidebar() {
    const sidebar = document.getElementById("app-sidebar");
    if (!sidebar) return;

    if (window.innerWidth <= 1024) {
        sidebar.classList.toggle("mobile-open");
    } else {
        sidebar.classList.toggle("collapsed");
    }
}

/* ================= FORMAT DỮ LIỆU ================= */

/** Định dạng số thành tiền VNĐ, vd: 25000 -> "25.000 ₫" */
function formatCurrency(amount) {
    const number = Number(amount) || 0;
    return number.toLocaleString("vi-VN") + " ₫";
}

/** Định dạng ngày giờ kiểu Việt Nam, vd: "22/08/2026 14:30" */
function formatDateTime(isoString) {
    if (!isoString) return "";
    const date = new Date(isoString);
    if (isNaN(date.getTime())) return isoString;
    return date.toLocaleString("vi-VN", {
        day: "2-digit", month: "2-digit", year: "numeric",
        hour: "2-digit", minute: "2-digit",
    });
}

/* ================= HEADER: THÔNG TIN USER & ĐĂNG XUẤT ================= */

/**
 * Gọi khi trang vừa load: lấy thông tin user đang đăng nhập (backend/api/auth/me.php)
 * và hiển thị lên Header. Nếu chưa đăng nhập, api.js sẽ tự redirect về login.html.
 */
async function loadCurrentUser() {
    try {
        const res = await API.get("/backend/api/auth/me.php");
        const user = res.data;

        const nameEl = document.getElementById("current-user-name");
        const roleEl = document.getElementById("current-user-role");
        const avatarEl = document.getElementById("current-user-avatar");

        if (nameEl) nameEl.textContent = user.fullName || user.username;
        if (roleEl) roleEl.textContent = user.roleName;
        if (avatarEl) avatarEl.textContent = (user.fullName || user.username).charAt(0).toUpperCase();

        return user;
    } catch (err) {
        // api.js đã tự động redirect về trang login khi lỗi 401
        console.error("Không lấy được thông tin người dùng:", err);
    }
}

/** Gắn sự kiện cho nút đăng xuất (id="logout-btn") - gọi 1 lần khi trang load */
function setupLogoutButton() {
    const btn = document.getElementById("logout-btn");
    if (!btn) return;

    btn.addEventListener("click", async () => {
        try {
            await API.post("/backend/api/auth/logout.php");
            window.location.href = getLoginPageUrl();
        } catch (err) {
            showToast(err.message || "Đăng xuất thất bại", "error");
        }
    });
}

/** Đánh dấu menu sidebar tương ứng trang hiện tại là "active" (viền vàng) */
function highlightActiveMenu() {
    const currentPage = window.location.pathname.split("/").pop();
    document.querySelectorAll(".sidebar-menu a").forEach((link) => {
        const linkPage = link.getAttribute("href").split("/").pop();
        if (linkPage === currentPage) link.classList.add("active");
    });
}

// Tự động chạy khi bất kỳ trang nào (đã nhúng common.js) load xong
document.addEventListener("DOMContentLoaded", () => {
    setupLogoutButton();
    highlightActiveMenu();

    const toggleBtn = document.getElementById("sidebar-toggle-btn");
    if (toggleBtn) toggleBtn.addEventListener("click", toggleSidebar);
});
