# Winsum Home

E-commerce bán đèn trang trí & nội thất. PHP thuần + MySQL, chạy XAMPP.

## Tech Stack

- PHP 8+
- MySQL/MariaDB
- Apache (XAMPP)
- Vanilla JS/CSS

## Setup

```bash
# 1. Clone vào htdocs
git clone <repo> C:\xampp\htdocs\webwinsum

# 2. Tạo DB và import data
mysql -u root -e "CREATE DATABASE winsumwebfinal"
mysql -u root winsumwebfinal < "winsumwebfinal (9).sql"

# 3. Config DB (nếu cần)
# Sửa config/database.php
```

Mở http://localhost/webwinsum

## Demo Accounts

| Role | Login |
|------|-------|
| Admin | `admin` / `admin123` |
| Coupon | `WINSUMXINCHAO` (-40k) |

Khách hàng tự đăng ký qua header.

## Features

**Storefront:**
- Catalog: filter danh mục/màu/giá, search, sort, pagination
- Product detail: gallery, specs, reviews
- Cart + Checkout (COD / VietQR demo)
- Order tracking, cancel, return request
- Blog + comments

**Admin:**
- Dashboard: revenue, orders, customers stats
- CRUD products + inventory management
- Order management (status, payment, shipping)
- Return/refund approval
- Blog + comment moderation
- Review management

## Project Structure

```
index.php                   # Front controller
bootstrap/app.php           # App init, auth, POST handling
config/database.php         # DB config

includes/
├── routes.php              # View routing
├── helpers.php             # Utility functions
├── csrf.php / flash.php    # Security & flash messages
│
├── *-repository.php        # Data access layer
├── *-auth.php              # Authentication
├── cart-store.php          # Session cart
│
├── *.php                   # View templates
└── layout/                 # Header, footer, sidebar

assets/
├── css/
├── js/
└── images/

docs/                       # Documentation & test reports
```

## Routes

| Page | URL |
|------|-----|
| Home | `?view=home` |
| Catalog | `?view=catalog` |
| Product | `?view=product&slug=xxx` |
| Cart | `?view=cart` |
| Checkout | `?view=checkout` |
| My Orders | `?view=orders` |
| Guest Lookup | `?view=order-lookup` |
| Blog | `?view=blog` |
| Admin | `?view=admin-dashboard` |

## Security

- CSRF tokens on all forms
- Prepared statements (no SQL injection)
- Password hashing (`password_hash`)
- Admin-only routes protection
- Rate limiting (5 failed logins = 15min block)
- Session regeneration on login

## Business Logic

- Inventory auto-deduct on order, restore on cancel/return
- COD auto-marks paid when delivered
- Reviews only for delivered orders, 1 per product
- Coupons validate at checkout, restore on cancel
- Order status can't go backwards (shipped → pending)

## Troubleshooting

**DB connection failed?**
1. Check XAMPP Apache + MySQL running
2. Verify DB name in `config/database.php`
3. Make sure SQL file imported correctly

---

Built for academic project.
