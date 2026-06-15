# Tài liệu đồ án Winsum Home

Thư mục này chứa phần tài liệu em nộp kèm đồ án: hướng dẫn cài đặt, mô tả nghiệp vụ, thiết kế CSDL và báo cáo kiểm thử.

---

## Mục lục

| Tài liệu | Mô tả |
|----------|--------|
| [huong-dan-cai-dat.md](huong-dan-cai-dat.md) | Cài XAMPP, import DB, chạy thử, xử lý lỗi |
| [luong-nghiep-vu.md](luong-nghiep-vu.md) | Luồng đặt hàng, thanh toán, hoàn trả, doanh thu |
| [ru-ro-trien-khai.md](ru-ro-trien-khai.md) | Rủi ro triển khai (Markdown) |
| [Ru-ro-trien-khai-Winsum-Home.docx](Ru-ro-trien-khai-Winsum-Home.docx) | Rủi ro triển khai (Word) |
| [testing/TEST-CASES.md](testing/TEST-CASES.md) | Danh sách test case (161 TC) |
| [testing/DEMO-SCRIPT.md](testing/DEMO-SCRIPT.md) | Kịch bản demo 5–7 phút khi bảo vệ |
| [database/database-design.html](database/database-design.html) | Mô tả các bảng trong CSDL |
| [database/er-diagrams.html](database/er-diagrams.html) | Sơ đồ ERD |
| [database/Mo-ta-CSDL-Winsum-Home.docx](database/Mo-ta-CSDL-Winsum-Home.docx) | File Word mô tả CSDL (nếu có) |

---

## Thư mục con

```
docs/
├── huong-dan-cai-dat.md      # Cài đặt
├── luong-nghiep-vu.md        # Nghiệp vụ
├── sql/                      # Script SQL bổ sung
│   ├── migrate-coupon-role.sql
│   └── seed-coupons.sql
├── database/                 # Thiết kế CSDL
└── testing/                  # Kiểm thử
    ├── TEST-CASES.md
    ├── DEMO-SCRIPT.md
    ├── test-report-data.php  # Nguồn dữ liệu sinh báo cáo
    ├── generate-test-report.php
    └── export-test-report.bat
```

---

## Script SQL bổ sung

Sau khi import file dump chính `winsumwebfinal (9).sql`, có thể chạy thêm:

```bash
mysql -u root winsumwebfinal < docs/sql/migrate-coupon-role.sql
mysql -u root winsumwebfinal < docs/sql/seed-coupons.sql
```

Script này đảm bảo bảng coupon và mã `WINSUMXINCHAO` hoạt động đúng khi demo.

---

## Xuất toàn bộ tài liệu (một lệnh)

Double-click `docs/export-docs.bat` hoặc:

```bash
docs\export-docs.bat
```

**File sinh ra:**

- `docs/testing/bao-cao-kiem-thu.html`
- `docs/testing/bao-cao-kiem-thu.xlsx`
- `docs/testing/bao-cao-kiem-thu.pdf` (nếu có Chrome/Edge)
- `docs/testing/TEST-CASES.md`
- `docs/database/Mo-ta-CSDL-Winsum-Home.docx`
- `docs/Ru-ro-trien-khai-Winsum-Home.docx`
- `docs/ru-ro-trien-khai.md`
- `docs/Winsum-Test-Case-Template-Filled.xlsx`
- `Winsum-Test-Case-Template-Filled (1).xlsx` (thư mục gốc project)

---

## Sinh báo cáo kiểm thử (riêng)

Em viết script PHP để xuất báo cáo từ cùng một nguồn dữ liệu (`test-report-data.php`):

```bash
C:\xampp\php\php.exe docs/testing/generate-test-report.php
```

Hoặc double-click `docs/testing/export-test-report.bat`.

**Kết quả sinh ra:**

- `docs/testing/bao-cao-kiem-thu.html`
- `docs/testing/bao-cao-kiem-thu.xlsx`
- `docs/testing/bao-cao-kiem-thu.pdf` (nếu máy có công cụ hỗ trợ)
- `docs/testing/TEST-CASES.md` (đồng bộ từ data)

---

## Sinh tài liệu Word mô tả CSDL

```bash
python docs/database/generate-database-doc-word.py
```

(Cần Python 3 và thư viện `python-docx` nếu script yêu cầu.)

---

## Sinh tài liệu Word rủi ro triển khai

```bash
python docs/generate-ru-ro-doc-word.py
```

Kết quả: `docs/Ru-ro-trien-khai-Winsum-Home.docx`

---

## Ghi chú cho giảng viên / hội đồng

- URL mặc định trong tài liệu: `http://localhost/webwinsum`
- Tài khoản admin demo: `admin` / `admin123`
- Database: `winsumwebfinal`
- Phần VietQR là **demo** — chưa tích hợp cổng thanh toán thật
