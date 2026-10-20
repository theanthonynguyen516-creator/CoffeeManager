/**
 * FILE: assets/js/auth.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý sự kiện submit của form đăng nhập trên pages/login.html.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã được nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/login.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/auth/login.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    Người dùng nhập username/password --> submit form
 *    --> auth.js gọi API.post("/backend/api/auth/login.php", {...})
 *    --> PHP kiểm tra & tạo session --> trả JSON
 *    --> nếu thành công: JS chuyển hướng vào ../index.html
 *    --> nếu thất bại: JS hiện toast lỗi
 * -----------------------------------------------------------------------
 */

document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("login-form");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        const username = document.getElementById("username").value.trim();
        const password = document.getElementById("password").value;
        const submitBtn = document.getElementById("login-submit-btn");

        if (!username || !password) {
            showToast("Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu", "warning");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading-spinner"></span> Đang đăng nhập...';

        try {
            await API.post("/backend/api/auth/login.php", { username, password });
            showToast("Đăng nhập thành công!", "success");
            window.location.href = "../index.html";
        } catch (err) {
            showToast(err.message || "Đăng nhập thất bại", "error");
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = "Đăng nhập";
        }
    });
});
