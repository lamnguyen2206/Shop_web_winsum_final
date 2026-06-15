-- Bỏ bảng brands và cột brand_id — shop chỉ có thương hiệu Winsum Home (hard-code trong app).
--
--   mysql -u root winsumwebfinal < docs/sql/migrate-drop-brands.sql

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

-- products: bỏ liên kết brand
CALL `_winsum_drop_fk_if_exists`('products', 'fk_products_brand');
CALL `_winsum_drop_index_if_exists`('products', 'idx_products_brand');
CALL `_winsum_drop_column_if_exists`('products', 'brand_id');

DROP TABLE IF EXISTS `brands`;

DROP PROCEDURE IF EXISTS `_winsum_drop_fk_if_exists`;
DROP PROCEDURE IF EXISTS `_winsum_drop_index_if_exists`;
DROP PROCEDURE IF EXISTS `_winsum_drop_column_if_exists`;

SET FOREIGN_KEY_CHECKS = 1;
