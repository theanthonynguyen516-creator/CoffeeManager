/**
 * FILE: assets/js/inventory.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác trang Kho: tải danh sách nguyên liệu, cảnh
 *    báo tồn kho thấp, thêm/sửa/xóa nguyên liệu, nhập/xuất/điều chỉnh
 *    kho, xem lịch sử giao dịch kho.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/inventory.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/inventory/list.php, create.php, update.php, delete.php,
 *    transaction.php, history.php
 *    backend/api/suppliers/list.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    inventory.html load -> inventory.js gọi list.php -> render bảng + cảnh báo
 *    Nhập/Xuất/Điều chỉnh -> gọi transaction.php -> SQL Server -> JSON
 *    -> load lại bảng kho
 * -----------------------------------------------------------------------
 */

let ingredientsListCache = [];

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadSuppliersIntoDropdown();
    await loadInventory();

    setupToolbarEvents();
    setupIngredientFormEvents();
    setupTransactionFormEvents();
});

/* ================= TẢI NHÀ CUNG CẤP CHO DROPDOWN ================= */

async function loadSuppliersIntoDropdown() {
    try {
        const res = await API.get("/backend/api/suppliers/list.php");
        const select = document.getElementById("ingredient-supplier");
        res.data.forEach((s) => {
            select.insertAdjacentHTML("beforeend", `<option value="${s.SupplierId}">${s.SupplierName}</option>`);
        });
    } catch (err) {
        showToast(err.message || "Không tải được danh sách nhà cung cấp", "error");
    }
}

/* ================= TẢI DANH SÁCH KHO ================= */

async function loadInventory() {
    const tbody = document.getElementById("inventory-tbody");
    tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Đang tải...</td></tr>`;

    const search = document.getElementById("search-input").value.trim();
    const params = new URLSearchParams();
    if (search) params.append("search", search);

    try {
        const res = await API.get(`/backend/api/inventory/list.php?${params.toString()}`);
        ingredientsListCache = res.data;
        renderInventoryTable(ingredientsListCache);
        renderLowStockAlert(ingredientsListCache);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được danh sách kho", "error");
    }
}

function renderLowStockAlert(ingredients) {
    const alertBox = document.getElementById("low-stock-alert");
    const lowStockItems = ingredients.filter((i) => i.IsLowStock);

    if (lowStockItems.length === 0) {
        alertBox.style.display = "none";
        return;
    }

    const names = lowStockItems.map((i) => i.IngredientName).join(", ");
    alertBox.innerHTML = `⚠️ Cảnh báo: ${lowStockItems.length} nguyên liệu sắp hết hàng — ${names}`;
    alertBox.style.display = "block";
}

function renderInventoryTable(ingredients) {
    const tbody = document.getElementById("inventory-tbody");

    if (!ingredients || ingredients.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Chưa có nguyên liệu nào</td></tr>`;
        return;
    }

    tbody.innerHTML = ingredients.map((i) => `
        <tr>
            <td class="ingredient-name-cell">${i.IngredientName}</td>
            <td>${i.Unit}</td>
            <td><span class="stock-value ${i.IsLowStock ? 'low-stock' : ''}">${i.QuantityInStock} ${i.Unit}</span> ${i.IsLowStock ? '<span class="badge badge-danger">Sắp hết</span>' : ''}</td>
            <td>${i.LowStockThreshold} ${i.Unit}</td>
            <td>${i.SupplierName || "-"}</td>
            <td class="actions-cell">
                <button class="btn btn-success btn-sm" onclick="openTransactionModal(${i.IngredientId}, 'IMPORT')">Nhập</button>
                <button class="btn btn-warning btn-sm" onclick="openTransactionModal(${i.IngredientId}, 'EXPORT')">Xuất</button>
                <button class="btn btn-secondary btn-sm" onclick="openTransactionModal(${i.IngredientId}, 'ADJUST')">Điều chỉnh</button>
                <button class="btn btn-secondary btn-sm" onclick="onEditIngredient(${i.IngredientId})">Sửa</button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteIngredient(${i.IngredientId}, '${escapeQuote(i.IngredientName)}')">Xóa</button>
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
        searchTimeout = setTimeout(loadInventory, 400);
    });

    document.getElementById("add-ingredient-btn").addEventListener("click", openAddIngredientModal);
    document.getElementById("view-history-btn").addEventListener("click", () => onViewHistory(null));
}

/* ================= MODAL THÊM / SỬA NGUYÊN LIỆU ================= */

function openAddIngredientModal() {
    document.getElementById("ingredient-modal-title").textContent = "Thêm nguyên liệu";
    document.getElementById("ingredient-form").reset();
    document.getElementById("ingredient-id").value = "";
    document.getElementById("ingredient-quantity-group").style.display = "block"; // chỉ cho nhập tồn ban đầu khi thêm mới
    openModal("ingredient-modal");
}

function onEditIngredient(ingredientId) {
    const ingredient = ingredientsListCache.find((i) => Number(i.IngredientId) === Number(ingredientId));
    if (!ingredient) {
        showToast("Không tìm thấy nguyên liệu", "error");
        return;
    }

    document.getElementById("ingredient-modal-title").textContent = "Sửa nguyên liệu";
    document.getElementById("ingredient-id").value = ingredient.IngredientId;
    document.getElementById("ingredient-name").value = ingredient.IngredientName;
    document.getElementById("ingredient-unit").value = ingredient.Unit;
    document.getElementById("ingredient-threshold").value = ingredient.LowStockThreshold;
    document.getElementById("ingredient-supplier").value = ingredient.SupplierId || "";
    // Ẩn ô "tồn kho ban đầu" khi sửa, vì đổi số tồn phải đi qua Nhập/Xuất/Điều chỉnh (có lịch sử)
    document.getElementById("ingredient-quantity-group").style.display = "none";

    openModal("ingredient-modal");
}

function setupIngredientFormEvents() {
    document.getElementById("ingredient-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const ingredientId = document.getElementById("ingredient-id").value;
        const submitBtn = document.getElementById("ingredient-submit-btn");
        submitBtn.disabled = true;

        try {
            if (ingredientId) {
                await API.put("/backend/api/inventory/update.php", {
                    ingredientId: Number(ingredientId),
                    ingredientName: document.getElementById("ingredient-name").value.trim(),
                    unit: document.getElementById("ingredient-unit").value.trim(),
                    lowStockThreshold: Number(document.getElementById("ingredient-threshold").value) || 0,
                    supplierId: document.getElementById("ingredient-supplier").value || null,
                });
                showToast("Cập nhật nguyên liệu thành công", "success");
            } else {
                await API.post("/backend/api/inventory/create.php", {
                    ingredientName: document.getElementById("ingredient-name").value.trim(),
                    unit: document.getElementById("ingredient-unit").value.trim(),
                    quantityInStock: Number(document.getElementById("ingredient-quantity").value) || 0,
                    lowStockThreshold: Number(document.getElementById("ingredient-threshold").value) || 0,
                    supplierId: document.getElementById("ingredient-supplier").value || null,
                });
                showToast("Thêm nguyên liệu thành công", "success");
            }
            closeModal("ingredient-modal");
            loadInventory();
        } catch (err) {
            showToast(err.message || "Lưu nguyên liệu thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

async function onDeleteIngredient(ingredientId, ingredientName) {
    if (!confirm(`Bạn có chắc muốn xóa nguyên liệu "${ingredientName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/inventory/delete.php", { ingredientId });
        showToast(res.message || "Xóa nguyên liệu thành công", "success");
        loadInventory();
    } catch (err) {
        showToast(err.message || "Xóa nguyên liệu thất bại", "error");
    }
}

/* ================= NHẬP / XUẤT / ĐIỀU CHỈNH KHO ================= */

const TRANSACTION_LABELS = {
    IMPORT: { title: "Nhập kho", quantityLabel: "Số lượng nhập *", btnClass: "btn-success" },
    EXPORT: { title: "Xuất kho", quantityLabel: "Số lượng xuất *", btnClass: "btn-warning" },
    ADJUST: { title: "Điều chỉnh tồn kho", quantityLabel: "Số lượng thực tế (sau điều chỉnh) *", btnClass: "btn-secondary" },
};

function openTransactionModal(ingredientId, transactionType) {
    const ingredient = ingredientsListCache.find((i) => Number(i.IngredientId) === Number(ingredientId));
    if (!ingredient) return;

    const labels = TRANSACTION_LABELS[transactionType];
    document.getElementById("transaction-modal-title").textContent = labels.title;
    document.getElementById("transaction-quantity-label").textContent = labels.quantityLabel;
    document.getElementById("transaction-ingredient-id").value = ingredientId;
    document.getElementById("transaction-type").value = transactionType;
    document.getElementById("transaction-ingredient-name").textContent = ingredient.IngredientName;
    document.getElementById("transaction-current-stock").textContent = `${ingredient.QuantityInStock} ${ingredient.Unit}`;
    document.getElementById("transaction-quantity").value = "";
    document.getElementById("transaction-note").value = "";

    openModal("transaction-modal");
}

function setupTransactionFormEvents() {
    document.getElementById("transaction-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const ingredientId = Number(document.getElementById("transaction-ingredient-id").value);
        const transactionType = document.getElementById("transaction-type").value;
        const quantity = Number(document.getElementById("transaction-quantity").value);
        const note = document.getElementById("transaction-note").value.trim();

        const submitBtn = document.getElementById("transaction-submit-btn");
        submitBtn.disabled = true;

        try {
            await API.post("/backend/api/inventory/transaction.php", {
                ingredientId, transactionType, quantity, note,
            });
            showToast("Ghi nhận giao dịch kho thành công", "success");
            closeModal("transaction-modal");
            loadInventory();
        } catch (err) {
            showToast(err.message || "Ghi nhận giao dịch kho thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= LỊCH SỬ KHO ================= */

async function onViewHistory(ingredientId) {
    openModal("history-modal");
    const tbody = document.getElementById("history-tbody");
    tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Đang tải...</td></tr>`;

    const url = ingredientId
        ? `/backend/api/inventory/history.php?ingredientId=${ingredientId}`
        : `/backend/api/inventory/history.php`;

    try {
        const res = await API.get(url);
        renderHistoryTable(res.data);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Lỗi tải lịch sử</td></tr>`;
        showToast(err.message || "Không tải được lịch sử kho", "error");
    }
}

const TRANSACTION_TYPE_LABELS = { IMPORT: "Nhập kho", EXPORT: "Xuất kho", ADJUST: "Điều chỉnh" };

function renderHistoryTable(transactions) {
    const tbody = document.getElementById("history-tbody");

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="table-empty">Chưa có giao dịch kho nào</td></tr>`;
        return;
    }

    tbody.innerHTML = transactions.map((t) => `
        <tr>
            <td>${t.IngredientName}</td>
            <td><span class="badge transaction-type-badge-${t.TransactionType}">${TRANSACTION_TYPE_LABELS[t.TransactionType]}</span></td>
            <td>${t.Quantity > 0 ? "+" : ""}${t.Quantity} ${t.Unit}</td>
            <td class="text-muted">${t.Note || "-"}</td>
            <td>${t.EmployeeName || "-"}</td>
            <td>${formatDateTime(t.CreatedAt)}</td>
        </tr>
    `).join("");
}
