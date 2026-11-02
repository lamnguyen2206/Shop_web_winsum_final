CREATE TABLE IF NOT EXISTS payment_webhook_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(32) NOT NULL,
    provider_event_id VARCHAR(80) NOT NULL,
    order_id INT NULL,
    status VARCHAR(24) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_webhook_event (provider, provider_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
