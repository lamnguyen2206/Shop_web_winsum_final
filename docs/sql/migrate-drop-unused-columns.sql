-- Xóa các cột không dùng trong app Winsum Home (toàn NULL / không có trong PHP).
-- Import: phpMyAdmin → tab SQL → chọn file này, hoặc:
--   mysql -u root winsumwebfinal < docs/sql/migrate-drop-unused-columns.sql
--
-- Nên backup trước:
--   mysqldump -u root winsumwebfinal > backup-winsumwebfinal.sql
--
-- Sau khi import, dùng code PHP đã cập nhật (order/inventory/customer-admin repository).

USE `winsumwebfinal`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Helper: DROP FOREIGN KEY nếu tồn tại
DROP PROCEDURE IF EXISTS `_winsum_drop_fk_if_exists`;
DELIMITER //
CREATE PROCEDURE `_winsum_drop_fk_if_exists`(IN p_table VARCHAR(64), IN p_fk VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND CONSTRAINT_NAME = p_fk
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP FOREIGN KEY `', p_fk, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- Helper: DROP INDEX nếu tồn tại
DROP PROCEDURE IF EXISTS `_winsum_drop_index_if_exists`;
DELIMITER //
CREATE PROCEDURE `_winsum_drop_index_if_exists`(IN p_table VARCHAR(64), IN p_index VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND INDEX_NAME = p_index
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP INDEX `', p_index, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- Helper: DROP COLUMN nếu tồn tại
DROP PROCEDURE IF EXISTS `_winsum_drop_column_if_exists`;
DELIMITER //
CREATE PROCEDURE `_winsum_drop_column_if_exists`(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table
          AND COLUMN_NAME = p_column
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` DROP COLUMN `', p_column, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END//
DELIMITER ;

-- ========== blog_posts ==========
CALL `_winsum_drop_fk_if_exists`('blog_posts', 'fk_blog_posts_category');
CALL `_winsum_drop_index_if_exists`('blog_posts', 'fk_blog_posts_category');
CALL `_winsum_drop_column_if_exists`('blog_posts', 'category_id');
CALL `_winsum_drop_column_if_exists`('blog_posts', 'seo_title');
CALL `_winsum_drop_column_if_exists`('blog_posts', 'seo_description');

-- ========== categories ==========
CALL `_winsum_drop_fk_if_exists`('categories', 'fk_categories_parent');
CALL `_winsum_drop_column_if_exists`('categories', 'parent_id');
CALL `_winsum_drop_column_if_exists`('categories', 'image');

-- ========== customers ==========
CALL `_winsum_drop_column_if_exists`('customers', 'birthday');
CALL `_winsum_drop_column_if_exists`('customers', 'gender');
CALL `_winsum_drop_column_if_exists`('customers', 'last_login_at');

-- ========== orders / payments / shipments ==========
CALL `_winsum_drop_column_if_exists`('orders', 'currency_code');
CALL `_winsum_drop_column_if_exists`('order_payments', 'transaction_code');
CALL `_winsum_drop_column_if_exists`('order_payments', 'gateway_response');
CALL `_winsum_drop_column_if_exists`('order_shipments', 'tracking_number');
CALL `_winsum_drop_column_if_exists`('order_shipments', 'shipping_provider');

-- ========== order_items ==========
CALL `_winsum_drop_index_if_exists`('order_items', 'fk_order_items_variant');
CALL `_winsum_drop_column_if_exists`('order_items', 'variant_id');

-- ========== product_images ==========
CALL `_winsum_drop_index_if_exists`('product_images', 'fk_product_images_variant');
CALL `_winsum_drop_column_if_exists`('product_images', 'variant_id');

-- ========== inventory_items ==========
CALL `_winsum_drop_index_if_exists`('inventory_items', 'uq_inventory_item');
CALL `_winsum_drop_index_if_exists`('inventory_items', 'fk_inventory_variant');
CALL `_winsum_drop_column_if_exists`('inventory_items', 'variant_id');
CALL `_winsum_drop_column_if_exists`('inventory_items', 'quantity_reserved');
CALL `_winsum_drop_column_if_exists`('inventory_items', 'reorder_level');

-- Unique tạm: product + warehouse (bỏ hẳn kho bằng migrate-drop-warehouses.sql)
SET @has_uq := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_items'
      AND INDEX_NAME = 'uq_inventory_item'
);
SET @has_wh := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_items'
      AND COLUMN_NAME = 'warehouse_id'
);
SET @sql_uq = IF(
    @has_uq = 0 AND @has_wh > 0,
    'ALTER TABLE `inventory_items` ADD UNIQUE KEY `uq_inventory_item` (`product_id`, `warehouse_id`)',
    'SELECT 1'
);
PREPARE stmt_uq FROM @sql_uq;
EXECUTE stmt_uq;
DEALLOCATE PREPARE stmt_uq;

-- ========== products ==========
CALL `_winsum_drop_column_if_exists`('products', 'cost_price');
CALL `_winsum_drop_column_if_exists`('products', 'product_type');
CALL `_winsum_drop_column_if_exists`('products', 'weight_gram');
CALL `_winsum_drop_column_if_exists`('products', 'length_cm');
CALL `_winsum_drop_column_if_exists`('products', 'width_cm');
CALL `_winsum_drop_column_if_exists`('products', 'height_cm');

-- ========== payment_methods ==========
CALL `_winsum_drop_column_if_exists`('payment_methods', 'description');

-- Dọn helper procedures
DROP PROCEDURE IF EXISTS `_winsum_drop_fk_if_exists`;
DROP PROCEDURE IF EXISTS `_winsum_drop_index_if_exists`;
DROP PROCEDURE IF EXISTS `_winsum_drop_column_if_exists`;

SET FOREIGN_KEY_CHECKS = 1;

-- Xong. Giữ lại: categories.description (form admin / catalog vẫn dùng).
