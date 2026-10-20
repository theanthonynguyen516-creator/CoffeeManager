/**
 * FILE: assets/js/suppliers.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác trang Nhà cung cấp: tải danh sách, tìm
 *    kiếm, thêm/sửa/xóa.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/suppliers.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/suppliers/list.php, create.php, update.php, delete.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    suppliers.html load -> suppliers.js gọi list.php -> render bảng
 *    Thêm/sửa/xóa -> gọi create/update/delete.php -> SQL Server -> JSON
 *    -> suppliers.js load lại bảng
 * -----------------------------------------------------------------------
 */

let suppliersListCache = [];

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadSuppliers();

    setupToolbarEvents();
    setupSupplierFormEvents();
});

/* ================= TẢI DANH SÁCH NHÀ CUNG CẤP ================= */

async function loadSuppliers() {
    const tbody = document.getElementById("suppliers-tbody");
    tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Đang tải...</td></tr>`;

    const search = document.getElementById("search-input").value.trim();
    const params = new URLSearchParams({ includeInactive: "1" });
    if (search) params.append("search", search);

    try {
        const res = await API.get(`/backend/api/suppliers/list.php?${params.toString()}`);
        suppliersListCache = res.data;
        renderSuppliersTable(suppliersListCache);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được danh sách nhà cung cấp", "error");
    }
}

function renderSuppliersTable(suppliers) {
    const tbody = document.getElementById("suppliers-tbody");

    if (!suppliers || suppliers.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Không tìm thấy nhà cung cấp nào</td></tr>`;
        return;
    }

    tbody.innerHTML = suppliers.map((s) => `
        <tr>
            <td class="supplier-name-cell">${s.SupplierName}</td>
            <td>${s.ContactName || "-"}</td>
            <td>${s.Phone || "-"}</td>
            <td>${s.Email || "-"}</td>
            <td><span class="ingredient-count-badge">${s.IngredientCount}</span></td>
            <td>${s.IsActive ? '<span class="badge badge-success">Đang hợp tác</span>' : '<span class="badge badge-muted">Ngừng hợp tác</span>'}</td>
            <td class="actions-cell">
                <button class="btn btn-secondary btn-sm" onclick="onEditSupplier(${s.SupplierId})">Sửa</button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteSupplier(${s.SupplierId}, '${escapeQuote(s.SupplierName)}')">Xóa</button>
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
        searchTimeout = setTimeout(loadSuppliers, 400);
    });

    document.getElementById("add-supplier-btn").addEventListener("click", openAddSupplierModal);
}

/* ================= MODAL THÊM / SỬA NHÀ CUNG CẤP ================= */

function openAddSupplierModal() {
    document.getElementById("supplier-modal-title").textContent = "Thêm nhà cung cấp";
    document.getElementById("supplier-form").reset();
    document.getElementById("supplier-id").value = "";
    openModal("supplier-modal");
}

function onEditSupplier(supplierId) {
    const supplier = suppliersListCache.find((s) => Number(s.SupplierId) === Number(supplierId));
    if (!supplier) {
        showToast("Không tìm thấy nhà cung cấp", "error");
        return;
    }

    document.getElementById("supplier-modal-title").textContent = "Sửa nhà cung cấp";
    document.getElementById("supplier-id").value = supplier.SupplierId;
    document.getElementById("supplier-name").value = supplier.SupplierName;
    document.getElementById("supplier-contact").value = supplier.ContactName || "";
    document.getElementById("supplier-phone").value = supplier.Phone || "";
    document.getElementById("supplier-email").value = supplier.Email || "";
    document.getElementById("supplier-address").value = supplier.Address || "";
    document.getElementById("supplier-status").value = supplier.IsActive ? "1" : "0";

    openModal("supplier-modal");
}

function setupSupplierFormEvents() {
    document.getElementById("supplier-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const supplierId = document.getElementById("supplier-id").value;
        const payload = {
            supplierName: document.getElementById("supplier-name").value.trim(),
            contactName: document.getElementById("supplier-contact").value.trim(),
            phone: document.getElementById("supplier-phone").value.trim(),
            email: document.getElementById("supplier-email").value.trim(),
            address: document.getElementById("supplier-address").value.trim(),
        };

        const submitBtn = document.getElementById("supplier-submit-btn");
        submitBtn.disabled = true;

        try {
            if (supplierId) {
                payload.supplierId = Number(supplierId);
                payload.isActive = document.getElementById("supplier-status").value === "1";
                await API.put("/backend/api/suppliers/update.php", payload);
                showToast("Cập nhật nhà cung cấp thành công", "success");
            } else {
                await API.post("/backend/api/suppliers/create.php", payload);
                showToast("Thêm nhà cung cấp thành công", "success");
            }
            closeModal("supplier-modal");
            loadSuppliers();
        } catch (err) {
            showToast(err.message || "Lưu nhà cung cấp thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= XÓA NHÀ CUNG CẤP ================= */

async function onDeleteSupplier(supplierId, supplierName) {
    if (!confirm(`Bạn có chắc muốn xóa nhà cung cấp "${supplierName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/suppliers/delete.php", { supplierId });
        showToast(res.message || "Xóa nhà cung cấp thành công", "success");
        loadSuppliers();
    } catch (err) {
        showToast(err.message || "Xóa nhà cung cấp thất bại", "error");
    }
}
