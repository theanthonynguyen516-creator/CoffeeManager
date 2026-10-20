/**
 * FILE: assets/js/settings.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý 3 form độc lập của trang Cài đặt: Thông tin quán (chỉ Admin
 *    sửa được, người khác chỉ xem), Tài khoản cá nhân, Đổi mật khẩu.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/settings.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/settings/get.php, update.php
 *    backend/api/auth/me.php, update-profile.php, change-password.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    settings.html load -> settings.js gọi settings/get.php + auth/me.php
 *    -> đổ dữ liệu vào 3 form
 *    Lưu form nào -> gọi API tương ứng của form đó -> SQL Server -> JSON
 * -----------------------------------------------------------------------
 */

document.addEventListener("DOMContentLoaded", async () => {
    const currentUser = await loadCurrentUser();

    await loadShopInfo(currentUser);
    loadProfileInfo(currentUser);

    setupShopInfoFormEvents();
    setupProfileFormEvents();
    setupPasswordFormEvents();
});

/* ================= THÔNG TIN QUÁN ================= */

async function loadShopInfo(currentUser) {
    const isAdmin = currentUser && currentUser.roleName === "Admin";
    const hint = document.getElementById("shop-info-hint");
    const submitBtn = document.getElementById("shop-info-submit-btn");

    // Chỉ Admin mới được sửa; người khác vẫn xem được nhưng form bị khóa (disabled)
    if (!isAdmin) {
        hint.textContent = "Chỉ Quản trị viên (Admin) mới có quyền chỉnh sửa thông tin quán.";
        document.querySelectorAll("#shop-info-form input").forEach((el) => (el.disabled = true));
        submitBtn.style.display = "none";
    } else {
        hint.textContent = "Thông tin này sẽ được sử dụng trên hóa đơn và các báo cáo.";
    }

    try {
        const res = await API.get("/backend/api/settings/get.php");
        const settings = res.data;

        document.getElementById("shop-name").value = settings.ShopName || "";
        document.getElementById("shop-address").value = settings.ShopAddress || "";
        document.getElementById("shop-phone").value = settings.ShopPhone || "";
        document.getElementById("shop-logo").value = settings.ShopLogo || "";
    } catch (err) {
        showToast(err.message || "Không tải được thông tin quán", "error");
    }
}

function setupShopInfoFormEvents() {
    document.getElementById("shop-info-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const submitBtn = document.getElementById("shop-info-submit-btn");
        submitBtn.disabled = true;

        try {
            await API.put("/backend/api/settings/update.php", {
                ShopName: document.getElementById("shop-name").value.trim(),
                ShopAddress: document.getElementById("shop-address").value.trim(),
                ShopPhone: document.getElementById("shop-phone").value.trim(),
                ShopLogo: document.getElementById("shop-logo").value.trim(),
            });
            showToast("Cập nhật thông tin quán thành công", "success");
        } catch (err) {
            showToast(err.message || "Cập nhật thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= TÀI KHOẢN CÁ NHÂN ================= */

function loadProfileInfo(currentUser) {
    if (!currentUser) return;
    document.getElementById("profile-username").value = currentUser.username;
    document.getElementById("profile-fullname").value = currentUser.fullName || "";
    // auth/me.php hiện chưa trả email/phone, nên để trống cho người dùng tự nhập/sửa
}

function setupProfileFormEvents() {
    document.getElementById("profile-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const submitBtn = document.getElementById("profile-submit-btn");
        submitBtn.disabled = true;

        try {
            await API.put("/backend/api/auth/update-profile.php", {
                fullName: document.getElementById("profile-fullname").value.trim(),
                email: document.getElementById("profile-email").value.trim(),
                phone: document.getElementById("profile-phone").value.trim(),
            });
            showToast("Cập nhật thông tin cá nhân thành công", "success");
            // Cập nhật lại tên hiển thị trên Header ngay lập tức
            document.getElementById("current-user-name").textContent = document.getElementById("profile-fullname").value.trim();
        } catch (err) {
            showToast(err.message || "Cập nhật thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= ĐỔI MẬT KHẨU ================= */

function setupPasswordFormEvents() {
    document.getElementById("password-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const currentPassword = document.getElementById("current-password").value;
        const newPassword = document.getElementById("new-password").value;
        const confirmPassword = document.getElementById("confirm-password").value;

        if (newPassword !== confirmPassword) {
            showToast("Mật khẩu mới nhập lại không khớp", "warning");
            return;
        }
        if (newPassword.length < 6) {
            showToast("Mật khẩu mới phải có ít nhất 6 ký tự", "warning");
            return;
        }

        const submitBtn = document.getElementById("password-submit-btn");
        submitBtn.disabled = true;

        try {
            await API.put("/backend/api/auth/change-password.php", { currentPassword, newPassword });
            showToast("Đổi mật khẩu thành công", "success");
            document.getElementById("password-form").reset();
        } catch (err) {
            showToast(err.message || "Đổi mật khẩu thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}
