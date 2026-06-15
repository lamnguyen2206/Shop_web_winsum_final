# Winsum Home

Website bán đèn trang trí và nội thất — đồ án tốt nghiệp viết bằng **PHP thuần + MySQL**, chạy trên **XAMPP**, không framework.

**Local:** [http://localhost/webwinsum](http://localhost/webwinsum)

---

## Tổng quan

| Phần | File điều phối chính | Chức năng |
| --- | --- | --- |
| **Storefront** | `includes/home.php`, `catalog.php`, `cart.php`, `checkout.php`, … | Xem SP, giỏ hàng, đặt hàng, tra cứu đơn, hoàn hàng, đánh giá, blog |
| **Admin** | `includes/admin/*.php`, `admin-*.php` | Dashboard, SP, đơn, hoàn trả, KH, coupon, đánh giá, blog |

Mọi request đi qua **`index.php?view=...`**. Logic khởi động nằm ở `bootstrap/app.php`: session, kết nối DB, migrate schema, xử lý POST, kiểm tra quyền admin.

```
index.php
  └─ bootstrap/app.php          session, *EnsureSchema(), POST handlers
       └─ includes/routes.php  map view → file PHP + CSS/JS
            └─ layout + view
```

---

## Công nghệ

| Thành phần | Chi tiết trong repo |
| --- | --- |
| PHP | ≥ 8.0, `declare(strict_types=1)` |
| MySQL | DB `winsumwebfinal`, charset `utf8mb4` (`config/database.php`) |
| Apache | XAMPP |
| Frontend | HTML/CSS/JS thuần; design tokens trong `assets/css/style.css` |
| Biểu đồ admin | Chart.js 4.4.7 (CDN, chỉ `admin-dashboard`) |

---

## Cài đặt

### 1. Copy mã nguồn

```
C:\xampp\htdocs\webwinsum
```

### 2. Import database

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS winsumwebfinal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root winsumwebfinal < winsumwebfinal.sql
```

Hoặc phpMyAdmin → Import → `winsumwebfinal.sql`.

### 3. Cấu hình DB

`config/database.php`:

```php
$host = 'localhost';  
$db   = 'winsumwebfinal';
$user = 'root';
$pass = '';
```

### 4. Chạy

1. XAMPP: bật Apache + MySQL  
2. Trình duyệt: `http://localhost/webwinsum`

### 5. Bootstrap lần đầu

`bootstrap/app.php` tự gọi:

| Hàm | Việc làm |
| --- | --- |
| `customerBootstrapAdminAccount()` | Thêm cột `customers.role`, tạo admin mặc định |
| `orderEnsureSchema()` | Cột `inventory_deducted`, đồng bộ trạng thái đơn cũ |
| `returnEnsureSchema()` | Bảng / cột hoàn hàng |
| `reviewEnsureSchema()` | Schema đánh giá SP |
| `blogCommentEnsureTable()` | Bình luận blog |
| `inventoryEnsureAlertsTable()` | Cảnh báo tồn kho |

---

## Tài khoản demo

Admin và khách dùng **cùng form đăng nhập** trên header (modal). Admin được nhận diện qua `customers.role = 'admin'`, không có bảng admin riêng.

| Vai trò | Đăng nhập |
| --- | --- |
| **Admin** | SĐT `0901000000` hoặc email `admin@winsumhome.vn` / mật khẩu `admin123` |
| **Khách** | Đăng ký qua popup **Đăng ký** |

Tài khoản admin do `customerBootstrapAdminAccount()` tạo nếu DB chưa có user `role = admin`.

**Mã giảm giá** (seed trong `winsumwebfinal.sql`): `WINSUMXINCHAO`, `WINSUM10`, `FREESHIP`, `HELLO2026` — nhập tại trang Giỏ hàng.

---

## Định tuyến (`includes/routes.php`)

### Storefront

| View | File | Ghi chú |
| --- | --- | --- |
| `home` | `includes/home.php` | Hero, danh mục, SP nổi bật, blog |
| `catalog` | `includes/catalog.php` | Lọc, sort, phân trang |
| `product` | `includes/views/product-detail.php` | `&slug=...` |
| `cart` | `includes/cart.php` | Session + coupon |
| `checkout` | `includes/checkout.php` | Guest / đăng nhập |
| `account` | `includes/account.php` | Hồ sơ, đổi MK |
| `orders` | `includes/my-orders.php` | Đơn của tôi |
| `order-detail` | `includes/order-detail.php` | `&id=...` |
| `order-lookup` | `includes/order-lookup.php` | Mã đơn + SĐT |
| `order-return` | `includes/order-return.php` | `&order_id=...` |
| `blog` | `includes/blog.php` | |
| `post` | `includes/views/blog-detail.php` | `&slug=...` |

### Admin (yêu cầu `adminRequire()`)

| View | File |
| --- | --- |
| `admin-dashboard` | `includes/admin/admin-dashboard.php` |
| `admin-orders` | `includes/admin-orders.php` |
| `admin-order-detail` | `includes/admin-order-detail.php` (`&code=WS...`) |
| `admin-products` | `includes/admin-products.php` |
| `admin-product-create` / `admin-product-edit` | `includes/admin-product-form.php` |
| `admin-coupons` | `includes/admin-coupons.php` |
| `admin-coupon-create` / `admin-coupon-edit` | `includes/admin-coupon-form.php` |
| `admin-returns` | `includes/admin-returns.php` |
| `admin-customers` | `includes/admin-customers.php` |
| `admin-reviews` | `includes/admin/admin-reviews.php` |
| `admin-blog` | `includes/admin-blog.php` |
| `admin-blog-comments` | `includes/admin/admin-blog-comments.php` |
| `blog-editor` | `includes/blog-editor.php` |

View không tồn tại → fallback `home` + banner 404 (`includes/errors/404.php`).

---

## Kiến trúc mã nguồn

### Xử lý POST

| Handler | File | Ví dụ action |
| --- | --- | --- |
| Storefront | `includes/handlers/storefront-post.php` | Thêm giỏ, checkout, hủy đơn, VietQR demo |
| Admin | `includes/admin-post.php` | CRUD SP, cập nhật đơn, duyệt hoàn |
| Auth | `includes/customer-auth-post.php` | Đăng ký, đăng nhập, đăng xuất |
| Blog editor | `includes/blog-editor-handler.php` | Lưu / upload ảnh blog |

Pattern **PRG** (Post-Redirect-Get): xử lý POST → `redirect()` → render GET.

### Repository (nghiệp vụ + SQL)

| File | Domain |
| --- | --- |
| `product-repository.php` | Tìm kiếm, lọc catalog, chi tiết SP |
| `product-admin-repository.php` | CRUD SP admin |
| `order-repository.php` | Tạo đơn, trạng thái, thanh toán, giao hàng |
| `cart-store.php` | Giỏ hàng session (không phải DB) |
| `inventory-repository.php` | Trừ / hoàn kho, cảnh báo |
| `coupon-repository.php` | Validate, ghi `coupon_redemptions` |
| `coupon-admin-repository.php` | CRUD coupon |
| `return-repository.php` | Yêu cầu & duyệt hoàn hàng |
| `review-repository.php` | Đánh giá SP |
| `customer-auth.php` / `customer-admin-repository.php` | Auth & quản lý KH |
| `home-repository.php` | Dữ liệu trang chủ |
| `blog-repository.php` / `blog-comment-repository.php` | Blog & bình luận |
| `admin-stats.php` | KPI dashboard |

### API (JSON / AJAX)

| Endpoint | Mục đích |
| --- | --- |
| `api/product-search.php` | Tìm kiếm SP (header `site-search.js`) |
| `api/checkout-totals.php` | Tính lại tổng checkout |
| `api/admin-tasks.php` | Banner nhắc việc admin (`admin-tasks.js`) |

### Layout & assets

- Layout: `includes/layout/head.php`, `foot.php`, `header.php`, `footer.php`
- CSS/JS theo view: `appAssetsForView()` trong `routes.php`
- Admin nav: `includes/admin-nav.php` — 9 mục + banner task

---

## Nghiệp vụ (bám code)

### Đặt hàng — `orderCreateFromCheckout()`

1. Validate giỏ (`cart-store.php`) và coupon trong transaction  
2. INSERT `orders` — mặc định `status = shipped`, `fulfillment_status = shipped`, `payment_status = unpaid`  
3. INSERT `order_items`, `order_payments`, `order_shipments`  
4. `inventoryDeductForOrder()` → cập nhật `inventory_items.quantity_on_hand`  
5. Ghi `coupon_redemptions` nếu có mã  
6. `cartClear()` — xóa session giỏ  

Mã đơn: `WS` + `md` + 4 số ngẫu nhiên (ví dụ `WS06161234`).

### Thanh toán (seed `payment_methods`)

| Code | Hiển thị | Hành vi |
| --- | --- | --- |
| `cod` | Thanh toán khi nhận hàng | `unpaid` → admin cập nhật `paid` sau giao |
| `bank_transfer` | Chuyển khoản VietQR | `unpaid` → khách bấm **«Tôi đã thanh toán»** (`confirm_vietqr_demo`) → `paid` |

### Vận chuyển (seed `shipping_methods`)

| Code | Phí | ETA |
| --- | --- | --- |
| `express_24h` | 30.000đ | Trong 24h |
| `standard` | 20.000đ | 2–4 ngày |

Phí lưu session `selected_shipping_fee` trước checkout.

### Trạng thái đơn (`order-repository.php`)

| `orders.status` | Nhãn UI | Ghi chú |
| --- | --- | --- |
| `shipped` | Đang giao hàng | Mặc định khi đặt — rút gọn demo |
| `delivered` | Đã giao hàng | Admin xác nhận trên `admin-order-detail` |
| `cancelled` | Đã hủy | Khách hủy khi `shipped`; hoàn kho |
| `return_pending` … `returned` | Luồng hoàn | Đồng bộ với `order_return_requests` |

Theo dõi thêm: `payment_status` (`unpaid` / `paid` / `refunded`), `fulfillment_status`.

### Hoàn hàng — `return-repository.php`

- Khách gửi yêu cầu trong **7 ngày** sau `delivered`  
- Bảng `order_return_requests.status`: `pending` → `accepted` → `goods_received` → `completed` (hoặc `rejected`)  
- Admin xử lý tại `admin-returns`; hoàn kho khi nhận hàng trả; `refunded` + release coupon khi hoàn tiền xong  

### Doanh thu dashboard — `adminGetDashboardStats()`

| Chỉ số | Công thức |
| --- | --- |
| `revenue_net` | `status = delivered` AND `payment_status = paid` |
| `revenue_refunded` | `status = returned` |
| `revenue_paid` | Đơn không hủy/trả + đã paid |

### Quy tắc khác

- **Giỏ hàng:** PHP Session — không có bảng `carts`  
- **Đánh giá:** Chỉ sau giao; một lần / SP / khách (`review-repository.php`)  
- **Bình luận blog:** Hiển thị ngay; admin trả lời / xóa  
- **Catalog lọc:** `category`, `color`, `min_price`, `max_price`, `q`, `sort` (`productBuildFiltersFromRequest()`)  
- **Admin tasks:** Đếm đơn shipped, COD chưa thu, hoàn chờ duyệt, tồn kho thấp  

---

## Session quan trọng

| Key | File | Mục đích |
| --- | --- | --- |
| `cart_items` | `cart-store.php` | Dòng giỏ hàng |
| `cart_coupon`, `cart_coupon_id` | `cart-store.php` | Mã giảm giá |
| `selected_shipping_fee`, `checkout_shipping_method_id` | checkout flow | Phí ship |
| `checkout_payment_method_id` | checkout flow | PT thanh toán |
| `customer_id` | `customer-auth.php` | Khách đăng nhập |
| `admin_logged_in`, `customer_role` | `admin-auth.php` | Quyền admin |
| `csrf_token` | `csrf.php` | CSRF |
| `auth_flash`, `page_flash` | auth / flash | Thông báo |
| `login_attempts` | `customer-auth.php` | Khóa 5 lần sai / 15 phút |
| `last_order_code`, `last_order_phone` | checkout | Tra cứu guest |
| `checkout_result` | checkout | Kết quả / VietQR demo |
| `admin_tasks_seen` | `admin-tasks.php` | Banner nhắc việc admin |

---

## Cơ sở dữ liệu

Database **`winsumwebfinal`** — 19 bảng:

```
customers
products, product_images, categories, product_reviews
orders, order_items, order_payments, order_shipments, order_return_requests
coupons, coupon_redemptions, payment_methods, shipping_methods
inventory_items, inventory_alerts
blog_categories, blog_posts, blog_comments
```

- Snapshot khách trên `orders` (`customer_name`, `customer_phone`, `customer_address`)  
- 1 sản phẩm ↔ 1 dòng `inventory_items`  
- Sơ đồ ER: `docs/winsum-er-diagram.erdplus`  

---

## Cấu trúc thư mục

```
webwinsum/
├── index.php
├── bootstrap/app.php
├── config/database.php
├── winsumwebfinal.sql
│
├── includes/
│   ├── routes.php
│   ├── handlers/storefront-post.php
│   ├── admin-post.php
│   ├── *-repository.php
│   ├── cart-store.php
│   ├── views/                 # product-detail, blog-detail, partials
│   ├── admin/                 # dashboard, reviews, blog-comments, stats
│   ├── admin/partials/        # task-banner, admin-replies
│   └── layout/, errors/
│
├── assets/css/, assets/js/, assets/images/
│   ├── images/blog-uploads/
│   └── images/return-uploads/
│
├── api/
│   ├── checkout-totals.php
│   ├── product-search.php
│   └── admin-tasks.php
│
└── docs/
    └── winsum-er-diagram.erdplus
```

---

## Bảo mật

- CSRF (`includes/csrf.php`) trên form POST  
- Prepared statements (mysqli)  
- `password_hash` / `password_verify`  
- `adminRequire()` chặn view admin  
- Khách chỉ thao tác đơn của mình  
- Helper `e()` escape HTML  
- Giới hạn đăng nhập sai (`login_attempts`)  

---

## Hạn chế

- VietQR chỉ demo — không webhook ngân hàng  
- Đơn mới vào thẳng `shipped`, không có `pending` / `processing`  
- Chưa email/SMS  
- Chưa test tự động  
- Hoàn hàng theo cả đơn, không theo từng dòng SP  

---

## Xử lý lỗi

**Không kết nối DB:** MySQL đã Start; DB tên `winsumwebfinal`; thử `127.0.0.1`; import lại SQL.

**Trang trắng:** Bật `display_errors` trong `php.ini`; kiểm tra PHP ≥ 8.0 + `mysqli`.

**Không vào admin:** Dùng SĐT/email admin ở trên, không phải tên hiển thị `admin`.

**Ảnh lỗi:** Kiểm tra `assets/images/` và đường dẫn trong DB.

*Winsum Home — PHP thuần · MySQL · XAMPP*
