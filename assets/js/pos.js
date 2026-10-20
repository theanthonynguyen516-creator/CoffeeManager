/**
 * FILE: assets/js/pos.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Xử lý toàn bộ nghiệp vụ bán hàng tại quầy: tải danh sách bàn/danh
 *    mục/sản phẩm, quản lý giỏ hàng (thêm/sửa số lượng/ghi chú/xóa món),
 *    tính giảm giá, mở modal thanh toán, gọi API tạo đơn hàng.
 *
 * 2. File này giao tiếp với file nào?
 *    Cần api.js và common.js đã nhúng trước.
 *    Chỉ được nhúng DUY NHẤT trong pages/pos.html.
 *
 * 3. File này gọi API nào?
 *    backend/api/tables/list.php
 *    backend/api/categories/list.php
 *    backend/api/products/list.php
 *    backend/api/orders/create.php (thanh toán)
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    pos.html load -> pos.js gọi 3 API list -> render giao diện chọn món
 *    Người dùng bấm vào sản phẩm -> thêm vào biến "cart" (chỉ lưu trong
 *    JS, CHƯA gửi lên server) -> render lại giỏ hàng + tổng tiền
 *    Bấm "Thanh toán" -> mở modal -> xác nhận -> gọi orders/create.php
 *    -> SQL Server ghi nhận đơn hàng thật -> JSON -> xóa giỏ hàng, thông báo
 * -----------------------------------------------------------------------
 */

// ===== BIẾN TRẠNG THÁI CỦA TRANG (lưu trong bộ nhớ JS, mất khi tải lại trang) =====
let allProducts = [];       // toàn bộ sản phẩm đang bán, tải 1 lần khi vào trang
let selectedCategoryId = ""; // danh mục đang chọn ở tab ("" = Tất cả)
let cart = [];               // giỏ hàng: [{ productId, productName, unitPrice, quantity, note }]

document.addEventListener("DOMContentLoaded", async () => {
    await loadCurrentUser();
    await Promise.all([loadTablesForPos(), loadCategoriesTabsForPos(), loadProductsForPos()]);

    setupSearchAndFilterEvents();
    setupCartFooterEvents();
    setupCheckoutModalEvents();
    renderCart(); // vẽ giỏ hàng rỗng ban đầu
});

/* ================= TẢI DỮ LIỆU BAN ĐẦU ================= */

async function loadTablesForPos() {
    try {
        const res = await API.get("/backend/api/tables/list.php");
        const select = document.getElementById("pos-table-select");
        // Chỉ hiển thị bàn đang Trống hoặc Đã đặt để chọn phục vụ (bàn đang dùng thì đã có đơn khác)
        const availableTables = res.data.filter((t) => t.StatusCode === "EMPTY" || t.StatusCode === "RESERVED");
        availableTables.forEach((t) => {
            select.insertAdjacentHTML("beforeend", `<option value="${t.TableId}">${t.TableName} (${t.Capacity} chỗ)</option>`);
        });
    } catch (err) {
        showToast(err.message || "Không tải được danh sách bàn", "error");
    }
}

async function loadCategoriesTabsForPos() {
    try {
        const res = await API.get("/backend/api/categories/list.php");
        const tabsContainer = document.getElementById("pos-category-tabs");
        res.data.forEach((cat) => {
            const btn = document.createElement("button");
            btn.className = "category-tab";
            btn.dataset.categoryId = cat.CategoryId;
            btn.textContent = cat.CategoryName;
            btn.addEventListener("click", () => onSelectCategoryTab(cat.CategoryId, btn));
            tabsContainer.appendChild(btn);
        });

        // Gắn sự kiện cho tab "Tất cả" (đã có sẵn trong HTML)
        const allTab = tabsContainer.querySelector('[data-category-id=""]');
        allTab.addEventListener("click", () => onSelectCategoryTab("", allTab));
    } catch (err) {
        showToast(err.message || "Không tải được danh mục", "error");
    }
}

function onSelectCategoryTab(categoryId, clickedBtn) {
    selectedCategoryId = String(categoryId);
    document.querySelectorAll(".category-tab").forEach((btn) => btn.classList.remove("active"));
    clickedBtn.classList.add("active");
    renderProductsGrid();
}

async function loadProductsForPos() {
    const grid = document.getElementById("pos-products-grid");
    try {
        // Lấy tối đa 100 sản phẩm 1 lần (đủ dùng cho 1 quán cà phê vừa và nhỏ),
        // sau đó lọc/tìm kiếm ngay trên trình duyệt để bấm chọn món được NHANH,
        // không phải gọi API mỗi lần gõ tìm kiếm hay đổi tab danh mục.
        const res = await API.get("/backend/api/products/list.php?page=1&pageSize=100");
        // PDO/sqlsrv có thể trả cột IsActive (kiểu BIT) về dạng số 1/0, chuỗi "1"/"0",
        // hoặc boolean true/false tùy phiên bản driver. Dùng Number(...) === 1 để
        // luôn kiểm tra đúng bất kể kiểu dữ liệu thực tế PHP trả về.
        allProducts = res.data.items.filter((p) => Number(p.IsActive) === 1);
        renderProductsGrid();
    } catch (err) {
        grid.innerHTML = `<p class="table-empty">Lỗi tải sản phẩm</p>`;
        showToast(err.message || "Không tải được sản phẩm", "error");
    }
}

/* ================= HIỂN THỊ LƯỚI SẢN PHẨM (có lọc + tìm kiếm) ================= */

function renderProductsGrid() {
    const grid = document.getElementById("pos-products-grid");
    const searchText = document.getElementById("pos-search-input").value.trim().toLowerCase();

    let filtered = allProducts;
    if (selectedCategoryId) {
        filtered = filtered.filter((p) => String(p.CategoryId) === selectedCategoryId);
    }
    if (searchText) {
        // Dùng (p.ProductName || "") để phòng trường hợp tên sản phẩm bị null/undefined
        // (dữ liệu lỗi) làm crash toàn bộ hàm filter, khiến lưới sản phẩm trống trơn
        filtered = filtered.filter((p) => (p.ProductName || "").toLowerCase().includes(searchText));
    }

    if (filtered.length === 0) {
        grid.innerHTML = `<p class="table-empty">Không tìm thấy sản phẩm phù hợp</p>`;
        return;
    }

    // Render TỪNG sản phẩm trong try/catch riêng: nếu 1 sản phẩm có dữ liệu bất
    // thường gây lỗi, chỉ sản phẩm đó bị bỏ qua thay vì làm hỏng cả lưới (trước đây
    // 1 lỗi nhỏ có thể khiến innerHTML không được gán, cả lưới trống không bấm được)
    const cardsHtml = filtered.map((p) => {
        try {
            return `
                <div class="pos-product-card" onclick="onAddProductToCart(${Number(p.ProductId)})">
                    <div class="pos-product-thumb">
                        ${p.ImageUrl ? `<img src="${p.ImageUrl}" alt="${p.ProductName || ''}">` : "☕"}
                    </div>
                    <div class="pos-product-name">${p.ProductName || "(Không có tên)"}</div>
                    <div class="pos-product-price">${formatCurrency(p.SellPrice)}</div>
                </div>
            `;
        } catch (err) {
            console.error("Lỗi render sản phẩm:", p, err);
            return "";
        }
    });

    grid.innerHTML = cardsHtml.join("");
}

function setupSearchAndFilterEvents() {
    document.getElementById("pos-search-input").addEventListener("input", renderProductsGrid);
}

/* ================= GIỎ HÀNG ================= */

/** Thêm sản phẩm vào giỏ (nếu đã có thì tăng số lượng lên 1) */
function onAddProductToCart(productId) {
    const product = allProducts.find((p) => Number(p.ProductId) === Number(productId));
    if (!product) {
        // Trước đây chỗ này chỉ "return" âm thầm không báo gì, khiến việc bấm chọn
        // sản phẩm trông như "không phản ứng". Giờ báo rõ để dễ debug nếu còn xảy ra.
        console.error("Không tìm thấy sản phẩm với ID:", productId, "trong danh sách:", allProducts);
        showToast("Không thể thêm sản phẩm vào giỏ hàng, vui lòng tải lại trang", "error");
        return;
    }

    const existingItem = cart.find((item) => Number(item.productId) === Number(productId));
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            productId: Number(product.ProductId), // ép về số để so sánh nhất quán về sau (PDO/sqlsrv có thể trả về ID dạng chuỗi)
            productName: product.ProductName,
            unitPrice: product.SellPrice,
            quantity: 1,
            note: "",
        });
    }
    renderCart();
}

function changeCartItemQuantity(productId, delta) {
    const item = cart.find((i) => Number(i.productId) === Number(productId));
    if (!item) return;

    item.quantity += delta;
    if (item.quantity <= 0) {
        cart = cart.filter((i) => i.productId !== productId);
    }
    renderCart();
}

function removeCartItem(productId) {
    cart = cart.filter((i) => i.productId !== productId);
    renderCart();
}

function updateCartItemNote(productId, noteValue) {
    const item = cart.find((i) => Number(i.productId) === Number(productId));
    if (item) item.note = noteValue;
    // Không cần renderCart() lại ở đây vì chỉ cập nhật biến, tránh mất focus ô input
}

/** Vẽ lại toàn bộ giỏ hàng + tính lại tổng tiền */
function renderCart() {
    const container = document.getElementById("pos-cart-items");

    if (cart.length === 0) {
        container.innerHTML = `<p class="table-empty">Chưa có món nào</p>`;
    } else {
        container.innerHTML = cart.map((item) => `
            <div class="pos-cart-item">
                <div class="pos-cart-item-top">
                    <div class="pos-cart-item-name">${item.productName}</div>
                    <button class="pos-cart-item-remove" onclick="removeCartItem(${item.productId})">✕</button>
                </div>
                <div class="flex flex-between">
                    <div class="pos-cart-item-price">${formatCurrency(item.unitPrice)}</div>
                    <div class="pos-qty-control">
                        <button type="button" onclick="changeCartItemQuantity(${item.productId}, -1)">−</button>
                        <span class="qty-value">${item.quantity}</span>
                        <button type="button" onclick="changeCartItemQuantity(${item.productId}, 1)">+</button>
                    </div>
                </div>
                <input
                    type="text"
                    class="pos-cart-item-note"
                    placeholder="Ghi chú món (vd: ít đá, không đường)..."
                    value="${item.note}"
                    onchange="updateCartItemNote(${item.productId}, this.value)"
                >
            </div>
        `).join("");
    }

    updateCartTotals();
}

function setupCartFooterEvents() {
    document.getElementById("clear-cart-btn").addEventListener("click", () => {
        if (cart.length === 0) return;
        if (confirm("Xóa toàn bộ giỏ hàng?")) {
            cart = [];
            renderCart();
        }
    });

    document.getElementById("pos-discount-type").addEventListener("change", updateCartTotals);
    document.getElementById("pos-discount-value").addEventListener("input", updateCartTotals);

    document.getElementById("open-checkout-btn").addEventListener("click", openCheckoutModal);
}

/* ================= TÍNH TOÁN TỔNG TIỀN (dùng chung cho giỏ hàng & modal thanh toán) ================= */

/** Tính subtotal, discountAmount, total dựa trên giỏ hàng hiện tại. Trả về object để tái sử dụng. */
function calculateCartTotals() {
    const subTotal = cart.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0);

    const discountType = document.getElementById("pos-discount-type").value;
    const discountValue = Number(document.getElementById("pos-discount-value").value) || 0;

    let discountAmount = 0;
    if (discountType === "PERCENT") {
        discountAmount = (subTotal * Math.min(discountValue, 100)) / 100;
    } else if (discountType === "AMOUNT") {
        discountAmount = Math.min(discountValue, subTotal);
    }

    const totalAmount = subTotal - discountAmount;
    return { subTotal, discountType, discountValue, discountAmount, totalAmount };
}

function updateCartTotals() {
    const { subTotal, discountAmount, totalAmount } = calculateCartTotals();
    document.getElementById("pos-subtotal").textContent = formatCurrency(subTotal);
    document.getElementById("pos-discount-amount").textContent = formatCurrency(discountAmount);
    document.getElementById("pos-total").textContent = formatCurrency(totalAmount);
}

/* ================= MODAL THANH TOÁN ================= */

function openCheckoutModal() {
    if (cart.length === 0) {
        showToast("Giỏ hàng đang trống", "warning");
        return;
    }

    const { totalAmount } = calculateCartTotals();
    document.getElementById("checkout-total-amount").textContent = formatCurrency(totalAmount);
    document.getElementById("checkout-amount-paid").value = Math.round(totalAmount);
    document.getElementById("checkout-change-row").style.display = "none";

    openModal("checkout-modal");
}

function setupCheckoutModalEvents() {
    const paymentMethodSelect = document.getElementById("checkout-payment-method");
    const cashGroup = document.getElementById("checkout-cash-group");
    const amountPaidInput = document.getElementById("checkout-amount-paid");

    // Chỉ hiển thị ô "Khách đưa" + tính tiền thối khi thanh toán bằng Tiền mặt
    paymentMethodSelect.addEventListener("change", () => {
        cashGroup.style.display = paymentMethodSelect.value === "CASH" ? "block" : "none";
        updateChangeAmount();
    });

    amountPaidInput.addEventListener("input", updateChangeAmount);

    document.getElementById("checkout-form").addEventListener("submit", onSubmitCheckout);
}

function updateChangeAmount() {
    const { totalAmount } = calculateCartTotals();
    const amountPaid = Number(document.getElementById("checkout-amount-paid").value) || 0;
    const changeRow = document.getElementById("checkout-change-row");

    if (amountPaid > totalAmount) {
        changeRow.style.display = "flex";
        document.getElementById("checkout-change-amount").textContent = formatCurrency(amountPaid - totalAmount);
    } else {
        changeRow.style.display = "none";
    }
}

async function onSubmitCheckout(e) {
    e.preventDefault();

    const { subTotal, discountType, discountValue, totalAmount } = calculateCartTotals();
    const paymentMethod = document.getElementById("checkout-payment-method").value;
    const amountPaid = paymentMethod === "CASH"
        ? Number(document.getElementById("checkout-amount-paid").value) || totalAmount
        : totalAmount;

    if (amountPaid < totalAmount) {
        showToast("Số tiền khách đưa không đủ", "warning");
        return;
    }

    const payload = {
        tableId: document.getElementById("pos-table-select").value || null,
        items: cart.map((item) => ({
            productId: item.productId,
            quantity: item.quantity,
            note: item.note,
        })),
        discountType: discountType || null,
        discountValue: discountValue,
        paymentMethod: paymentMethod,
        amountPaid: amountPaid,
    };

    const submitBtn = document.getElementById("checkout-submit-btn");
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="loading-spinner"></span> Đang xử lý...';

    try {
        const res = await API.post("/backend/api/orders/create.php", payload);
        const order = res.data;

        showToast(`Thanh toán thành công! Mã đơn: ${order.orderCode}`, "success");
        if (order.changeAmount > 0) {
            showToast(`Tiền thối lại: ${formatCurrency(order.changeAmount)}`, "info");
        }

        // Reset lại toàn bộ giỏ hàng và giao diện sau khi thanh toán xong
        cart = [];
        document.getElementById("pos-discount-type").value = "";
        document.getElementById("pos-discount-value").value = "0";
        renderCart();
        closeModal("checkout-modal");

        // Tải lại danh sách bàn (vì trạng thái bàn vừa đổi) và sản phẩm
        document.getElementById("pos-table-select").innerHTML = '<option value="">Mang về (không chọn bàn)</option>';
        await loadTablesForPos();
    } catch (err) {
        showToast(err.message || "Thanh toán thất bại", "error");
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "Xác nhận thanh toán";
    }
}
