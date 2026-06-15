# Winsum Home

Website thương mại điện tử bán đèn trang trí và nội thất — đồ án tốt nghiệp xây dựng bằng **PHP thuần + MySQL/MariaDB**, chạy trên **XAMPP**, không dùng framework.

**URL chạy local:** [http://localhost/webwinsum](http://localhost/webwinsum)

---

## Tổng quan

Dự án gồm hai phần chính:

| Phần | Mô tả |
|------|--------|
| **Storefront** | Khách xem sản phẩm, giỏ hàng, đặt hàng, tra cứu đơn, hoàn hàng, đánh giá, đọc blog |
| **Admin** | Quản trị sản phẩm, tồn kho, đơn hàng, hoàn trả, khách hàng, mã giảm giá, đánh giá, blog |

Luồng nghiệp vụ trọng tâm: **đặt hàng → thanh toán (COD / VietQR demo) → giao hàng → hoàn hàng 4 bước → thống kê doanh thu**.

Mọi trang được điều hướng qua front controller `index.php` với tham số `?view=...`.

---

## Công nghệ

| Thành phần | Ghi chú |
|------------|---------|
| PHP | 8.0 trở lên (`declare(strict_types=1)`) |
| MySQL / MariaDB | Database `winsumwebfinal`, charset `utf8mb4` |
| Apache | XAMPP |
| Frontend | HTML, CSS, JavaScript thuần |
| Biểu đồ admin | Chart.js (CDN) |

---

## Yêu cầu hệ thống

- Windows + [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB)
- PHP ≥ 8.0 với extension `mysqli`
- Trình duyệt hiện đại (Chrome, Edge, Firefox)

---

## Cài đặt

### 1. Đặt mã nguồn

Copy toàn bộ thư mục project vào `htdocs`, ví dụ:

```
C:\xampp\htdocs\webwinsum
```

### 2. Tạo database và import dữ liệu

Tạo database `winsumwebfinal`, sau đó import file SQL dump đi kèm đồ án (tên file có thể là `winsumwebfinal.sql` hoặc bản export từ phpMyAdmin):

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS winsumwebfinal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root winsumwebfinal < duong-dan/toi/file-dump.sql
```

Hoặc dùng **phpMyAdmin** → Import → chọn file `.sql`.

### 3. Cấu hình kết nối

Kiểm tra `config/database.php`:

```php
$host = 'localhost';   // hoặc '127.0.0.1' nếu gặp lỗi quyền MariaDB
$db   = 'winsumwebfinal';
$user = 'root';
$pass = '';
```

### 4. Chạy ứng dụng

1. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
2. Mở trình duyệt: `http://localhost/webwinsum`

> Nếu đặt project trong thư mục khác (ví dụ `winsumweb`), đổi URL tương ứng. Code dùng đường dẫn tương đối nên không cần cấu hình base URL.

### 5. Khởi tạo tự động khi chạy lần đầu

Khi có kết nối DB, `bootstrap/app.php` tự gọi các hàm `*EnsureSchema()` để:

- Bổ sung cột `role` trên `customers` và tạo tài khoản admin mặc định (nếu chưa có)
- Đồng bộ schema đơn hàng, đánh giá, hoàn hàng, bình luận blog

---

## Tài khoản demo

| Vai trò | Cách đăng nhập |
|---------|----------------|
| **Admin** | Trang chủ → **Đăng nhập** → SĐT `0901000000` hoặc email `admin@winsumhome.vn` / mật khẩu `admin123` |
| **Khách hàng** | Đăng ký mới qua popup trên header |
| **Mã giảm giá** | Nhập mã trên trang giỏ hàng (ví dụ mã có trong database seed) |

Sau khi đăng nhập admin, vào **Bảng quản trị** qua menu hoặc `?view=admin-dashboard`.

---

## Chức năng

### Storefront (khách hàng)

- Trang chủ: banner, danh mục, sản phẩm nổi bật, tin tức
- Danh mục sản phẩm: lọc danh mục / màu / giá, tìm kiếm, sắp xếp, phân trang
- Chi tiết sản phẩm: gallery, thông số, đánh giá
- Giỏ hàng (session PHP) + mã giảm giá
- Checkout: khách vãng lai hoặc đã đăng nhập; COD hoặc VietQR (demo)
- Tài khoản cá nhân, đơn hàng của tôi
- Tra cứu đơn bằng mã đơn + SĐT
- Hủy đơn (khi đơn còn trạng thái **Đang giao**)
- Yêu cầu hoàn hàng (trong 7 ngày sau khi giao)
- Blog + bình luận (hiển thị ngay sau khi gửi)
- Đánh giá sản phẩm (chỉ khách đã mua và đơn đã giao)

### Admin (quản trị)

- Dashboard: thống kê đơn, doanh thu thuần, hoàn trả, cảnh báo tồn kho
- Biểu đồ doanh thu theo ngày / tháng / năm / khoảng thời gian
- CRUD sản phẩm + quản lý tồn kho
- Quản lý đơn: cập nhật giao hàng, thanh toán
- Hoàn hàng 4 giai đoạn (duyệt → nhận hàng → hoàn tiền)
- Quản lý mã giảm giá
- Quản lý khách hàng (khóa / mở tài khoản)
- Quản lý đánh giá và bình luận blog (xóa, trả lời khách)
- Soạn thảo blog (trình editor riêng)

---

## Cấu trúc thư mục

```
webwinsum/
├── index.php                      # Front controller — mọi request qua ?view=
├── bootstrap/app.php              # Session, auth, schema, xử lý POST
├── config/database.php            # Kết nối MySQL
│
├── includes/
│   ├── routes.php                 # Định tuyến view + đăng ký CSS/JS
│   ├── helpers.php, csrf.php, flash.php
│   ├── header.php, footer.php
│   ├── auth/                      # Đăng nhập khách
│   ├── handlers/                  # Xử lý form POST storefront
│   ├── repositories/              # Truy vấn DB (một số module)
│   ├── *-repository.php         # Repository theo domain (order, product, …)
│   ├── views/                     # Giao diện storefront
│   ├── admin/                     # Giao diện trang quản trị
│   └── layout/, errors/
│
├── assets/
│   ├── css/, js/, images/
│   └── images/blog-uploads/         # Ảnh blog upload
│   └── images/return-uploads/     # Ảnh minh chứng hoàn hàng
│
└── api/
    ├── checkout-totals.php        # Tính tổng checkout (AJAX)
    └── product-search.php         # Tìm kiếm sản phẩm (AJAX)
```

### Kiến trúc xử lý request

```
Trình duyệt
    → index.php
    → bootstrap/app.php   (auth, POST handlers, schema)
    → includes/routes.php   (resolve view + assets)
    → layout + view PHP
```

- **Repository** (`*-repository.php`): truy vấn và nghiệp vụ DB
- **View** (`includes/views/`, `includes/admin/`): hiển thị HTML
- **Handlers** (`handlers/`, `*-post.php`): xử lý form POST trước khi render view

---

## Định tuyến (routes)

| Trang | URL |
|-------|-----|
| Trang chủ | `?view=home` |
| Sản phẩm | `?view=catalog` |
| Chi tiết SP | `?view=product&slug=...` |
| Giỏ hàng | `?view=cart` |
| Thanh toán | `?view=checkout` |
| Tài khoản | `?view=account` |
| Đơn của tôi | `?view=orders` |
| Chi tiết đơn | `?view=order-detail&id=...` |
| Tra cứu đơn | `?view=order-lookup` |
| Hoàn hàng | `?view=order-return&order_id=...` |
| Blog | `?view=blog` |
| Chi tiết bài viết | `?view=post&slug=...` |
| Soạn blog (admin) | `?view=blog-editor` |
| Admin dashboard | `?view=admin-dashboard` |
| Quản trị đơn | `?view=admin-orders` |
| Quản trị SP | `?view=admin-products` |
| Mã giảm giá | `?view=admin-coupons` |
| Hoàn hàng (admin) | `?view=admin-returns` |
| Khách hàng | `?view=admin-customers` |
| Đánh giá | `?view=admin-reviews` |
| Blog (admin) | `?view=admin-blog` |
| Bình luận blog | `?view=admin-blog-comments` |

---

## Nghiệp vụ quan trọng

| Chủ đề | Quy tắc trong code |
|--------|-------------------|
| Đơn mới | Tạo với trạng thái **Đang giao** (`shipped`) — rút gọn cho demo |
| VietQR / chuyển khoản | `paid` ngay khi khách bấm «Tôi đã thanh toán» |
| COD | `unpaid` lúc đặt; admin tự cập nhật «Đã thanh toán» sau khi giao và thu tiền |
| Doanh thu thuần | Chỉ tính đơn **đã giao + đã thanh toán**, trừ hoàn trả đã hoàn tiền |
| Tồn kho | Trừ khi đặt hàng; hoàn khi hủy hoặc nhận hàng trả (giai đoạn hoàn) |
| Hoàn hàng | 4 bước: chờ duyệt → đã duyệt → đã nhận hàng → hoàn tiền xong |
| Đánh giá | Chỉ khách đã giao hàng; một lần / sản phẩm; hiển thị ngay |
| Bình luận blog | Hiển thị ngay; admin có thể trả lời |
| Giỏ hàng | Lưu trong **PHP Session**, không có bảng `carts` |

### Bảng CSDL lõi (7 thực thể chính)

`customers` · `products` · `categories` · `orders` · `order_items` · `coupons` · `order_return_requests`

Các bảng phụ: `product_images`, `inventory_items`, `order_payments`, `order_shipments`, `product_reviews`, `blog_posts`, `blog_comments`, …

---

## Bảo mật cơ bản

- CSRF token trên form POST
- Prepared statements (chống SQL injection)
- `password_hash` / `password_verify` cho mật khẩu khách hàng
- Chặn trang admin nếu chưa đăng nhập quyền admin
- Khách chỉ xem được đơn của mình
- Khóa tạm 15 phút sau 5 lần đăng nhập sai
- Escape HTML khi hiển thị (chống XSS cơ bản)

---

## Hạn chế đã biết

- VietQR chỉ **mô phỏng** — chưa nối webhook ngân hàng thật
- Đơn mới nhảy thẳng «Đang giao», chưa có bước «Chờ xác nhận / Đóng gói»
- Chưa gửi email / SMS thông báo đơn hàng
- Chưa có kiểm thử tự động (PHPUnit, Selenium)
- Hoàn hàng theo **cả đơn**, chưa hoàn từng sản phẩm riêng lẻ

---

## Xử lý lỗi thường gặp

### Không kết nối được database

1. Kiểm tra MySQL đã **Start** trong XAMPP
2. Tên DB trong `config/database.php` phải là `winsumwebfinal`
3. Thử đổi `localhost` → `127.0.0.1` trong `config/database.php`
4. Nếu báo `Host 'localhost' is not allowed to connect`: kiểm tra quyền user MariaDB hoặc sửa bảng `mysql.global_priv`
5. Import lại file SQL nếu bảng trống hoặc thiếu cột

### Trang trắng / lỗi 500

1. Bật `display_errors = On` tạm thời trong `php.ini` để xem lỗi cụ thể
2. Kiểm tra PHP ≥ 8.0 và extension `mysqli` đã bật

### Ảnh sản phẩm / danh mục không hiện

1. Kiểm tra thư mục `assets/images/` còn đủ file
2. Đường dẫn ảnh trong DB phải khớp cấu trúc project

### Đăng nhập admin không được

1. Dùng SĐT `0901000000` hoặc email `admin@winsumhome.vn`, không phải tên hiển thị `admin`
2. Mật khẩu mặc định: `admin123` (tài khoản được bootstrap tự tạo nếu DB chưa có admin)

---

## Tài liệu đồ án

Các file báo cáo (test case, mô tả CSDL, luồng nghiệp vụ, rủi ro triển khai) thường nộp **riêng** cùng đồ án hoặc đặt trong thư mục `docs/` nếu nhóm có bản export Word / Excel / PDF.

---

*Đồ án môn học — Winsum Home. PHP thuần, MySQL, XAMPP.*
