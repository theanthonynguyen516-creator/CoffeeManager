/**
 * FILE: assets/js/employees.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ tương tác trang Nhân viên: tải danh sách, tìm kiếm,
 *    lọc vai trò, phân trang, thêm/sửa nhân viên (tài khoản + hồ sơ),
 *    khóa/mở tài khoản, xóa.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/employees.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/employees/list.php, roles.php, create.php, update.php, delete.php
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    employees.html load -> employees.js gọi list.php + roles.php -> render bảng
 *    Thêm/sửa/xóa/khóa -> gọi API tương ứng -> SQL Server -> JSON -> load lại
 * -----------------------------------------------------------------------
 */

let currentPage = 1;
const PAGE_SIZE = 10;
let employeesListCache = [];
let rolesCache = [];

document.addEventListener("DOMContentLoaded", async () => {
    const currentUser = await loadCurrentUser();

    await loadRoles();
    await loadEmployees();

    setupToolbarEvents();
    setupEmployeeFormEvents();
});

/* ================= TẢI DANH SÁCH VAI TRÒ (dùng cho dropdown lọc + form) ================= */

async function loadRoles() {
    try {
        const res = await API.get("/backend/api/employees/roles.php");
        rolesCache = res.data;

        const filterSelect = document.getElementById("role-filter");
        const formSelect = document.getElementById("employee-role");

        rolesCache.forEach((role) => {
            filterSelect.insertAdjacentHTML("beforeend", `<option value="${role.RoleId}">${role.RoleName}</option>`);
            formSelect.insertAdjacentHTML("beforeend", `<option value="${role.RoleId}">${role.RoleName}</option>`);
        });
    } catch (err) {
        showToast(err.message || "Không tải được danh sách vai trò", "error");
    }
}

/* ================= TẢI DANH SÁCH NHÂN VIÊN ================= */

async function loadEmployees() {
    const tbody = document.getElementById("employees-tbody");
    tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Đang tải...</td></tr>`;

    const search = document.getElementById("search-input").value.trim();
    const roleId = document.getElementById("role-filter").value;

    const params = new URLSearchParams({ page: currentPage, pageSize: PAGE_SIZE });
    if (search) params.append("search", search);
    if (roleId) params.append("roleId", roleId);

    try {
        const res = await API.get(`/backend/api/employees/list.php?${params.toString()}`);
        employeesListCache = res.data.items;
        renderEmployeesTable(employeesListCache);
        renderPagination(res.data.page, res.data.totalPages);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Lỗi tải dữ liệu</td></tr>`;
        showToast(err.message || "Không tải được danh sách nhân viên", "error");
    }
}

function renderEmployeesTable(employees) {
    const tbody = document.getElementById("employees-tbody");

    if (!employees || employees.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="table-empty">Không tìm thấy nhân viên nào</td></tr>`;
        return;
    }

    tbody.innerHTML = employees.map((e) => `
        <tr>
            <td class="employee-name-cell">${e.FullName}</td>
            <td>${e.Username}</td>
            <td><span class="badge role-badge-${e.RoleName}">${e.RoleName}</span></td>
            <td>${e.Position || "-"}</td>
            <td>${e.Phone || "-"}</td>
            <td>${e.IsActive ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-danger">Đã khóa</span>'}</td>
            <td class="actions-cell">
                <button class="btn btn-secondary btn-sm" onclick="onEditEmployee(${e.EmployeeId})">Sửa</button>
                <button class="btn ${e.IsActive ? 'btn-warning' : 'btn-success'} btn-sm" onclick="onToggleLock(${e.EmployeeId}, ${e.IsActive})">
                    ${e.IsActive ? "Khóa" : "Mở khóa"}
                </button>
                <button class="btn btn-danger btn-sm" onclick="onDeleteEmployee(${e.EmployeeId}, ${e.UserId}, '${escapeQuote(e.FullName)}')">Xóa</button>
            </td>
        </tr>
    `).join("");
}

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
    prevBtn.onclick = () => { currentPage = page - 1; loadEmployees(); };
    container.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        if (i === page) btn.classList.add("active");
        btn.onclick = () => { currentPage = i; loadEmployees(); };
        container.appendChild(btn);
    }

    const nextBtn = document.createElement("button");
    nextBtn.textContent = "›";
    nextBtn.disabled = page >= totalPages;
    nextBtn.onclick = () => { currentPage = page + 1; loadEmployees(); };
    container.appendChild(nextBtn);
}

/* ================= THANH CÔNG CỤ ================= */

function setupToolbarEvents() {
    let searchTimeout;
    document.getElementById("search-input").addEventListener("input", () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => { currentPage = 1; loadEmployees(); }, 400);
    });

    document.getElementById("role-filter").addEventListener("change", () => { currentPage = 1; loadEmployees(); });
    document.getElementById("add-employee-btn").addEventListener("click", openAddEmployeeModal);
}

/* ================= MODAL THÊM / SỬA NHÂN VIÊN ================= */

function openAddEmployeeModal() {
    document.getElementById("employee-modal-title").textContent = "Thêm nhân viên";
    document.getElementById("employee-form").reset();
    document.getElementById("employee-id").value = "";
    document.getElementById("employee-user-id").value = "";

    // Khi thêm mới: hiện ô tài khoản/mật khẩu, ẩn ô trạng thái (mặc định Hoạt động)
    document.getElementById("employee-account-fields").style.display = "flex";
    document.getElementById("employee-username").required = true;
    document.getElementById("employee-password").required = true;
    document.getElementById("employee-status-group").style.display = "none";

    openModal("employee-modal");
}

function onEditEmployee(employeeId) {
    const employee = employeesListCache.find((e) => Number(e.EmployeeId) === Number(employeeId));
    if (!employee) {
        showToast("Không tìm thấy nhân viên", "error");
        return;
    }

    document.getElementById("employee-modal-title").textContent = "Sửa nhân viên";
    document.getElementById("employee-id").value = employee.EmployeeId;
    document.getElementById("employee-user-id").value = employee.UserId;
    document.getElementById("employee-fullname").value = employee.FullName;
    document.getElementById("employee-role").value = employee.RoleId;
    document.getElementById("employee-phone").value = employee.Phone || "";
    document.getElementById("employee-email").value = employee.Email || "";
    document.getElementById("employee-position").value = employee.Position || "";
    document.getElementById("employee-salary").value = employee.Salary || "";
    document.getElementById("employee-hiredate").value = employee.HireDate ? employee.HireDate.substring(0, 10) : "";
    document.getElementById("employee-address").value = employee.Address || "";
    document.getElementById("employee-status").value = employee.IsActive ? "1" : "0";

    // Khi sửa: KHÔNG cho đổi username/password ở đây (đơn giản hóa, tránh rủi ro bảo mật),
    // chỉ hiện ô trạng thái tài khoản
    document.getElementById("employee-account-fields").style.display = "none";
    document.getElementById("employee-username").required = false;
    document.getElementById("employee-password").required = false;
    document.getElementById("employee-status-group").style.display = "block";

    openModal("employee-modal");
}

function setupEmployeeFormEvents() {
    document.getElementById("employee-form").addEventListener("submit", async (e) => {
        e.preventDefault();

        const employeeId = document.getElementById("employee-id").value;
        const submitBtn = document.getElementById("employee-submit-btn");
        submitBtn.disabled = true;

        const commonPayload = {
            fullName: document.getElementById("employee-fullname").value.trim(),
            roleId: Number(document.getElementById("employee-role").value),
            phone: document.getElementById("employee-phone").value.trim(),
            email: document.getElementById("employee-email").value.trim(),
            position: document.getElementById("employee-position").value.trim(),
            salary: Number(document.getElementById("employee-salary").value) || null,
            hireDate: document.getElementById("employee-hiredate").value,
            address: document.getElementById("employee-address").value.trim(),
        };

        try {
            if (employeeId) {
                await API.put("/backend/api/employees/update.php", {
                    ...commonPayload,
                    employeeId: Number(employeeId),
                    userId: Number(document.getElementById("employee-user-id").value),
                    isActive: document.getElementById("employee-status").value === "1",
                });
                showToast("Cập nhật nhân viên thành công", "success");
            } else {
                await API.post("/backend/api/employees/create.php", {
                    ...commonPayload,
                    username: document.getElementById("employee-username").value.trim(),
                    password: document.getElementById("employee-password").value,
                });
                showToast("Thêm nhân viên thành công", "success");
            }
            closeModal("employee-modal");
            loadEmployees();
        } catch (err) {
            showToast(err.message || "Lưu nhân viên thất bại", "error");
        } finally {
            submitBtn.disabled = false;
        }
    });
}

/* ================= KHÓA / MỞ KHÓA TÀI KHOẢN ================= */

async function onToggleLock(employeeId, isCurrentlyActive) {
    const employee = employeesListCache.find((e) => Number(e.EmployeeId) === Number(employeeId));
    if (!employee) return;

    const actionLabel = isCurrentlyActive ? "khóa" : "mở khóa";
    if (!confirm(`Bạn có chắc muốn ${actionLabel} tài khoản "${employee.FullName}"?`)) return;

    try {
        await API.put("/backend/api/employees/update.php", {
            employeeId: employee.EmployeeId,
            userId: employee.UserId,
            fullName: employee.FullName,
            roleId: employee.RoleId,
            phone: employee.Phone,
            email: employee.Email,
            position: employee.Position,
            salary: employee.Salary,
            hireDate: employee.HireDate ? employee.HireDate.substring(0, 10) : null,
            address: employee.Address,
            isActive: !isCurrentlyActive,
        });
        showToast(`Đã ${actionLabel} tài khoản thành công`, "success");
        loadEmployees();
    } catch (err) {
        showToast(err.message || `Không thể ${actionLabel} tài khoản`, "error");
    }
}

/* ================= XÓA NHÂN VIÊN ================= */

async function onDeleteEmployee(employeeId, userId, fullName) {
    if (!confirm(`Bạn có chắc muốn xóa nhân viên "${fullName}"?`)) return;

    try {
        const res = await API.delete("/backend/api/employees/delete.php", { employeeId, userId });
        showToast(res.message || "Xóa nhân viên thành công", "success");
        loadEmployees();
    } catch (err) {
        showToast(err.message || "Xóa nhân viên thất bại", "error");
    }
}
