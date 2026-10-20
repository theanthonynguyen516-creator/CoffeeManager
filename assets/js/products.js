/**
 * FILE: assets/js/products.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác của trang Sản phẩm: tải danh sách, tìm kiếm,
 *    lọc danh mục, phân trang, mở modal thêm/sửa, submit form, xóa.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/products.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/products/list.php, create.php, update.php, delete.php
 *    backend/api/categories/list.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    products.html load -> products.js gọi list.php -> render bảng
 *    Người dùng bấm "Thêm/Sửa" -> mở modal -> submit -> gọi create/update.php
 *    -> SQL Server -> JSON -> products.js load lại danh sách + đóng modal
 * -----------------------------------------------------------------------
 */

// ===== BIẾN TRẠNG THÁI CỦA TRANG =====
let currentPage = 1;
const PAGE_SIZE = 10;
let categoriesCache = []; // lưu tạm danh mục để đổ vào dropdown & hiển thị tên

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadCategoriesIntoFilters();
    await loadProducts();

    setupToolbarEvents();
    setupProductFormEvents();
});

/* ================= TẢI DANH MỤC (dùng cho dropdown lọc + dropdown form) ================= */

async function loadCategoriesIntoFilters() {
    try {
        const res = await API.get("/backend/api/categories/list.php");
        categoriesCache = res.data;

        const filterSelect = document.getElementById("category-filter");
        const formSelect = document.getElementById("product-category");

        categoriesCache.forEach((cat) => {
            filterSelect.insertAdjacentHTML("beforeend", `<option value="${cat.CategoryId}">${cat.CategoryName}</option>`);
            formSelect.insertAdjacentHTML("beforeend", `<option value="${cat.CategoryId}">${cat.CategoryName}</option>`);
        });
    } catch (err) {
        showToast(err.message || "Không tải được danh mục", "error");
    }
}

/* ================= TẢI DANH SÁCH SẢN PHẨM ================= */

async function loadProducts() {
    const tbody = document.getElementById("products-tbody");
    tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Đang tải...</td></tr>`;

    const search = document.getElementById("search-input").value.trim();
    const categoryId = document.getElementById("category-filter").value;

    const params = new URLSearchParams({
        page: currentPage,
        pageSize: PAGE_SIZE,
    });
    if (search) params.append("search", search);
    if (categoryId) params.append("categoryId", categoryId);

    try {
        const res = await API.get(`/backend/api/products/list.php?${params.toString()}`);
        renderProductsTable(res.data.items);
        renderPagination(res.data.page, res.data.totalPages);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được sản phẩm", "error");
    }
}

function renderProductsTable(products) {
    const tbody = document.getElementById("products-tbody");

    if (!products || products.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Không tìm thấy sản phẩm nào</td></tr>`;
        return;
    }

    tbody.innerHTML = products.map((p) => `
        <tr>
            <td>
                ${p.ImageUrl
                    ? `<img src="${p.ImageUrl}" class="product-thumb" alt="${p.ProductName}">`
                    : `<div class="product-thumb-placeholder">☕</div>`}
            </td>
            <td class="product-name-cell">${p.ProductName}</td>
            <td>${p.CategoryName}</td>
            <td>${formatCurrency(p.SellPrice)}</td>
            <td>${formatCurrency(p.CostPrice)}</td>
            <td>${p.IsActive ? '<span class="badge badge-success">Đang bán</span>' : '<span class="badge badge-muted">Ngừng bán</span>'}</td>
            <td class="actions-cell">
                <button class="btn btn-secondary btn-sm" onclick="onEditProduct(${p.ProductId})">Sửa</button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteProduct(${p.ProductId}, '${escapeQuote(p.ProductName)}')">Xóa</button>
            </td>
        </tr>
    `).join("");
}

/** Thoát dấu nháy đơn để tránh vỡ chuỗi HTML khi gọi onclick(...) */
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
    prevBtn.onclick = () => { currentPage = page - 1; loadProducts(); };
    container.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        if (i === page) btn.classList.add("active");
        btn.onclick = () => { currentPage = i; loadProducts(); };
        container.appendChild(btn);
    }

    const nextBtn = document.createElement("button");
    nextBtn.textContent = "›";
    nextBtn.disabled = page >= totalPages;
    nextBtn.onclick = () => { currentPage = page + 1; loadProducts(); };
    container.appendChild(nextBtn);
}

/* ================= THANH CÔNG CỤ: TÌM KIẾM, LỌC, NÚT THÊM ================= */

function setupToolbarEvents() {
    let searchTimeout;
    document.getElementById("search-input").addEventListener("input", () => {
        clearTimeout(searchTimeout);
        // debounce 400ms để tránh gọi API liên tục khi đang gõ
        searchTimeout = setTimeout(() => { currentPage = 1; loadProducts(); }, 400);
    });

    document.getElementById("category-filter").addEventListener("change", () => {
        currentPage = 1;
        loadProducts();
    });

    document.getElementById("add-product-btn").addEventListener("click", openAddProductModal);
}

/* ================= MODAL THÊM / SỬA SẢN PHẨM ================= */

function openAddProductModal() {
    document.getElementById("product-modal-title").textContent = "Thêm sản phẩm";
    document.getElementById("product-form").reset();
    document.getElementById("product-id").value = "";
    openModal("product-modal");
}

async function onEditProduct(productId) {
    try {
        // Trang chỉ có API list.php (không có API "get 1 sản phẩm"), nên lấy từ
        // danh sách hiện có trên bảng thay vì gọi thêm API. Đơn giản, dễ hiểu
        // cho người mới học, tránh tạo thêm nhiều endpoint không cần thiết.
        const res = await API.get(`/backend/api/products/list.php?page=1&pageSize=100`);
        const product = res.data.items.find((p) => Number(p.ProductId) === Number(productId));
        if (!product) {
            showToast("Không tìm thấy sản phẩm", "error");
            return;
        }

        document.getElementById("product-modal-title").textContent = "Sửa sản phẩm";
        document.getElementById("product-id").value = product.ProductId;
        document.getElementById("product-name").value = product.ProductName;
        document.getElementById("product-category").value = product.CategoryId;
        document.getElementById("product-status").value = product.IsActive ? "1" : "0";
        document.getElementById("product-sell-price").value = product.SellPrice;
        document.getElementById("product-cost-price").value = product.CostPrice;
        document.getElementById("product-image").value = product.ImageUrl || "";
        document.getElementById("product-description").value = product.Description || "";

        openModal("product-modal");
    } catch (err) {
        showToast(err.message || "Không tải được sản phẩm", "error");
    }
}

function setupProductFormEvents() {
    document.getElementById("product-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const productId = document.getElementById("product-id").value;
        const payload = {
            productName: document.getElementById("product-name").value.trim(),
            categoryId: Number(document.getElementById("product-category").value),
            isActive: document.getElementById("product-status").value === "1",
            sellPrice: Number(document.getElementById("product-sell-price").value),
            costPrice: Number(document.getElementById("product-cost-price").value) || 0,
            imageUrl: document.getElementById("product-image").value.trim(),
            description: document.getElementById("product-description").value.trim(),
        };

        const submitBtn = document.getElementById("product-submit-btn");
        submitBtn.disabled = true;

        try {
            if (productId) {
                payload.productId = Number(productId);
                await API.put("/backend/api/products/update.php", payload);
                showToast("Cập nhật sản phẩm thành công", "success");
            } else {
                await API.post("/backend/api/products/create.php", payload);
                showToast("Thêm sản phẩm thành công", "success");
            }
            closeModal("product-modal");
            loadProducts();
        } catch (err) {
            showToast(err.message || "Lưu sản phẩm thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= XÓA SẢN PHẨM ================= */

async function onDeleteProduct(productId, productName) {
    if (!confirm(`Bạn có chắc muốn xóa sản phẩm "${productName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/products/delete.php", { productId });
        showToast(res.message || "Xóa sản phẩm thành công", "success");
        loadProducts();
    } catch (err) {
        showToast(err.message || "Xóa sản phẩm thất bại", "error");
    }
}
