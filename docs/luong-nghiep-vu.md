# Luồng nghiệp vụ Winsum Home

Em tóm tắt các luồng chính để khi viết báo cáo và bảo vệ không bị lệch giữa slide và code thực tế.

---

## 1. Tổng quan hệ thống

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  Storefront │     │  Repository  │     │   MySQL     │
│  (views)    │ ──► │  (PHP)       │ ──► │ winsumwebfinal │
└─────────────┘     └──────────────┘     └─────────────┘
       ▲                    ▲
       │                    │
┌─────────────┐     ┌──────────────┐
│   Admin     │     │  Handlers    │
│   (views)   │     │  (POST)      │
└─────────────┘     └──────────────┘
```

- **Storefront:** khách mua hàng, xem blog, đánh giá
- **Admin:** quản trị sản phẩm, đơn, hoàn trả, thống kê
- **Repository:** toàn bộ truy vấn DB (order, return, inventory, coupon…)
- **Handlers:** xử lý form POST trước khi render view

---

## 2. Luồng mua hàng

```
Duyệt SP → Thêm giỏ → (Áp coupon) → Checkout → Tạo đơn WS...
                                              ↓
                                    Trừ tồn kho (nếu còn hàng)
                                              ↓
                              Trạng thái: shipped / Đang giao
```

### Giải thích em chọn luồng rút gọn

Khi khách đặt hàng thành công, hệ thống **không** tạo đơn «Chờ xác nhận» mà nhảy thẳng sang **Đang giao** (`status = shipped`, `fulfillment_status = shipped`).

**Lý do:** Đồ án tập trung demo phần thanh toán, giao hàng, hoàn trả và thống kê — bỏ bớt bước đóng gói cho gọn khi trình bày.

**File liên quan:** `includes/repositories/order-repository.php` (hàm tạo đơn).

---

## 3. Thanh toán

| Phương thức | Lúc đặt hàng | Sau đó |
|-------------|--------------|--------|
| **Chuyển khoản / VietQR** | `payment_status = unpaid` (chờ xác nhận) | Khách bấm «Tôi đã thanh toán» → `paid` |
| **COD (thu hộ)** | `payment_status = unpaid` | Admin xác nhận **Đã giao**, rồi **tự cập nhật** «Đã thanh toán» khi đã thu tiền mặt |

> **Quan trọng khi bảo vệ:** COD **không** tự chuyển paid khi bấm «Xác nhận đã giao». Admin phải lưu trạng thái thanh toán riêng — giống thực tế shop nhỏ thu tiền sau khi shipper giao xong.

VietQR trong đồ án là **demo**: hiện mã QR và cho khách tự xác nhận. Bản production cần webhook ngân hàng (Casso, SePay, VNPay…).

---

## 4. Vòng đời đơn hàng

```
                    ┌──────────────┐
                    │   shipped    │  ← Đơn mới tạo
                    │  (Đang giao) │
                    └──────┬───────┘
           Hủy (KH)        │         Admin: Xác nhận đã giao
              ┌────────────┼────────────┐
              ▼            ▼            │
        cancelled     delivered         │
        (Hoàn kho)   (Đã giao)          │
                           │            │
                    Yêu cầu hoàn       │
                           ▼            │
                    return_pending ─────┘
                           │
              Admin duyệt / từ chối
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
      return_accepted              delivered
      (Chờ khách gửi hàng)         (Từ chối hoàn)
              │
      Admin: Đã nhận hàng hoàn (+ cộng kho)
              ▼
      return_received
              │
      Admin: Hoàn tiền xong
              ▼
         returned + refunded
```

### Ai được làm gì?

| Hành động | Điều kiện |
|-----------|-----------|
| Khách **hủy** đơn | Chỉ khi `status = shipped` (chưa giao, chưa hủy, chưa hoàn) |
| Khách **yêu cầu hoàn** | Đơn `delivered`, trong **7 ngày** kể từ ngày giao |
| Admin **đổi trạng thái** | Không lùi trạng thái; đơn terminal (`cancelled`, `returned`) bị khóa |
| Admin **set returned** | Không làm trực tiếp — phải qua module **Hoàn hàng** 4 bước |

---

## 5. Hoàn hàng — 4 giai đoạn

| Bước | Trạng thái đơn | Tồn kho | Thanh toán / doanh thu |
|------|----------------|---------|-------------------------|
| 1. Khách gửi yêu cầu | `return_pending` | Chưa đổi | Chưa hoàn tiền |
| 2. Admin duyệt / từ chối | `return_accepted` hoặc về `delivered` | Chưa đổi | Chưa hoàn tiền |
| 3. Admin nhận hàng hoàn | `return_received` | **Cộng lại kho** | Chưa hoàn tiền |
| 4. Admin hoàn tiền | `returned` | — | `payment_status = refunded` |

Khách phải điền: lý do, STK ngân hàng, tên TK, ngân hàng, **ảnh minh chứng**.

**File liên quan:** `includes/repositories/return-repository.php`, `includes/admin/admin-returns.php`

---

## 6. Tồn kho

| Sự kiện | Hành vi |
|---------|---------|
| Đặt hàng thành công | Trừ số lượng (`inventoryDeductForOrder`) |
| Khách hủy đơn | Hoàn kho |
| Hoàn hàng — giai đoạn 3 | Hoàn kho |
| Sản phẩm hết hàng | `stock_status = out_of_stock`, cảnh báo trên admin |
| Admin sửa SP | Lưu SP + tồn kho trong cùng transaction |

**File liên quan:** `includes/repositories/inventory-repository.php`

---

## 7. Mã giảm giá (coupon)

1. Khách nhập mã trên giỏ hàng (ví dụ `WINSUMXINCHAO` — giảm 40.000đ)
2. Hệ thống kiểm tra: còn hiệu lực, còn lượt, đúng SĐT (nếu có ràng buộc)
3. Khi đặt hàng: ghi nhận lượt dùng (`coupon_redemptions`)
4. Khi hủy đơn hoặc hoàn xong (giai đoạn 4): **giải phóng lượt** — khách dùng lại được

**File liên quan:** `includes/repositories/coupon-repository.php`

---

## 8. Doanh thu & dashboard

### Công thức em dùng

| Chỉ số | Cách tính |
|--------|-----------|
| **Doanh thu thuần** | Tổng `grand_total` các đơn `delivered` + `payment_status = paid` |
| **Hoàn trả / giảm trừ** | Tổng đơn `returned` (đã hoàn tiền) |
| Biểu đồ | Đường **Doanh thu thuần** + **Hoàn trả** theo ngày/tháng/năm/khoảng thời gian |

**Lưu ý:**

- Đơn VietQR đã `paid` nhưng **chưa giao** → **chưa** tính vào doanh thu thuần (đúng vì chưa hoàn thành giao dịch)
- Đơn đang hoàn (`return_pending` … `return_received`) → doanh thu **chưa trừ** cho đến bước 4

**File liên quan:** `includes/admin/admin-stats.php`, `includes/admin/admin-dashboard.php`

---

## 9. Đánh giá sản phẩm

```
Khách login → Mua SP → Admin giao (delivered) → Tab Đánh giá
                                                      ↓
                              Gửi review (1 lần / SP / khách)
                                                      ↓
                              status = approved → hiển thị ngay
                                                      ↓
                              Cập nhật rating_average trên SP
```

- Khách **chưa mua** hoặc đơn **chưa giao** → không gửi được
- Admin có thể **trả lời** đánh giá (trên trang SP khi login admin, hoặc trang quản trị)
- Admin có thể **xóa** đánh giá không phù hợp

**File liên quan:** `includes/repositories/review-repository.php`

---

## 10. Blog & bình luận

- Khách / guest gửi bình luận → **hiển thị ngay** (không qua bước duyệt — em đơn giản hóa cho đồ án)
- Admin soạn bài: draft hoặc published
- Admin **trả lời** bình luận (nested thread trên trang bài viết và trang quản trị)

**File liên quan:** `includes/repositories/blog-comment-repository.php`, `includes/repositories/blog-repository.php`

---

## 11. Bảo mật & phân quyền (tóm tắt)

| Quy tắc | Mô tả |
|---------|--------|
| Admin route | Phải login admin |
| Khách xem đơn | Chỉ đơn của `customer_id` mình |
| Guest tra cứu | Cần đúng mã đơn + SĐT |
| Admin không mua hàng | Chặn thêm giỏ / checkout |
| CSRF | Mọi form POST có token |
| SQL | Prepared statements |

---

## 12. Hạn chế em ghi trong báo cáo

1. VietQR demo, chưa webhook ngân hàng
2. Đơn mới = «Đang giao» (không có pending/processing)
3. Hoàn hàng theo **cả đơn**, chưa từng dòng SP
4. Chưa gửi email/SMS
5. Chưa có kiểm thử tự động
6. Bình luận/đánh giá hiển thị ngay — chưa kiểm duyệt nội dung

---

## 13. Hướng phát triển (nếu hội đồng hỏi thêm)

- Tích hợp VNPay / MoMo / webhook VietQR
- Thêm trạng thái «Chờ xác nhận», «Đóng gói»
- Email thông báo từng bước hoàn hàng
- PHPUnit cho repository
- Hoàn hàng từng sản phẩm (partial return)

---

*Phụ lục luồng nghiệp vụ — đồ án Winsum Home.*
