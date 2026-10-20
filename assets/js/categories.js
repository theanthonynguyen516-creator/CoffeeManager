/**
 * FILE: assets/js/categories.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác của trang Danh mục: tải danh sách, tìm
 *    kiếm, mở modal thêm/sửa, submit form, xóa.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/categories.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/categories/list.php, create.php, update.php, delete.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    categories.html load -> categories.js gọi list.php -> render bảng
 *    Người dùng thêm/sửa/xóa -> gọi create/update/delete.php -> SQL Server
 *    -> JSON -> categories.js load lại danh sách + đóng modal
 * -----------------------------------------------------------------------
 */

let categoriesListCache = []; // lưu tạm danh sách để tra cứu nhanh khi mở modal sửa

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadCategories();

    setupToolbarEvents();
    setupCategoryFormEvents();
});

/* ================= TẢI DANH SÁCH DANH MỤC ================= */

async function loadCategories() {
    const tbody = document.getElementById("categories-tbody");
    tbody.innerHTML = `<tr><td colspan="5" class="table-empty">Đang tải...</td></tr>`;

    const search = document.getElementById("search-input").value.trim();
    const params = new URLSearchParams({ includeInactive: "1" });
    if (search) params.append("search", search);

    try {
        const res = await API.get(`/backend/api/categories/list.php?${params.toString()}`);
        categoriesListCache = res.data;
        renderCategoriesTable(categoriesListCache);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được danh mục", "error");
    }
}

function renderCategoriesTable(categories) {
    const tbody = document.getElementById("categories-tbody");

    if (!categories || categories.length === 0) {
        tbody.innerHTML = `<tr><td colspan="5" class="table-empty">Không tìm thấy danh mục nào</td></tr>`;
        return;
    }

    tbody.innerHTML = categories.map((c) => `
        <tr>
            <td class="category-name-cell">${c.CategoryName}</td>
            <td class="text-muted">${c.Description || "-"}</td>
            <td><span class="product-count-badge">${c.ProductCount}</span></td>
            <td>${c.IsActive ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-muted">Ngừng hoạt động</span>'}</td>
            <td class="actions-cell">
                <button class="btn btn-secondary btn-sm" onclick="onEditCategory(${c.CategoryId})">Sửa</button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteCategory(${c.CategoryId}, '${escapeQuote(c.CategoryName)}')">Xóa</button>
            </td>
        </tr>
    `).join("");
}

function escapeQuote(text) {
    return String(text).replace(/'/g, "\\'");
}

/* ================= THANH CÔNG CỤ ================= */

function setupToolbarEvents() {
    let searchTimeout;
    document.getElementById("search-input").addEventListener("input", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadCategories, 400); // debounce 400ms
    });

    document.getElementById("add-category-btn").addEventListener("click", openAddCategoryModal);
}

/* ================= MODAL THÊM / SỬA DANH MỤC ================= */

function openAddCategoryModal() {
    document.getElementById("category-modal-title").textContent = "Thêm danh mục";
    document.getElementById("category-form").reset();
    document.getElementById("category-id").value = "";
    openModal("category-modal");
}

function onEditCategory(categoryId) {
    const category = categoriesListCache.find((c) => Number(c.CategoryId) === Number(categoryId));
    if (!category) {
        showToast("Không tìm thấy danh mục", "error");
        return;
    }

    document.getElementById("category-modal-title").textContent = "Sửa danh mục";
    document.getElementById("category-id").value = category.CategoryId;
    document.getElementById("category-name").value = category.CategoryName;
    document.getElementById("category-description").value = category.Description || "";
    document.getElementById("category-status").value = category.IsActive ? "1" : "0";

    openModal("category-modal");
}

function setupCategoryFormEvents() {
    document.getElementById("category-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const categoryId = document.getElementById("category-id").value;
        const payload = {
            categoryName: document.getElementById("category-name").value.trim(),
            description: document.getElementById("category-description").value.trim(),
            isActive: document.getElementById("category-status").value === "1",
        };

        const submitBtn = document.getElementById("category-submit-btn");
        submitBtn.disabled = true;

        try {
            if (categoryId) {
                payload.categoryId = Number(categoryId);
                await API.put("/backend/api/categories/update.php", payload);
                showToast("Cập nhật danh mục thành công", "success");
            } else {
                await API.post("/backend/api/categories/create.php", payload);
                showToast("Thêm danh mục thành công", "success");
            }
            closeModal("category-modal");
            loadCategories();
        } catch (err) {
            showToast(err.message || "Lưu danh mục thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= XÓA DANH MỤC ================= */

async function onDeleteCategory(categoryId, categoryName) {
    if (!confirm(`Bạn có chắc muốn xóa danh mục "${categoryName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/categories/delete.php", { categoryId });
        showToast(res.message || "Xóa danh mục thành công", "success");
        loadCategories();
    } catch (err) {
        // Trường hợp thường gặp: danh mục còn sản phẩm bên trong -> backend đã trả lỗi 400 rõ ràng
        showToast(err.message || "Xóa danh mục thất bại", "error");
    }
}
