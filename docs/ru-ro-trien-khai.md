# Rủi ro triển khai — Winsum Home

Tài liệu này mô tả các rủi ro nhóm em nhận thấy khi cài đặt, chạy thử và chuẩn bị demo đồ án. Hệ thống hiện phù hợp **môi trường XAMPP local**, chưa sẵn sàng vận hành thương mại công khai nếu không xử lý thêm các rủi ro mức cao.

---

## 1. Rủi ro môi trường cài đặt

| Rủi ro | Mô tả | Mức độ |
|--------|--------|--------|
| Phụ thuộc XAMPP | Apache + MySQL + PHP 8.0+ trên Windows. Máy demo thiếu XAMPP hoặc sai phiên bản PHP có thể không chạy được. | Trung bình |
| Xung đột cổng | Port 80 (Apache) hoặc 3306 (MySQL) bị chiếm bởi IIS/Skype → website hoặc DB không khởi động. | Trung bình |
| Sai đường dẫn / cấu hình | Copy project sai thư mục `htdocs`, import nhầm DB, hoặc `config/database.php` không khớp → lỗi kết nối. | Thấp |

**Biện pháp:** Hướng dẫn chi tiết tại `docs/huong-dan-cai-dat.md`; URL mặc định `http://localhost/webwinsum`.

---

## 2. Rủi ro dữ liệu và CSDL

| Rủi ro | Mô tả | Mức độ |
|--------|--------|--------|
| Import SQL thất bại | File dump lớn, phpMyAdmin có thể timeout nếu giới hạn upload/php thấp. | Trung bình |
| Lệch schema | Cần chạy script migrate bổ sung (`docs/sql/`). Bỏ qua có thể làm coupon/tồn kho không khớp code. | Trung bình |
| Mất dữ liệu demo | Không có backup tự động trên máy local; cài lại XAMPP có thể mất đơn hàng mẫu. | Thấp |

---

## 3. Rủi ro bảo mật (nếu đưa lên internet)

| Rủi ro | Mô tả | Mức độ |
|--------|--------|--------|
| Tài khoản demo | `admin` / `admin123` ghi trong README — nguy hiểm nếu public mà không đổi mật khẩu. | **Cao** |
| HTTP không mã hóa | Localhost chưa HTTPS; deploy production cần SSL. | **Cao** |
| MySQL root rỗng | Cấu hình XAMPP mặc định chỉ phù hợp dev. | **Cao** |

---

## 4. Rủi ro nghiệp vụ và thanh toán

| Rủi ro | Mô tả | Mức độ |
|--------|--------|--------|
| VietQR demo | Khách tự bấm «Đã thanh toán»; không có webhook ngân hàng đối soát thật. | **Cao** |
| COD thủ công | Admin phải cập nhật `paid` sau giao hàng; quên thao tác → doanh thu dashboard sai. | Trung bình |
| Luồng hoàn 4 bước | Admin thao tác sai thứ tự có thể lệch tồn kho / doanh thu. | Trung bình |

---

## 5. Rủi ro hiệu năng

| Rủi ro | Mô tả | Mức độ |
|--------|--------|--------|
| Giỏ hàng Session | Không đồng bộ đa thiết bị; session hết hạn có thể mất giỏ. | Thấp (demo) |
| Race condition tồn kho | Chưa test nhiều người đặt cùng lúc SP cuối cùng. | Trung bình |
| Chưa test tải | Chỉ kiểm thử thủ công trên một máy. | Trung bình |

---

## 6. Kết luận

Hệ thống **đủ điều kiện demo và bảo vệ đồ án** (161/161 test case Pass). Triển khai kinh doanh thật cần: HTTPS, đổi credential, cổng thanh toán có webhook, backup CSDL, khóa tồn kho bằng transaction, và bổ sung test tự động (PHPUnit).

*Cập nhật: 06/2026 — Nhóm phát triển đồ án Winsum Home.*
