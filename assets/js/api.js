/**
 * FILE: assets/js/api.js
 * -----------------------------------------------------------------------
 * 1. File này dùng để làm gì?
 *    Cung cấp đối tượng "API" với 4 hàm get/post/put/delete để gọi tới
 *    backend PHP bằng Fetch API. TẤT CẢ các file .js khác (products.js,
 *    pos.js, orders.js...) đều dùng object API này thay vì tự viết fetch().
 *
 * 2. File này giao tiếp với file nào?
 *    Được nhúng (<script>) TRƯỚC các file .js khác trong mọi trang HTML.
 *    Gọi tới các file trong backend/api/**.php
 *
 * 3. File này gọi API nào?
 *    Gọi bất kỳ endpoint PHP nào được truyền vào (products/list.php,
 *    orders/create.php,...) - đường dẫn do file .js của từng trang chỉ định.
 *
 * 4. Dữ liệu đi theo hướng nào?
 *    products.js gọi API.get("/backend/api/products/list.php")
 *    --> api.js thực hiện fetch() --> backend PHP --> SQL Server
 *    --> JSON trả về --> api.js parse JSON --> trả lại cho products.js
 * -----------------------------------------------------------------------
 */

// Đường dẫn gốc tới thư mục dự án, TỰ ĐỘNG PHÁT HIỆN dựa vào URL hiện tại.
// Đây là điểm sửa quan trọng: trước đây BASE_URL để trống ("") khiến các
// endpoint bắt đầu bằng "/backend/..." bị trình duyệt hiểu là tính từ GỐC
// DOMAIN (vd: http://localhost/backend/...), trong khi dự án thực tế nằm
// trong thư mục con (vd: http://localhost/CoffeeManager/backend/...) -> sai
// đường dẫn -> Apache trả về trang lỗi 404 (HTML) thay vì JSON -> JS báo
// "Phản hồi từ server không hợp lệ".
//
// Hàm dưới đây tự tìm ra phần thư mục gốc bằng cách nhìn vào URL hiện tại:
// - Nếu đang ở trong pages/xxx.html -> bỏ phần "/pages/xxx.html" đi
// - Nếu đang ở index.html (hoặc /) -> bỏ phần "/index.html" đi
// Kết quả: hoạt động đúng dù bạn đặt dự án ở gốc domain hay trong bất kỳ
// thư mục con nào (CoffeeManager, coffee-shop, demo/coffee,...).
function detectBaseUrl() {
    const path = window.location.pathname;
    const pagesIndex = path.indexOf("/pages/");
    if (pagesIndex !== -1) {
        return path.substring(0, pagesIndex);
    }
    return path.replace(/\/index\.html$/, "").replace(/\/$/, "");
}

const BASE_URL = detectBaseUrl();

/**
 * Hàm dùng chung để thực hiện 1 request tới backend PHP.
 * Không export ra ngoài, chỉ dùng nội bộ trong file này.
 */
async function request(method, endpoint, body = null) {
    const options = {
        method,
        headers: {
            "Content-Type": "application/json",
        },
        // "same-origin" để trình duyệt tự động gửi kèm Cookie (PHPSESSID),
        // giúp backend nhận diện được người dùng đã đăng nhập
        credentials: "same-origin",
    };

    if (body !== null) {
        options.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(BASE_URL + endpoint, options);
    } catch (networkError) {
        // Lỗi mạng (server tắt, mất kết nối...)
        throw { success: false, message: "Không thể kết nối tới server. Vui lòng kiểm tra kết nối mạng." };
    }

    let json;
    try {
        json = await response.json();
    } catch (parseError) {
        throw { success: false, message: "Phản hồi từ server không hợp lệ." };
    }

    // Nếu server trả về lỗi 401 (chưa đăng nhập) -> tự động đưa về trang đăng nhập
    if (response.status === 401) {
        window.location.href = getLoginPageUrl();
        throw json;
    }

    // Nếu HTTP status không phải 2xx -> coi là lỗi, ném ra để nơi gọi tự xử lý (try/catch)
    if (!response.ok || json.success === false) {
        throw json;
    }

    return json; // { success, message, data }
}

/**
 * Xác định đường dẫn login.html tương đối, vì trang có thể nằm ở root
 * (index.html) hoặc trong thư mục pages/ (pages/products.html).
 */
function getLoginPageUrl() {
    const isInPagesFolder = window.location.pathname.includes("/pages/");
    return isInPagesFolder ? "../pages/login.html" : "pages/login.html";
}

// Đối tượng API dùng chung - đây là phần quan trọng nhất của file này
const API = {
    get: (endpoint) => request("GET", endpoint),
    post: (endpoint, data) => request("POST", endpoint, data),
    put: (endpoint, data) => request("PUT", endpoint, data),
    delete: (endpoint, data = null) => request("DELETE", endpoint, data),
};
