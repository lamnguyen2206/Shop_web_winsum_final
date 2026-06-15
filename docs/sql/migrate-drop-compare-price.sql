-- Bỏ cột compare_at_price (giá so sánh / giá gạch) trên products.
--
--   mysql -u root winsumwebfinal < docs/sql/migrate-drop-compare-price.sql

USE `winsumwebfinal`;

SET NAMES utf8mb4;

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

CALL `_winsum_drop_column_if_exists`('products', 'compare_at_price');

DROP PROCEDURE IF EXISTS `_winsum_drop_column_if_exists`;
