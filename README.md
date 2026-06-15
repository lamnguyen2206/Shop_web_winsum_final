# Winsum Home — Website bán đèn & nội thất

Đồ án tốt nghiệp: xây dựng website thương mại điện tử bán đèn trang trí và nội thất. Nhóm em dùng **PHP thuần + MySQL**, chạy trên **XAMPP**, không dùng framework.

**Demo:** [http://localhost/webwinsum](http://localhost/webwinsum)

---

## 1. Giới thiệu nhanh

Winsum Home gồm hai phần:

| Phần | Mô tả |
|------|--------|
| **Storefront** | Khách xem sản phẩm, giỏ hàng, đặt hàng, tra cứu đơn, hoàn hàng, đánh giá, đọc blog |
| **Admin** | Quản trị sản phẩm, tồn kho, đơn hàng, hoàn trả, khách hàng, đánh giá, blog |

Luồng chính em tập trung xử lý: **đặt hàng → thanh toán (COD / VietQR demo) → giao hàng → hoàn hàng 4 bước → thống kê doanh thu**.

Chi tiết luồng nghiệp vụ: xem [`docs/luong-nghiep-vu.md`](docs/luong-nghiep-vu.md).

---

## 2. Công nghệ

| Thành phần | Phiên bản / ghi chú |
|------------|---------------------|
| PHP | 8.0 trở lên |
| MySQL / MariaDB | Database `winsumwebfinal` |
| Apache | XAMPP |
| Frontend | HTML, CSS, JavaScript thuần (Chart.js cho biểu đồ admin) |

---

## 3. Cài đặt trên máy mới

Tóm tắt — hướng dẫn đầy đủ: [`docs/huong-dan-cai-dat.md`](docs/huong-dan-cai-dat.md).

```bash
# 1. Copy project vào htdocs
#    Ví dụ: C:\xampp\htdocs\webwinsum

# 2. Tạo database và import dữ liệu mẫu
mysql -u root -e "CREATE DATABASE IF NOT EXISTS winsumwebfinal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root winsumwebfinal < "winsumwebfinal (9).sql"

# 3. (Tuỳ chọn) Chạy thêm script bổ sung coupon
mysql -u root winsumwebfinal < docs/sql/migrate-coupon-role.sql
mysql -u root winsumwebfinal < docs/sql/seed-coupons.sql
```

1. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
2. Kiểm tra file `config/database.php` (host, user, tên DB).
3. Mở trình duyệt: `http://localhost/webwinsum`

> **Lưu ý:** Nếu copy vào thư mục khác (ví dụ `winsumweb`), đổi URL tương ứng. Code dùng đường dẫn tương đối `index.php?view=...` nên không cần sửa config base URL.

---

## 4. Tài khoản dùng khi demo

| Vai trò | Cách đăng nhập |
|---------|----------------|
| **Admin** | Trang chủ → Đăng nhập → `admin` / `admin123` |
| **Khách hàng** | Tự đăng ký qua popup trên header |
| **Mã giảm giá** | `WINSUMXINCHAO` — giảm 40.000đ (trên giỏ hàng) |

Admin đăng nhập xong vào **Bảng quản trị** qua menu hoặc `?view=admin-dashboard`.

---

## 5. Chức năng đã làm

### Storefront (khách hàng)

- Trang chủ: banner, danh mục, sản phẩm nổi bật, tin tức
- Danh mục sản phẩm: lọc danh mục / màu / giá, tìm kiếm, sắp xếp, phân trang
- Chi tiết sản phẩm: gallery, thông số, tab đánh giá
- Giỏ hàng + mã giảm giá
- Checkout: khách vãng lai hoặc đã đăng nhập; COD hoặc VietQR (demo)
- Đơn hàng của tôi / tra cứu đơn bằng mã + SĐT
- Hủy đơn (khi đơn còn trạng thái **Đang giao**)
- Yêu cầu hoàn hàng (trong 7 ngày sau khi giao)
- Blog + bình luận (hiển thị ngay sau khi gửi)
- Đánh giá sản phẩm (chỉ khách đã mua và đơn đã giao)

### Admin (quản trị)

- Dashboard: thống kê đơn, doanh thu thuần, hoàn trả, cảnh báo tồn kho
- **Biểu đồ doanh thu** theo ngày / tháng / năm / khoảng thời gian
- CRUD sản phẩm + quản lý tồn kho
- Quản lý đơn: cập nhật giao hàng, thanh toán
- Hoàn hàng 4 giai đoạn (duyệt → nhận hàng → hoàn tiền)
- Quản lý khách hàng (khóa/mở tài khoản)
- Quản lý đánh giá & bình luận blog (xóa, trả lời khách)
- Soạn thảo blog (trình editor riêng)

---

## 6. Cấu trúc thư mục

```
webwinsum/
├── index.php                 # Front controller — mọi request qua ?view=
├── bootstrap/app.php         # Khởi tạo session, auth, xử lý POST
├── config/database.php       # Kết nối MySQL
│
├── includes/
│   ├── routes.php            # Định tuyến view + đăng ký CSS/JS
│   ├── helpers.php, csrf.php, flash.php
│   ├── header.php, footer.php
│   ├── auth/                 # Đăng nhập khách + admin
│   ├── handlers/             # Xử lý form POST (storefront, blog editor)
│   ├── repositories/         # Truy vấn DB (*-repository.php)
│   ├── views/                # Giao diện storefront
│   ├── admin/                # Giao diện + logic trang quản trị
│   └── layout/, errors/
│
├── assets/css, assets/js, assets/images/
├── api/                      # API phụ (checkout totals, search)
├── uploads/                  # Ảnh hoàn hàng, blog, ...
│
├── docs/                     # Tài liệu đồ án (xem docs/README.md)
├── scripts/                  # Script tiện ích (split SQL, ...)
└── winsumwebfinal (9).sql    # File dump database mẫu
```

**Kiến trúc:** Em tách **repository** (truy vấn DB) và **view** (HTML). Form POST được xử lý sớm trong `bootstrap/app.php` qua các handler, tránh trộn logic vào view.

---

## 7. Định tuyến (routes)

| Trang | URL |
|-------|-----|
| Trang chủ | `?view=home` |
| Sản phẩm | `?view=catalog` |
| Chi tiết SP | `?view=product&slug=...` |
| Giỏ hàng | `?view=cart` |
| Thanh toán | `?view=checkout` |
| Đơn của tôi | `?view=orders` |
| Tra cứu đơn | `?view=order-lookup` |
| Blog | `?view=blog` |
| Admin | `?view=admin-dashboard` |

---

## 8. Nghiệp vụ quan trọng (tóm tắt)

Em ghi lại để khi bảo vệ không bị hỏi “vì sao”:

| Chủ đề | Quy tắc trong code |
|--------|-------------------|
| Đơn mới | Tạo với trạng thái **Đang giao** (`shipped`) — rút gọn cho demo |
| VietQR / chuyển khoản | `paid` ngay khi khách bấm «Tôi đã thanh toán» |
| COD | `unpaid` lúc đặt; admin **tự cập nhật** «Đã thanh toán» sau khi giao và thu tiền |
| Doanh thu thuần | Chỉ tính đơn **đã giao + đã thanh toán** |
| Tồn kho | Trừ khi đặt hàng; hoàn khi hủy hoặc nhận hàng trả (giai đoạn 3 hoàn) |
| Hoàn hàng | 4 bước; chưa hoàn tiền thì doanh thu chưa giảm |
| Đánh giá | Chỉ khách đã giao hàng; 1 lần / sản phẩm; hiển thị ngay |
| Bình luận blog | Hiển thị ngay; admin có thể trả lời |

Giải thích chi tiết + sơ đồ: [`docs/luong-nghiep-vu.md`](docs/luong-nghiep-vu.md).

---

## 9. Bảo mật cơ bản

- CSRF token trên form POST
- Prepared statements (chống SQL injection)
- `password_hash` cho mật khẩu khách hàng
- Chặn truy cập trang admin nếu chưa đăng nhập admin
- Khách chỉ xem được đơn của mình
- Khóa tạm 15 phút sau 5 lần đăng nhập sai
- Escape HTML khi hiển thị (chống XSS cơ bản)

---

## 10. Hạn chế (em ghi thẳng trong báo cáo)

- VietQR chỉ **mô phỏng** — chưa nối webhook ngân hàng thật
- Đơn mới nhảy thẳng «Đang giao», chưa có bước «Chờ xác nhận / Đóng gói»
- Chưa có email/SMS thông báo đơn hàng
- Chưa kiểm thử tự động (PHPUnit, Selenium)
- Hoàn hàng theo **cả đơn**, chưa hoàn từng sản phẩm

---

## 11. Tài liệu kèm theo

| File | Nội dung |
|------|----------|
| [`docs/README.md`](docs/README.md) | Mục lục tài liệu |
| [`docs/huong-dan-cai-dat.md`](docs/huong-dan-cai-dat.md) | Cài đặt chi tiết + xử lý lỗi |
| [`docs/luong-nghiep-vu.md`](docs/luong-nghiep-vu.md) | Luồng nghiệp vụ + sơ đồ |
| [`docs/testing/TEST-CASES.md`](docs/testing/TEST-CASES.md) | Bảng test case |
| [`docs/testing/DEMO-SCRIPT.md`](docs/testing/DEMO-SCRIPT.md) | Kịch bản demo bảo vệ |
| [`docs/database/`](docs/database/) | Thiết kế CSDL, ERD |

Sinh báo cáo kiểm thu Excel/PDF/HTML:

```bash
C:\xampp\php\php.exe docs/testing/generate-test-report.php
```

Hoặc chạy `docs/testing/export-test-report.bat`.

---

## 12. Gặp lỗi thường gặp

**Không kết nối được database**

1. Kiểm tra MySQL đã Start trong XAMPP
2. Tên DB trong `config/database.php` phải là `winsumwebfinal`
3. Import lại file SQL nếu bảng trống

**Trang trắng / lỗi 500**

1. Bật hiển thị lỗi PHP tạm thời trong `php.ini` (`display_errors = On`)
2. Kiểm tra PHP ≥ 8.0

**Ảnh sản phẩm / danh mục không hiện**

1. Kiểm tra thư mục `assets/images/` còn đủ file
2. Đường dẫn ảnh trong DB phải khớp cấu trúc project

---

*Đồ án môn học — Winsum Home. Mọi thắc mắc khi chấm bài có thể tham khảo thêm tài liệu trong thư mục `docs/`.*
