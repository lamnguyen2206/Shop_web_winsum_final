# Kịch bản demo bảo vệ đồ án Winsum Home

Em dùng kịch bản này khi trình bày trước hội đồng (khoảng **5–7 phút**). Nên chạy thử 1–2 lần trước ngày bảo vệ.

---

## Chuẩn bị trước khi demo

- [ ] Bật XAMPP: Apache + MySQL
- [ ] Import database `winsumwebfinal` (file `winsumwebfinal (9).sql`)
- [ ] Mở **http://localhost/webwinsum**
- [ ] Chuẩn bị sẵn:
  - Tài khoản admin: `admin` / `admin123`
  - Một tài khoản khách (tự đăng ký)
  - Mã coupon: `WINSUMXINCHAO`
  - Một sản phẩm còn hàng
  - (Tuỳ chọn) Một đơn **đã giao** để demo hoàn hàng / đánh giá nhanh

---

## Kịch bản trình bày

### Bước 1 — Giới thiệu (30 giây)

> «Em xin trình bày website Winsum Home — bán đèn và nội thất. Hệ thống có hai vai trò: khách hàng mua online và quản trị viên xử lý đơn, tồn kho, hoàn trả. Em dùng PHP thuần + MySQL, chạy trên XAMPP.»

---

### Bước 2 — Khách mua hàng (1 phút)

1. Mở trang chủ → danh mục sản phẩm
2. Lọc hoặc tìm kiếm một sản phẩm
3. Vào chi tiết: xem gallery, mô tả, tab đánh giá
4. Thêm vào giỏ hàng

> «Phần storefront có catalog lọc theo danh mục, giá, màu và tìm kiếm nhanh trên header.»

---

### Bước 3 — Giỏ hàng & checkout (1 phút)

1. Mở giỏ hàng, có thể sửa số lượng
2. Nhập mã `WINSUMXINCHAO` → giảm 40.000đ
3. Vào thanh toán
4. Điền SĐT 10 số (bắt đầu bằng 0), địa chỉ 3 phần
5. Chọn **Chuyển khoản VietQR**

> «Coupon được validate theo SĐT và số lượt dùng. Khi đặt hàng thành công hệ thống trừ tồn kho ngay.»

---

### Bước 4 — VietQR demo (45 giây)

1. Sau khi đặt, trang hiện mã QR
2. Giải thích: *«Đây là bản demo — thực tế cần webhook ngân hàng. Em mô phỏng bằng nút xác nhận.»*
3. Bấm **«Tôi đã thanh toán»** → trạng thái chuyển **Đã thanh toán**
4. Khách login → «Đơn hàng của tôi»; guest → tra cứu bằng mã đơn + SĐT

---

### Bước 5 — Admin xử lý đơn (1,5 phút)

1. Đăng xuất khách → đăng nhập `admin` / `admin123`
2. Vào **Quản lý đơn hàng** → mở đơn vừa tạo
3. Bấm **Xác nhận đã giao**
4. Mở **Dashboard**:
   - Giải thích **Doanh thu thuần** = đã giao + đã thanh toán
   - Demo **biểu đồ doanh thu** — đổi lọc theo tháng / ngày

> «Đơn VietQR đã paid từ trước nhưng doanh thu thuần chỉ tính khi admin xác nhận giao xong — em thiết kế vậy để sát nghiệp vụ thực tế.»

**Nếu demo COD thêm:**

> «Đơn COD ban đầu là chưa thanh toán. Sau khi giao, admin **tự bấm cập nhật** «Đã thanh toán» khi đã thu tiền mặt — không tự động.»

5. (Tuỳ chọn) Thử hủy đơn **đã giao** phía khách → hệ thống từ chối

---

### Bước 6 — Tồn kho & sản phẩm (45 giây)

1. Mở **Quản lý sản phẩm**
2. Sửa tồn kho hoặc thông tin SP
3. Nếu có cảnh báo hết hàng trên dashboard → giải thích alert

> «Tồn kho lưu cùng transaction với sản phẩm. Khi hủy đơn hoặc nhận hàng hoàn thì kho được cộng lại.»

---

### Bước 7 — Hoàn hàng & đánh giá (1,5 phút)

**Hoàn hàng (nếu có đơn delivered):**

1. Khách login → đơn đã giao → **Yêu cầu hoàn hàng** (trong 7 ngày)
2. Admin → **Hoàn hàng** → duyệt → nhận hàng hoàn → hoàn tiền (4 bước)
3. Giải thích: giai đoạn 3 mới cộng kho; giai đoạn 4 mới trừ doanh thu

**Đánh giá:**

1. Khách mở SP trong đơn đã giao → gửi đánh giá
2. Review hiện ngay trên tab đánh giá
3. Admin có thể trả lời hoặc xóa trên trang quản trị / ngay trên trang SP

**Blog (nếu còn thời gian):**

- Gửi bình luận → hiện ngay; admin trả lời nested

---

## Câu kết khi kết thúc demo

> «Tóm lại, Winsum Home đã làm được luồng mua hàng, thanh toán demo, quản lý đơn, tồn kho, hoàn hàng 4 giai đoạn, thống kê doanh thu có biểu đồ, đánh giá và blog. Em đã kiểm thử 161 test case thủ công, tỷ lệ pass 100%. Hướng phát triển tiếp là tích hợp cổng thanh toán thật và kiểm thử tự động. Em xin cảm ơn thầy cô và hội đồng.»

---

## Câu hỏi hội đồng hay gặp — gợi ý trả lời

| Câu hỏi | Gợi ý trả lời |
|---------|----------------|
| Vì sao đơn mới là «Đang giao»? | Em rút gọn luồng demo, tập trung phần giao hàng, hoàn trả và doanh thu |
| COD có tự paid không? | Không — admin cập nhật thủ công sau khi thu tiền, giống shop nhỏ |
| Doanh thu tính khi nào? | `delivered` + `paid`; hoàn trừ ở bước hoàn tiền cuối |
| VietQR có thật không? | Demo — cần webhook ngân hàng ở bản production |
| Chống SQL injection? | Prepared statements + CSRF trên form |

---

## Tài liệu tham chiếu khi bị hỏi sâu

- Luồng nghiệp vụ: [`../luong-nghiep-vu.md`](../luong-nghiep-vu.md)
- Test case: [`TEST-CASES.md`](TEST-CASES.md)
- Báo cáo kiểm thu: chạy `generate-test-report.php`

---

*Kịch bản demo — đồ án Winsum Home.*
