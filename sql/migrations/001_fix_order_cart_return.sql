-- Additive schema for Winsum Home. Safe to re-run: duplicate column/key errors are ignored by scripts/migrate.php.

CREATE TABLE IF NOT EXISTS schema_migrations (
    id VARCHAR(64) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customers
    ADD COLUMN role ENUM('customer', 'admin') NOT NULL DEFAULT 'customer' AFTER status;

ALTER TABLE orders
    ADD COLUMN inventory_deducted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE orders
    ADD COLUMN inventory_restocked TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE order_items
    ADD COLUMN stock_deducted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE product_reviews
    ADD COLUMN parent_id BIGINT DEFAULT NULL;

ALTER TABLE product_reviews
    ADD COLUMN is_admin_reply TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE product_reviews
    ADD INDEX idx_product_reviews_parent (parent_id);

ALTER TABLE blog_comments
    ADD COLUMN parent_id BIGINT DEFAULT NULL;

ALTER TABLE blog_comments
    ADD COLUMN is_admin_reply TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE blog_comments
    ADD INDEX idx_blog_comments_parent (parent_id);

ALTER TABLE order_return_requests
    ADD COLUMN bank_account_number VARCHAR(40) DEFAULT NULL;

ALTER TABLE order_return_requests
    ADD COLUMN bank_account_name VARCHAR(120) DEFAULT NULL;

ALTER TABLE order_return_requests
    ADD COLUMN bank_name VARCHAR(120) DEFAULT NULL;

ALTER TABLE order_return_requests
    ADD COLUMN return_shipping_cost DECIMAL(12,2) DEFAULT NULL;

ALTER TABLE order_return_requests
    ADD COLUMN goods_received_at TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE order_return_requests
    ADD COLUMN refunded_at TIMESTAMP NULL DEFAULT NULL;

ALTER TABLE order_return_requests
    ADD COLUMN customer_refund_confirmed_at TIMESTAMP NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS inventory_alerts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT NOT NULL,
    order_id BIGINT DEFAULT NULL,
    message TEXT NOT NULL,
    alert_type VARCHAR(30) NOT NULL DEFAULT 'stock_depleted',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_alerts_unread (is_read, created_at),
    CONSTRAINT fk_inventory_alerts_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_alerts_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT DEFAULT NULL,
    session_key VARCHAR(64) DEFAULT NULL,
    coupon_code VARCHAR(50) NOT NULL DEFAULT '',
    coupon_id BIGINT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_carts_customer (customer_id),
    UNIQUE KEY uq_carts_session (session_key),
    CONSTRAINT fk_carts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    qty INT NOT NULL,
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(190) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    UNIQUE KEY uq_login_attempts_identifier (identifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_return_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    return_request_id BIGINT NOT NULL,
    order_item_id BIGINT NOT NULL,
    quantity INT NOT NULL,
    UNIQUE KEY uq_return_line (return_request_id, order_item_id),
    CONSTRAINT fk_return_items_request FOREIGN KEY (return_request_id) REFERENCES order_return_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
