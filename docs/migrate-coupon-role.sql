-- Thêm cột coupon_role để phân loại tab: discount | shipping | vip
-- Chạy: mysql -u root winsumwebfinal < docs/migrate-coupon-role.sql

USE `winsumwebfinal`;

SET NAMES utf8mb4;

ALTER TABLE `coupons`
    ADD COLUMN `coupon_role` enum('discount','shipping','vip') NOT NULL DEFAULT 'discount'
        COMMENT 'Nhóm hiển thị: mã giảm giá / freeship / VIP'
        AFTER `discount_type`;

-- Gán role theo mã (ưu tiên rõ ràng, không đoán từ min_order)
UPDATE `coupons` SET `coupon_role` = 'shipping' WHERE `code` = 'FREESHIP';
UPDATE `coupons` SET `coupon_role` = 'vip' WHERE `code` = 'VIP15';
UPDATE `coupons` SET `coupon_role` = 'discount' WHERE `code` IN (
    'WINSUMXINCHAO', 'WINSUM10', 'WINSUM100K', 'HELLO2026', 'TESTHETHAN', 'NGUNGHDB'
);

-- Mã shipping còn sót (theo discount_type)
UPDATE `coupons` SET `coupon_role` = 'shipping' WHERE `discount_type` = 'shipping';
