/**
 * FILE: assets/js/tables.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Vẽ sơ đồ bàn dạng lưới (màu theo trạng thái), xử lý thêm/sửa/xóa
 *    bàn, đổi trạng thái nhanh, chuyển bàn, gộp bàn.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/tables.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/tables/list.php, create.php, update.php, delete.php,
 *    transfer.php, merge.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    tables.html load -> tables.js gọi list.php -> vẽ lưới thẻ bàn
 *    Thao tác (thêm/sửa/xóa/đổi trạng thái/chuyển/gộp) -> gọi API tương ứng
 *    -> SQL Server -> JSON -> tables.js load lại sơ đồ
 * -----------------------------------------------------------------------
 */

let tablesListCache = []; // lưu tạm danh sách bàn để tra cứu khi mở modal

// Nhãn hiển thị + icon cho từng trạng thái
const STATUS_LABELS = {
    EMPTY: "Trống",
    IN_USE: "Đang sử dụng",
    RESERVED: "Đã đặt",
    CLEANING: "Đang dọn",
};

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await loadTables();

    document.getElementById("add-table-btn").addEventListener("click", openAddTableModal);
    setupTableFormEvents();
    setupTransferEvents();
    setupMergeEvents();
});

/* ================= TẢI & VẼ SƠ ĐỒ BÀN ================= */

async function loadTables() {
    const grid = document.getElementById("tables-grid");
    grid.innerHTML = `<p class="table-empty">Đang tải...</p>`;

    try {
        const res = await API.get("/backend/api/tables/list.php");
        tablesListCache = res.data;
        renderTablesGrid(tablesListCache);
    } catch (err) {
        grid.innerHTML = `<p class="table-empty">Lỗi tải dữ liệu</p>`;
        showToast(err.message || "Không tải được danh sách bàn", "error");
    }
}

function renderTablesGrid(tables) {
    const grid = document.getElementById("tables-grid");

    if (!tables || tables.length === 0) {
        grid.innerHTML = `<p class="table-empty">Chưa có bàn nào, hãy thêm bàn mới</p>`;
        return;
    }

    grid.innerHTML = tables.map((t) => `
        <div class="table-card card-status-${t.StatusCode}">
            <div class="table-icon">🍽️</div>
            <div class="table-name">${t.TableName}</div>
            <div class="table-capacity">${t.Capacity} chỗ ngồi</div>
            ${t.CurrentOrderTotal ? `<div class="table-order-total">${formatCurrency(t.CurrentOrderTotal)}</div>` : ""}
            <div class="table-status-label">${STATUS_LABELS[t.StatusCode] || t.StatusCode}</div>
            <div class="table-card-actions">
                <button class="btn btn-secondary btn-sm" onclick="onEditTable(${t.TableId})">Sửa</button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteTable(${t.TableId}, '${escapeQuote(t.TableName)}')">Xóa</button>
            </div>
        </div>
    `).join("");
}

function escapeQuote(text) {
    return String(text).replace(/'/g, "\\'");
}

/* ================= MODAL THÊM / SỬA BÀN ================= */

function openAddTableModal() {
    document.getElementById("table-modal-title").textContent = "Thêm bàn";
    document.getElementById("table-form").reset();
    document.getElementById("table-id").value = "";
    document.getElementById("table-status-group").style.display = "none"; // bàn mới luôn mặc định Trống
    openModal("table-modal");
}

function onEditTable(tableId) {
    const table = tablesListCache.find((t) => Number(t.TableId) === Number(tableId));
    if (!table) {
        showToast("Không tìm thấy bàn", "error");
        return;
    }

    document.getElementById("table-modal-title").textContent = "Sửa bàn";
    document.getElementById("table-id").value = table.TableId;
    document.getElementById("table-name").value = table.TableName;
    document.getElementById("table-capacity").value = table.Capacity;
    document.getElementById("table-status").value = table.StatusCode;
    document.getElementById("table-status-group").style.display = "block"; // cho phép đổi trạng thái khi sửa

    openModal("table-modal");
}

function setupTableFormEvents() {
    document.getElementById("table-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const tableId = document.getElementById("table-id").value;
        const submitBtn = document.getElementById("table-submit-btn");
        submitBtn.disabled = true;

        try {
            if (tableId) {
                await API.put("/backend/api/tables/update.php", {
                    tableId: Number(tableId),
                    tableName: document.getElementById("table-name").value.trim(),
                    capacity: Number(document.getElementById("table-capacity").value),
                    statusCode: document.getElementById("table-status").value,
                });
                showToast("Cập nhật bàn thành công", "success");
            } else {
                await API.post("/backend/api/tables/create.php", {
                    tableName: document.getElementById("table-name").value.trim(),
                    capacity: Number(document.getElementById("table-capacity").value),
                });
                showToast("Thêm bàn thành công", "success");
            }
            closeModal("table-modal");
            loadTables();
        } catch (err) {
            showToast(err.message || "Lưu bàn thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

async function onDeleteTable(tableId, tableName) {
    if (!confirm(`Bạn có chắc muốn xóa bàn "${tableName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/tables/delete.php", { tableId });
        showToast(res.message || "Xóa bàn thành công", "success");
        loadTables();
    } catch (err) {
        showToast(err.message || "Xóa bàn thất bại", "error");
    }
}

/* ================= CHUYỂN BÀN ================= */

function setupTransferEvents() {
    document.getElementById("transfer-table-btn").addEventListener("click", () => {
        const fromSelect = document.getElementById("transfer-from");
        const toSelect = document.getElementById("transfer-to");

        const inUseTables = tablesListCache.filter((t) => t.StatusCode === "IN_USE");
        const emptyTables = tablesListCache.filter((t) => t.StatusCode === "EMPTY");

        if (inUseTables.length === 0 || emptyTables.length === 0) {
            showToast("Cần ít nhất 1 bàn đang sử dụng và 1 bàn trống để chuyển bàn", "warning");
            return;
        }

        fromSelect.innerHTML = inUseTables.map((t) => `<option value="${t.TableId}">${t.TableName}</option>`).join("");
        toSelect.innerHTML = emptyTables.map((t) => `<option value="${t.TableId}">${t.TableName}</option>`).join("");

        openModal("transfer-modal");
    });

    document.getElementById("transfer-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const fromTableId = Number(document.getElementById("transfer-from").value);
        const toTableId = Number(document.getElementById("transfer-to").value);

        try {
            await API.post("/backend/api/tables/transfer.php", { fromTableId, toTableId });
            showToast("Chuyển bàn thành công", "success");
            closeModal("transfer-modal");
            loadTables();
        } catch (err) {
            showToast(err.message || "Chuyển bàn thất bại", "error");
        }
    });
}

/* ================= GỘP BÀN ================= */

function setupMergeEvents() {
    document.getElementById("merge-table-btn").addEventListener("click", () => {
        const sourceSelect = document.getElementById("merge-source");
        const targetSelect = document.getElementById("merge-target");

        const inUseTables = tablesListCache.filter((t) => t.StatusCode === "IN_USE");

        if (inUseTables.length < 2) {
            showToast("Cần ít nhất 2 bàn đang sử dụng để gộp bàn", "warning");
            return;
        }

        const optionsHtml = inUseTables.map((t) => `<option value="${t.TableId}">${t.TableName}</option>`).join("");
        sourceSelect.innerHTML = optionsHtml;
        targetSelect.innerHTML = optionsHtml;

        openModal("merge-modal");
    });

    document.getElementById("merge-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const sourceTableId = Number(document.getElementById("merge-source").value);
        const targetTableId = Number(document.getElementById("merge-target").value);

        if (sourceTableId === targetTableId) {
            showToast("Bàn nguồn và bàn đích không được trùng nhau", "warning");
            return;
        }

        try {
            await API.post("/backend/api/tables/merge.php", { sourceTableId, targetTableId });
            showToast("Gộp bàn thành công", "success");
            closeModal("merge-modal");
            loadTables();
        } catch (err) {
            showToast(err.message || "Gộp bàn thất bại", "error");
        }
    });
}
