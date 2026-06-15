-- ============================================================
-- Winsum Home: Dữ liệu mẫu bảng coupons & coupon_redemptions
-- Database: winsumwebfinal
-- Chạy migrate trước (nếu chưa có cột coupon_role):
--   mysql -u root winsumwebfinal < docs/migrate-coupon-role.sql
-- Sau đó: mysql -u root winsumwebfinal < docs/seed-coupons.sql
-- ============================================================

USE `winsumwebfinal`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Xóa lịch sử dùng mã cũ (nếu muốn làm sạch trước khi seed lại)
-- DELETE FROM `coupon_redemptions`;

-- ------------------------------------------------------------
-- Bảng coupons: mã giảm giá demo
-- discount_type: fixed | percent | shipping
-- ------------------------------------------------------------
INSERT INTO `coupons` (
    `id`, `code`, `name`, `description`,
    `discount_type`, `coupon_role`, `discount_value`,
    `min_order_amount`, `max_discount_amount`,
    `total_usage_limit`, `per_customer_limit`,
    `starts_at`, `ends_at`, `is_active`,
    `created_at`, `updated_at`
) VALUES
(1, 'WINSUMXINCHAO', 'Xin chào Winsum',
 'Giảm 40.000đ cho đơn hàng — dành cho khách mới',
 'fixed', 'discount', 40000.00,
 0.00, NULL,
 NULL, 1,
 NULL, NULL, 1,
 NOW(), NOW()),

(2, 'WINSUM10', 'Giảm 10% đơn từ 500k',
 'Giảm 10% tổng tiền hàng, tối đa 200.000đ, đơn tối thiểu 500.000đ',
 'percent', 'discount', 10.00,
 500000.00, 200000.00,
 NULL, 3,
 NULL, NULL, 1,
 NOW(), NOW()),

(3, 'FREESHIP', 'Miễn phí vận chuyển',
 'Giảm toàn bộ phí ship (tối đa 30.000đ), đơn tối thiểu 300.000đ',
 'shipping', 'shipping', 30000.00,
 300000.00, NULL,
 200, NULL,
 NULL, NULL, 1,
 NOW(), NOW()),

(4, 'WINSUM100K', 'Giảm 100.000đ đơn lớn',
 'Giảm 100.000đ cho đơn từ 2.000.000đ',
 'fixed', 'discount', 100000.00,
 2000000.00, NULL,
 50, 1,
 NULL, NULL, 1,
 NOW(), NOW()),

(5, 'HELLO2026', 'Chào hè 2026',
 'Giảm 50.000đ — giới hạn 100 lượt toàn hệ thống',
 'fixed', 'discount', 50000.00,
 0.00, NULL,
 100, 2,
 '2026-01-01 00:00:00', '2026-12-31 23:59:59', 1,
 NOW(), NOW()),

(6, 'VIP15', 'Khách VIP giảm 15%',
 'Giảm 15% tối đa 500.000đ, mỗi khách dùng tối đa 2 lần',
 'percent', 'vip', 15.00,
 1000000.00, 500000.00,
 NULL, 2,
 NULL, NULL, 1,
 NOW(), NOW()),

(7, 'TESTHETHAN', 'Mã test hết hạn',
 'Mã không còn hiệu lực — dùng để demo thông báo lỗi',
 'fixed', 'discount', 10000.00,
 0.00, NULL,
 NULL, NULL,
 '2025-01-01 00:00:00', '2025-12-31 23:59:59', 1,
 NOW(), NOW()),

(8, 'NGUNGHDB', 'Mã đã tắt',
 'Mã is_active = 0 — demo mã không còn hiệu lực',
 'fixed', 'discount', 20000.00,
 0.00, NULL,
 NULL, NULL,
 NULL, NULL, 0,
 NOW(), NOW())

ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`),
    `discount_type` = VALUES(`discount_type`),
    `coupon_role` = VALUES(`coupon_role`),
    `discount_value` = VALUES(`discount_value`),
    `min_order_amount` = VALUES(`min_order_amount`),
    `max_discount_amount` = VALUES(`max_discount_amount`),
    `total_usage_limit` = VALUES(`total_usage_limit`),
    `per_customer_limit` = VALUES(`per_customer_limit`),
    `starts_at` = VALUES(`starts_at`),
    `ends_at` = VALUES(`ends_at`),
    `is_active` = VALUES(`is_active`),
    `updated_at` = NOW();

-- ------------------------------------------------------------
-- Bảng coupon_redemptions: lịch sử đã dùng mã
-- (Chỉ insert nếu order_id / customer_id tồn tại)
-- ------------------------------------------------------------
INSERT INTO `coupon_redemptions` (`id`, `coupon_id`, `customer_id`, `order_id`, `redeemed_at`)
SELECT 1, 1, 4, 3, '2026-05-19 19:26:10'
FROM DUAL
WHERE EXISTS (SELECT 1 FROM `orders` WHERE `id` = 3)
  AND EXISTS (SELECT 1 FROM `customers` WHERE `id` = 4)
  AND NOT EXISTS (SELECT 1 FROM `coupon_redemptions` WHERE `id` = 1);

-- Đồng bộ thông tin mã trên đơn hàng mẫu (bảng orders — liên quan coupon)
UPDATE `orders`
SET `coupon_id` = 1,
    `coupon_code` = 'WINSUMXINCHAO',
    `discount_amount` = 40000.00,
    `grand_total` = GREATEST(0, `subtotal` + `shipping_fee` - 40000.00)
WHERE `id` = 3
  AND EXISTS (SELECT 1 FROM `coupons` WHERE `id` = 1);

SET FOREIGN_KEY_CHECKS = 1;

-- Kiểm tra sau khi chạy:
-- SELECT id, code, name, discount_type, discount_value, is_active FROM coupons;
-- SELECT * FROM coupon_redemptions;
