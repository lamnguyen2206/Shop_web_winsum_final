# Winsum Home

Website bán hàng nội thất và thiết bị chiếu sáng — PHP thuần + MySQL, chạy trên XAMPP.

## 1. Công nghệ

- PHP 8+
- MySQL 
- Apache (XAMPP)
- HTML / CSS / JavaScript (thuần, không framework)

## 2. Cài đặt nhanh

1. Đặt project vào `C:\xampp\htdocs\webwinsum`
2. Tạo database (gợi ý tên): `winsumwebfinal`
3. Import dữ liệu:

```bash
mysql -u root winsumwebfinal < "winsumwebfinal (9).sql"
```


4. Chỉnh kết nối DB trong `config/database.php` (`$host`, `$db`, `$user`, `$pass`)
5. Mở trình duyệt:

```
http://localhost/webwinsum/index.php
```

## 3. Tài khoản demo

| Loại | Thông tin đăng nhập |
|------|---------------------|
| Admin | `admin` / `admin123` (hoặc `admin@winsumhome.vn`) |
| Mã giảm giá | `WINSUMXINCHAO` (giảm 40.000đ) |
| Khách hàng | Đăng ký qua popup trên header |

## 4. Chức năng chính

### Khách hàng (Storefront)

- **Trang chủ**: hero cố định, voucher, sản phẩm chủ lực, bài viết mới
- **Danh mục sản phẩm**: lọc theo danh mục / màu / giá, tìm kiếm, sắp xếp, phân trang
- **Chi tiết sản phẩm**: gallery ảnh, mô tả, thông số kỹ thuật, đánh giá sao
- **Giỏ hàng**: thêm / sửa / xóa sản phẩm, áp mã giảm giá
- **Thanh toán**: COD hoặc VietQR demo; không cho xác nhận thanh toán với đơn đã hủy / hoàn trả
- **Quản lý tài khoản**: đăng ký, đăng nhập, hồ sơ, sổ địa chỉ
- **Đơn hàng**: danh sách đơn, chi tiết đơn, hủy đơn khi chưa giao vận, yêu cầu hoàn hàng trong 7 ngày sau khi giao (khách đăng nhập)
- **Tra cứu đơn khách vãng lai**: nhập mã đơn + số điện thoại; khách đã đăng nhập dùng trang đơn hàng của mình
- **Blog**: danh sách bài viết, chi tiết bài viết, bình luận (admin duyệt)
- **Đánh giá sản phẩm**: chỉ khách đã mua và đơn đã giao thành công mới được đánh giá, mỗi sản phẩm chỉ đánh giá một lần; đánh giá hiển thị công khai ngay khi gửi

### Quản trị (Admin)

- **Dashboard**: doanh thu thuần (đã giao + đã thu), hoàn trả, đơn hàng, khách hàng
- **Quản lý sản phẩm**: tách trang danh sách, thêm mới, sửa chi tiết; cập nhật tồn kho cùng transaction lưu sản phẩm
- **Tồn kho**: theo dõi số lượng tồn, cảnh báo hết hàng tự động, hoàn kho khi hủy / trả đơn
- **Quản lý đơn hàng**: cập nhật trạng thái, thanh toán, vận chuyển; khóa đơn terminal; COD tự paid khi giao
- **Hoàn hàng / khiếu nại**: duyệt hoặc từ chối yêu cầu khách (admin không tự set returned)
- **Quản lý khách hàng**: xem, tìm kiếm khách hàng
- **Quản lý đánh giá sản phẩm** (xóa nội dung không phù hợp)
- **Quản lý blog**: soạn / sửa / xóa bài viết, duyệt bình luận

## 5. Routing

| View | URL |
|------|-----|
| Trang chủ | `index.php?view=home` |
| Danh mục | `index.php?view=catalog` |
| Chi tiết sản phẩm | `index.php?view=product&slug=...` |
| Giỏ hàng | `index.php?view=cart` |
| Thanh toán | `index.php?view=checkout` |
| Đơn hàng của tôi | `index.php?view=orders` |
| Tra cứu đơn (guest) | `index.php?view=order-lookup` |
| Blog | `index.php?view=blog` |
| Chi tiết bài viết | `index.php?view=post&slug=...` |
| Admin Dashboard | `index.php?view=admin-dashboard` |
| Admin Sản phẩm | `index.php?view=admin-products` |
| Admin Thêm sản phẩm | `index.php?view=admin-product-create` |
| Admin Sửa sản phẩm | `index.php?view=admin-product-edit&id=...` |
| Admin Đơn hàng | `index.php?view=admin-orders` |
| Admin Chi tiết đơn | `index.php?view=admin-order-detail&code=...` |
| Admin Khách hàng | `index.php?view=admin-customers` |
| Admin Blog | `index.php?view=admin-blog` |
| Admin Đánh giá | `index.php?view=admin-reviews` |
| Admin Hoàn hàng | `index.php?view=admin-returns` |

## 6. Cấu trúc thư mục

```
index.php                   Front controller
bootstrap/app.php           Boot app, auth, xử lý POST
config/database.php         Cấu hình kết nối MySQL

includes/
  routes.php                Định tuyến view
  helpers.php               Hàm tiện ích (e(), app_url, ...)
  csrf.php                  CSRF token
  flash.php                 Flash message

  customer-auth.php         Xác thực khách hàng
  customer-auth-post.php    Xử lý đăng ký / đăng nhập
  admin-auth.php            Xác thực admin

  product-repository.php    Truy vấn sản phẩm
  product-admin-repository.php
  inventory-repository.php  Quản lý tồn kho
  order-repository.php      Tạo / truy vấn đơn hàng
  return-repository.php     Yêu cầu hoàn hàng
  coupon-repository.php     Mã giảm giá
  review-repository.php     Đánh giá sản phẩm
  blog-repository.php       Truy vấn blog
  blog-comment-repository.php
  home-repository.php       Dữ liệu trang chủ

  cart-store.php             Session cart
  storefront-post.php        Xử lý POST storefront
  admin-post.php             Xử lý POST admin

  home.php                   Trang chủ
  catalog.php                Danh mục sản phẩm
  product-detail.php         Chi tiết sản phẩm
  cart.php                   Giỏ hàng
  checkout.php               Thanh toán
  my-orders.php              Đơn hàng
  order-detail.php           Chi tiết đơn
  order-lookup.php           Tra cứu đơn (guest)
  account.php                Tài khoản
  blog.php / blog-detail.php Blog

  admin-dashboard.php        Dashboard admin
  admin-products.php         Danh sách sản phẩm
  admin-product-form.php     Form thêm / sửa chi tiết sản phẩm
  admin-orders.php           Quản lý đơn hàng
  admin-order-detail.php     Chi tiết đơn (admin)
  admin-customers.php        Quản lý khách hàng
  admin-reviews.php          Quản lý đánh giá
  admin-returns.php          Duyệt hoàn hàng
  admin-blog.php             Quản lý blog
  admin-blog-comments.php    Duyệt bình luận

  layout/                    Header, footer, sidebar

assets/
  css/                       Stylesheet
  js/                        JavaScript
  images/                    Ảnh sản phẩm, banner

docs/
  database-design.html       Thiết kế CSDL (lược đồ, mô tả bảng, PK/FK)
  er-diagrams.html           Biểu đồ ER chia theo nhóm chức năng
  bao-cao-kiem-thu.html      Báo cáo kiểm thử (HTML)
  bao-cao-kiem-thu.xlsx      Báo cáo kiểm thử (Excel)
  TEST-CASES.md              Test cases
  generate-test-report.php   Sinh HTML + Markdown + Excel
  DEMO-SCRIPT.md             Kịch bản demo

winsumwebfinal (2).sql       Schema + dữ liệu mẫu
```

## 7. Bảo mật & nghiệp vụ

- **CSRF**: token cho mọi form POST
- **SQL Injection**: prepared statements toàn bộ
- **Mật khẩu**: `password_hash()` / `password_verify()`
- **Phân quyền**: chặn view `admin-*` và `blog-editor` nếu không phải admin
- **Validation SĐT**: đúng 10 số, bắt đầu bằng `0`
- **Tồn kho**: tự động trừ khi đặt hàng, đánh dấu từng dòng đã trừ, hoàn một lần khi hủy / trả đơn (`inventory_restocked`), cảnh báo hết hàng
- **Đơn hàng**: trạng thái đơn và vận chuyển được đồng bộ; khách chỉ hủy khi đơn chưa giao vận
- **Thanh toán**: VietQR demo không xác nhận được với đơn đã hủy / hoàn trả
- **Đánh giá**: chỉ đánh giá sản phẩm trong đơn đã giao thành công, chặn đánh giá trùng, công khai ngay khi gửi
- **Đăng nhập**: chỉ SĐT hoặc email; `session_regenerate_id` sau login/register; chặn tạm sau 5 lần sai (15 phút)
- **Khách bị khóa**: session bị xóa ngay khi `customerCurrent()` phát hiện `status !== active`
- **Mã giảm giá**: validate tại checkout; đếm lượt loại đơn hủy/hoàn trả; kiểm tra lại trong transaction; hoàn lượt khi hủy đơn
- **Đơn hàng**: không lùi trạng thái từ shipped/delivered; không sửa thanh toán khi đơn ở trạng thái cuối; hoàn hàng chỉ qua duyệt khiếu nại
- **Doanh thu thuần**: chỉ đơn `delivered` + `paid`; đơn `returned` tính vào giảm trừ
- **COD**: tự chuyển `paid` khi admin đánh dấu đã giao
- **Tồn kho**: bắt buộc có dòng `inventory_items` khi đặt hàng; admin luôn tạo/cập nhật tồn kho khi lưu sản phẩm
- **Mã đơn**: sinh mã đơn có kiểm tra trùng trước khi lưu

## 8. Tài liệu

| Tài liệu | Đường dẫn |
|-----------|-----------|
| Thiết kế CSDL | `docs/database-design.html` |
| Biểu đồ ER | `docs/er-diagrams.html` |
| Test cases | `docs/TEST-CASES.md` |
| Báo cáo kiểm thử (Excel) | `docs/bao-cao-kiem-thu.xlsx` |
| Báo cáo kiểm thử (HTML) | `docs/bao-cao-kiem-thu.html` |
| Kịch bản demo | `docs/DEMO-SCRIPT.md` |

---

**Khắc phục lỗi kết nối DB:**

1. Kiểm tra đã import đúng file SQL chưa
2. Tên database trong `config/database.php` có khớp không
3. Apache + MySQL đã bật trong XAMPP chưa
