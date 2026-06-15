# Hướng dẫn cài đặt Winsum Home

Tài liệu này hướng dẫn cài project trên Windows + XAMPP. Em viết theo đúng các bước nhóm đã làm khi phát triển.

---

## 1. Yêu cầu phần cứng & phần mềm

| Thành phần | Yêu cầu |
|------------|---------|
| Hệ điều hành | Windows 10/11 |
| XAMPP | Apache + MySQL + PHP 8.0+ |
| Trình duyệt | Chrome hoặc Edge (phiên bản mới) |
| Dung lượng | ~500 MB (bao gồm ảnh và database) |

---

## 2. Cài XAMPP

1. Tải XAMPP tại [https://www.apachefriends.org](https://www.apachefriends.org)
2. Cài vào `C:\xampp` (mặc định)
3. Mở **XAMPP Control Panel**
4. Bấm **Start** cho **Apache** và **MySQL**
5. Nếu Apache báo lỗi port 80 bị chiếm: đổi port Apache sang 8080 hoặc tắt Skype/IIS tạm thời

---

## 3. Copy mã nguồn

Copy toàn bộ thư mục project vào:

```
C:\xampp\htdocs\webwinsum
```

Cấu trúc đúng khi mở thư mục phải thấy ngay file `index.php` ở root.

> Nếu đặt tên thư mục khác (ví dụ `winsumweb`), URL sẽ là `http://localhost/winsumweb` — code vẫn chạy bình thường.

---

## 4. Tạo database và import dữ liệu

### Cách 1: Dòng lệnh (em dùng khi dev)

Mở **CMD** hoặc **PowerShell**:

```bash
cd C:\xampp\htdocs\webwinsum

"C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS winsumwebfinal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

"C:\xampp\mysql\bin\mysql.exe" -u root winsumwebfinal < "winsumwebfinal (9).sql"
```

### Cách 2: phpMyAdmin

1. Mở `http://localhost/phpmyadmin`
2. Tạo database tên `winsumwebfinal`, collation `utf8mb4_unicode_ci`
3. Chọn database → tab **Import**
4. Chọn file `winsumwebfinal (9).sql` → **Go**

### Script bổ sung (tuỳ chọn)

```bash
"C:\xampp\mysql\bin\mysql.exe" -u root winsumwebfinal < docs/sql/migrate-coupon-role.sql
"C:\xampp\mysql\bin\mysql.exe" -u root winsumwebfinal < docs/sql/seed-coupons.sql
```

---

## 5. Cấu hình kết nối database

Mở file `config/database.php`:

```php
$host = 'localhost';
$db   = 'winsumwebfinal';
$user = 'root';
$pass = '';          // Mặc định XAMPP để trống
$charset = 'utf8mb4';
```

Nếu MySQL có mật khẩu root, sửa `$pass` cho khớp.

---

## 6. Chạy thử website

1. Đảm bảo Apache + MySQL đang chạy
2. Mở trình duyệt: **http://localhost/webwinsum**
3. Trang chủ hiện banner, danh mục, sản phẩm → cài đặt thành công

### Kiểm tra nhanh

| Việc cần thử | Kết quả mong đợi |
|--------------|------------------|
| Mở `?view=catalog` | Danh sách sản phẩm |
| Đăng nhập `admin` / `admin123` | Vào được admin dashboard |
| Thêm SP vào giỏ | Toast báo thành công |

---

## 7. Quyền thư mục upload

Thư mục `uploads/` cần ghi được khi khách upload ảnh hoàn hàng hoặc admin upload blog:

- Windows + XAMPP thường không cần cấu hình thêm
- Nếu upload lỗi: kiểm tra thư mục `uploads/returns/` tồn tại và có quyền ghi

---

## 8. Xử lý lỗi thường gặp

### «LỖI KẾT NỐI DATABASE»

- MySQL chưa Start trong XAMPP
- Sai tên database trong `config/database.php`
- Chưa import file SQL

### Trang trắng

- Kiểm tra PHP version ≥ 8.0: `C:\xampp\php\php.exe -v`
- Xem log Apache: `C:\xampp\apache\logs\error.log`

### Tiếng Việt hiện `????`

- Database phải dùng `utf8mb4`
- Import lại SQL với charset đúng

### Ảnh sản phẩm / danh mục không hiện

- Kiểm tra folder `assets/images/` còn file
- Đường dẫn ảnh trong DB (ví dụ `assets/images/products/...`)

### Admin không đăng nhập được

- Dùng đúng: `admin` / `admin123` trên form **Đăng nhập** trang chủ (không phải form đăng ký khách)
- Nếu vẫn lỗi: kiểm tra bảng `admin_users` trong DB đã có dòng admin

---

## 9. Chuẩn bị máy demo bảo vệ

Em khuyên trước ngày bảo vệ nên:

1. Import lại DB sạch từ file SQL
2. Chạy script coupon nếu cần demo mã giảm giá
3. Tạo sẵn:
   - 1 đơn **COD** trạng thái đang giao (để demo hủy / giao hàng)
   - 1 đơn **đã giao** (để demo hoàn hàng + đánh giá)
4. Mở thử kịch bản trong [`testing/DEMO-SCRIPT.md`](testing/DEMO-SCRIPT.md)

---

*Tài liệu thuộc đồ án Winsum Home — nhóm thực hiện.*
