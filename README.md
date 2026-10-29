# Winsum Home

Website bán đèn trang trí và nội thất — đồ án tốt nghiệp viết bằng **PHP thuần + MySQL**, không framework. Chạy được trên XAMPP (local) và shared hosting PHP 8 + MySQL (Hostinger, cPanel…).

**Local:** `http://localhost/<thư-mục-mã-nguồn>` · **Hosting:** xem mục [Triển khai lên hosting](#triển-khai-lên-hosting-hostinger)

---

## Tổng quan

| Phần | File điều phối chính | Chức năng |
| --- | --- | --- |
| **Storefront** | `includes/home.php`, `catalog.php`, `cart.php`, `checkout.php`, … | Xem SP, giỏ hàng, đặt hàng, tra cứu đơn, hoàn hàng, đánh giá, blog |
| **Admin** | `includes/admin/*.php`, `admin-*.php` | Dashboard, SP, đơn, hoàn trả, KH, coupon, đánh giá, blog |

Mọi request đi qua **`index.php?view=...`**. Logic khởi động nằm ở `bootstrap/app.php`: kết nối DB, session, xử lý POST, kiểm tra quyền admin.

```
index.php
  └─ bootstrap/app.php          kết nối DB, session, POST handlers
       └─ includes/routes.php  map view → file PHP + CSS/JS
            └─ layout + view
```

---

## Công nghệ

| Thành phần | Chi tiết trong repo |
| --- | --- |
| PHP | ≥ 8.0, `declare(strict_types=1)` |
| MySQL | DB `winsumwebfinal`, charset `utf8mb4` (`config/database.php`) |
| Web server | Apache / LiteSpeed (`.htaccess` chặn `config/`, `sql/`, `scripts/`, `tests/`, `storage/`) |
| Frontend | HTML/CSS/JS thuần; design tokens trong `assets/css/style.css` |
| Biểu đồ admin | Chart.js 4.4.7 (CDN, chỉ `admin-dashboard`) |

---

## Cài đặt local (XAMPP)

### 1. Copy mã nguồn

```
C:\xampp\htdocs\webwinsum
```

### 2. Import database

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS winsumwebfinal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root winsumwebfinal < winsumwebfinal.sql
```

Hoặc phpMyAdmin → tạo DB `winsumwebfinal` → Import → `winsumwebfinal.sql`.

`winsumwebfinal.sql` đã chứa đủ schema mới (giỏ hàng, khóa đăng nhập, dòng hoàn hàng, tài khoản nhận tiền) và tự đánh dấu các migration là đã áp dụng. `php scripts/migrate.php` chỉ cần chạy khi nâng cấp một DB cũ từ bản trước.

### 3. Cấu hình

Copy `config/local.example.php` thành `config/local.php` rồi điền mật khẩu MySQL, tài khoản quản trị và số tài khoản nhận chuyển khoản. Ứng dụng đọc file đó qua `config/database.php`. Không commit `config/local.php`. Ở local có thể đặt `'app' => ['debug' => true]` để hiện lỗi PHP.

### 4. Chạy

1. XAMPP: bật Apache + MySQL  
2. Trình duyệt: `http://localhost/webwinsum` (hoặc thư mục bạn đặt mã nguồn)

### 5. Bootstrap lần đầu

`bootstrap/app.php` tạo tài khoản admin nếu chưa có, dùng mật khẩu trong `config/local.php` (mặc định xem `config/local.example.php`). Schema không bị `ALTER` trên mỗi request.

---

## Triển khai lên hosting (Hostinger)

Yêu cầu: gói hosting có **PHP ≥ 8.0** (bật `mysqli`, `mbstring`, `fileinfo`) và **MySQL/MariaDB**. Hướng dẫn dưới dùng hPanel của Hostinger; cPanel tương tự.

### 1. Tạo database

hPanel → **Databases → MySQL Databases** → tạo database, user, mật khẩu. Ghi lại 3 giá trị (tên DB/user có tiền tố, ví dụ `u123456789_winsum`). Host là `localhost`.

### 2. Import schema + dữ liệu mẫu

hPanel → **Databases → phpMyAdmin** → chọn DB vừa tạo → **Import** → chọn `winsumwebfinal.sql` → Go. Không cần chạy `scripts/migrate.php` (dump đã đầy đủ và không chứa `CREATE DATABASE`).

### 3. Upload mã nguồn

Nén toàn bộ thư mục dự án (trừ `.git/`) thành `.zip`, hPanel → **Files → File Manager** → `public_html` → Upload → Extract. Kết quả phải là `public_html/index.php`, `public_html/includes/`, `public_html/assets/`… (không nằm trong thư mục con). Cách khác: hPanel → **Git** → Deploy từ repository vào `public_html`.

File `.htaccess` ở gốc và trong `config/`, `sql/`, `scripts/`, `tests/`, `storage/`, `bootstrap/` chặn truy cập trực tiếp qua web; thư mục upload ảnh chặn chạy PHP.

### 4. Tạo `config/local.php` trên hosting

File Manager → `public_html/config/` → copy `local.example.php` thành `local.php` → sửa:

```php
'app'   => ['debug' => false],
'db'    => ['host' => 'localhost', 'name' => 'u123456789_winsum', 'user' => 'u123456789_winsum', 'pass' => 'MẬT_KHẨU_DB', 'charset' => 'utf8mb4'],
'admin' => ['name' => 'Admin', 'phone' => 'SĐT_ADMIN', 'email' => 'EMAIL_ADMIN', 'password' => 'MẬT_KHẨU_MẠNH'],
'bank'  => ['code' => 'MB', 'account_number' => 'SỐ_TK', 'account_name' => 'TÊN_CHỦ_TK'],
```

Đổi mật khẩu admin **trước** khi mở web lần đầu: tài khoản admin được tạo ở request đầu tiên từ giá trị này. Muốn gửi email thật, đặt `mail.transport = 'smtp'` và điền SMTP (Hostinger: `smtp.hostinger.com`, port 587 với email theo tên miền).

### 5. Phiên bản PHP và quyền ghi

- hPanel → **Advanced → PHP Configuration** → chọn PHP 8.1 hoặc 8.2, tick `mysqli`, `mbstring`, `fileinfo`; `display_errors` tắt.  
- File Manager → chmod `755` cho `assets/images/blog-uploads`, `assets/images/return-uploads`, `storage/mail` (Hostinger mặc định đã ghi được).  

### 6. SSL và kiểm tra

- hPanel → **Security → SSL** → cài SSL miễn phí → bật **Force HTTPS**.  
- Mở `https://<tên-miền>/` → trang chủ; `/index.php?view=catalog`; đăng nhập admin bằng SĐT/email trong `local.php` → **Bảng quản trị** → kiểm tra form **Tài khoản nhận tiền**.  
- Thử mở `/config/local.php`, `/winsumwebfinal.sql`, `/sql/` → phải bị **403**.  
- Nếu lỗi DB, trang báo "Hệ thống đang bảo trì"; chi tiết ghi ở hPanel → **Files → Error log** (hoặc đặt tạm `debug => true`).

---

## Tài khoản quản trị

Admin và khách dùng **cùng form đăng nhập** trên header (modal). Admin được nhận diện qua `customers.role = 'admin'`, không có bảng admin riêng.

| Vai trò | Đăng nhập |
| --- | --- |
| **Admin** | SĐT và mật khẩu trong `config/local.example.php` (mặc định SĐT `0901000000`, email `admin@winsumhome.vn`) |
| **Khách** | Đăng ký qua popup **Đăng ký** |

Tài khoản admin do `customerBootstrapAdminAccount()` tạo nếu DB chưa có user `role = admin`. Mật khẩu được hash lúc tạo, không gắn cứng trong mã nguồn.

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
| Storefront | `includes/handlers/storefront-post.php` | Thêm giỏ, checkout, hủy đơn, chuyển khoản |
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

1. Validate giỏ (`cart-store.php`, lưu ở bảng `carts` / `cart_items`) và coupon trong transaction  
2. INSERT `orders` — mặc định `status = pending`, `fulfillment_status = pending`, `payment_status = unpaid`  
3. INSERT `order_items`, `order_payments`, `order_shipments` (`pending`)  
4. Ghi `coupon_redemptions` nếu có mã  
5. `cartClear()` — xóa dòng giỏ trong database  
6. Gửi email xác nhận nếu khách có email. Mặc định ghi file trong `storage/mail/`. SMTP chỉ gửi khi `config/local.php` đặt `mail.transport = smtp`.

Kho chỉ bị trừ khi admin chuyển đơn từ `processing` sang `shipped`.

Mã đơn: `WS` + `md` + 4 số ngẫu nhiên (ví dụ `WS06161234`).

### Thanh toán (seed `payment_methods`)

| Code | Hiển thị | Hành vi |
| --- | --- | --- |
| `cod` | Thanh toán khi nhận hàng | `unpaid` cho đến khi admin đánh dấu `paid` sau khi đã giao |
| `bank_transfer` | Chuyển khoản | Hiện số tài khoản admin đã lưu ở mục **Tài khoản nhận tiền**, kèm mã QR. `unpaid` cho đến khi admin xác nhận đã nhận tiền |

### Vận chuyển (seed `shipping_methods`)

| Code | Phí | ETA |
| --- | --- | --- |
| `express_24h` | 30.000đ | Trong 24h |
| `standard` | 20.000đ | 2–4 ngày |

Phí lưu session `selected_shipping_fee` trước checkout.

### Trạng thái đơn (`order-repository.php`)

| `orders.status` | Nhãn UI | Ghi chú |
| --- | --- | --- |
| `pending` | Chờ xác nhận | Mặc định khi đặt. Chưa trừ kho. Khách được hủy |
| `processing` | Đang xử lý | Admin xác nhận đơn. Khách vẫn được hủy |
| `shipped` | Đang giao hàng | Admin giao hàng. Lúc này mới trừ kho |
| `delivered` | Đã giao hàng | Admin xác nhận đã giao |
| `cancelled` | Đã hủy | Khách hủy khi `pending` hoặc `processing`. Hoàn kho nếu đã trừ |
| `partially_returned` | Hoàn một phần | Đã hoàn một số dòng. Có thể hoàn tiếp phần còn lại |
| `return_pending` … `returned` | Luồng hoàn | Đồng bộ với `order_return_requests` |

Theo dõi thêm: `payment_status` (`unpaid` / `paid` / `refunded`), `fulfillment_status`.

### Hoàn hàng — `return-repository.php`

- Khách chọn từng dòng và số lượng trong **7 ngày** sau `delivered` (kể cả sau một lần hoàn một phần)
- Bảng `order_return_requests` kèm `order_return_items`
- Tiền hoàn bằng tổng dòng được chọn. Phí ship chỉ hoàn khi trả hết mọi dòng
- Trả hết thì `orders.status = returned` và nhả mã giảm giá. Trả một phần thì `partially_returned`, mã giảm giá giữ nguyên
- Admin xử lý tại `admin-returns`; hoàn kho đúng số lượng dòng đã chọn  

### Doanh thu dashboard — `adminGetDashboardStats()`

| Chỉ số | Công thức |
| --- | --- |
| `revenue_net` | `status = delivered` AND `payment_status = paid` |
| `revenue_refunded` | `status = returned` |
| `revenue_paid` | Đơn không hủy/trả + đã paid |

### Quy tắc khác

- **Giỏ hàng:** bảng `carts` và `cart_items`. Khách vãng lai theo `session_key`. Đăng nhập thì gộp vào giỏ của khách  
- **Đánh giá:** Chỉ sau giao; một lần / SP / khách (`review-repository.php`)  
- **Bình luận blog:** Hiển thị ngay; admin trả lời / xóa  
- **Catalog lọc:** `category`, `color`, `min_price`, `max_price`, `q`, `sort` (`productBuildFiltersFromRequest()`)  
- **Admin tasks:** Đếm đơn chờ xử lý, đơn đang giao, COD chưa thu, hoàn chờ duyệt, tồn kho thấp  

---

## Session quan trọng

| Key | File | Mục đích |
| --- | --- | --- |
| `cart_items` | `carts` / `cart_items` | Dòng giỏ hàng |
| `cart_coupon`, `cart_coupon_id` | `cart-store.php` | Mã giảm giá |
| `selected_shipping_fee`, `checkout_shipping_method_id` | checkout flow | Phí ship |
| `checkout_payment_method_id` | checkout flow | PT thanh toán |
| `customer_id` | `customer-auth.php` | Khách đăng nhập |
| `admin_logged_in`, `customer_role` | `admin-auth.php` | Quyền admin |
| `csrf_token` | `csrf.php` | CSRF |
| `auth_flash`, `page_flash` | auth / flash | Thông báo |
| `login_attempts` | bảng `login_attempts` | Khóa 5 lần sai / 15 phút theo SĐT hoặc email |
| `last_order_code`, `last_order_phone` | checkout | Tra cứu guest |
| `checkout_result` | checkout | Kết quả đặt hàng |
| `admin_tasks_seen` | `admin-tasks.php` | Banner nhắc việc admin |

---

## Cơ sở dữ liệu

Database **`winsumwebfinal`** — các bảng nghiệp vụ cộng thêm `carts`, `cart_items`, `login_attempts`, `order_return_items`, `schema_migrations`:

```
customers
products, product_images, categories, product_reviews
orders, order_items, order_payments, order_shipments, order_return_requests, order_return_items
coupons, coupon_redemptions, payment_methods, shipping_methods
inventory_items, inventory_alerts
blog_categories, blog_posts, blog_comments
carts, cart_items, login_attempts
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
- Khóa đăng nhập sai theo SĐT/email trong `login_attempts` (5 lần / 15 phút)  
- HTML blog chỉ giữ liên kết `http`, `https` hoặc đường dẫn nội bộ  

Tài khoản quản trị và số tài khoản nhận tiền nằm ở `config/local.example.php`. Mật khẩu được hash lúc tạo, không gắn cứng trong source.  

---

## Hạn chế

- Chuyển khoản hiện tài khoản cửa hàng và mã QR. Admin xác nhận đã nhận tiền trên web, không có webhook ngân hàng  
- Chưa email SMTP mặc định; thư xác nhận đơn ghi ở `storage/mail/`  
- Hoàn hàng theo dòng đã chọn, không tự hoàn một phần tiền trên cổng thanh toán  

## Kiểm thử

```bash
php tests/run.php
```

Script tạo database `winsumwebfinal_test` nếu chưa có, import SQL, chạy migrate, rồi kiểm tra: đơn mới là `pending` và chỉ trừ kho khi `shipped`, mã giảm giá hết lượt bị từ chối, khách không xem đơn của người khác, admin không thêm được vào giỏ.  

---

## Xử lý lỗi

**Không kết nối DB:** trang hiện "Hệ thống đang bảo trì" (HTTP 503), chi tiết ghi vào error log của PHP. Kiểm tra `config/local.php` (host/tên DB/user/pass), MySQL đã chạy, import lại SQL. Đặt `'app' => ['debug' => true]` để xem lỗi ngay trên trang.

**Trang trắng:** đặt `debug => true` trong `config/local.php`; kiểm tra PHP ≥ 8.0 + `mysqli`, `mbstring`.

**403 khi mở trang:** chỉ các thư mục `config/`, `sql/`, `scripts/`, `tests/`, `storage/`, `bootstrap/` bị chặn; `index.php` ở gốc phải mở được. Nếu hosting không có `mod_rewrite`, các file `.htaccess` trong từng thư mục vẫn chặn.

**Không vào admin:** Dùng SĐT/email admin ở trên, không phải tên hiển thị `admin`.

**Ảnh lỗi:** Kiểm tra `assets/images/` và đường dẫn trong DB.

*Winsum Home — PHP thuần · MySQL · XAMPP*
