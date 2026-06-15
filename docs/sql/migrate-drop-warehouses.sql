-- Bỏ bảng warehouses và cột warehouse_id — tồn kho theo product_id (1 SP = 1 dòng).
-- Import sau migrate-drop-unused-columns.sql (hoặc trên DB đã gọn cột thừa).
--
--   mysql -u root winsumwebfinal < docs/sql/migrate-drop-warehouses.sql

USE `winsumwebfinal`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS `_winsum_drop_fk_if_exists`;
DELIMITER //
CREATE PROCEDURE `_winsum_drop_fk_if_exists`(IN p_table VARCHAR(64), IN p_fk VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
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

DROP PROCEDURE IF EXISTS `_winsum_drop_index_if_exists`;
DELIMITER //
CREATE PROCEDURE `_winsum_drop_index_if_exists`(IN p_table VARCHAR(64), IN p_index VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
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

DROP PROCEDURE IF EXISTS `_winsum_drop_column_if_exists`;
DELIMITER //
CREATE PROCEDURE `_winsum_drop_column_if_exists`(IN p_table VARCHAR(64), IN p_column VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
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

-- Gộp tồn nếu còn cột warehouse_id (nhiều dòng / SP)
SET @has_wh_col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_items'
      AND COLUMN_NAME = 'warehouse_id'
);
SET @sql_merge = IF(
    @has_wh_col > 0,
    'CREATE TEMPORARY TABLE `_winsum_inv_merge` AS
     SELECT product_id, SUM(quantity_on_hand) AS total_qty, MAX(updated_at) AS last_updated
     FROM inventory_items GROUP BY product_id',
    'SELECT 1'
);
PREPARE stmt_merge FROM @sql_merge;
EXECUTE stmt_merge;
DEALLOCATE PREPARE stmt_merge;

SET @sql_wipe = IF(
    @has_wh_col > 0,
    'DELETE FROM inventory_items',
    'SELECT 1'
);
PREPARE stmt_wipe FROM @sql_wipe;
EXECUTE stmt_wipe;
DEALLOCATE PREPARE stmt_wipe;

SET @sql_reinsert = IF(
    @has_wh_col > 0,
    'INSERT INTO inventory_items (product_id, quantity_on_hand, updated_at)
     SELECT product_id, total_qty, last_updated FROM `_winsum_inv_merge`',
    'SELECT 1'
);
PREPARE stmt_reinsert FROM @sql_reinsert;
EXECUTE stmt_reinsert;
DEALLOCATE PREPARE stmt_reinsert;

SET @sql_drop_tmp = IF(
    @has_wh_col > 0,
    'DROP TEMPORARY TABLE IF EXISTS `_winsum_inv_merge`',
    'SELECT 1'
);
PREPARE stmt_drop_tmp FROM @sql_drop_tmp;
EXECUTE stmt_drop_tmp;
DEALLOCATE PREPARE stmt_drop_tmp;

-- inventory_items: bỏ liên kết kho
CALL `_winsum_drop_fk_if_exists`('inventory_items', 'fk_inventory_warehouse');
CALL `_winsum_drop_index_if_exists`('inventory_items', 'uq_inventory_item');
CALL `_winsum_drop_index_if_exists`('inventory_items', 'fk_inventory_warehouse');
CALL `_winsum_drop_column_if_exists`('inventory_items', 'warehouse_id');

SET @has_uq_product := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'inventory_items'
      AND INDEX_NAME = 'uq_inventory_product'
);
SET @sql_uq = IF(
    @has_uq_product = 0,
    'ALTER TABLE `inventory_items` ADD UNIQUE KEY `uq_inventory_product` (`product_id`)',
    'SELECT 1'
);
PREPARE stmt_uq FROM @sql_uq;
EXECUTE stmt_uq;
DEALLOCATE PREPARE stmt_uq;

-- Xóa bảng warehouses
DROP TABLE IF EXISTS `warehouses`;

DROP PROCEDURE IF EXISTS `_winsum_drop_fk_if_exists`;
DROP PROCEDURE IF EXISTS `_winsum_drop_index_if_exists`;
DROP PROCEDURE IF EXISTS `_winsum_drop_column_if_exists`;

SET FOREIGN_KEY_CHECKS = 1;
