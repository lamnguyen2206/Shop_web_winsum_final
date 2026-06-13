<?php
require_once __DIR__ . '/../config/database.php';

function adminGetDashboardStats(mysqli $conn): array
{
    $stats = [
        'orders_total' => 0,
        'orders_pending' => 0,
        'products_total' => 0,
        'products_active' => 0,
        'customers_total' => 0,
        'reviews_pending' => 0,
        'reviews_total' => 0,
        'revenue_total' => 0.0,
        'revenue_paid' => 0.0,
        'revenue_net' => 0.0,
        'revenue_refunded' => 0.0,
        'returns_pending' => 0,
        'inventory_alerts_unread' => 0,
    ];

    $queries = [
        'orders_total' => "SELECT COUNT(*) AS c FROM orders",
        'orders_pending' => "SELECT COUNT(*) AS c FROM orders WHERE status = 'shipped'",
        'products_total' => "SELECT COUNT(*) AS c FROM products",
        'products_active' => "SELECT COUNT(*) AS c FROM products WHERE is_active = 1",
        'customers_total' => "SELECT COUNT(*) AS c FROM customers",
        'reviews_pending' => "SELECT COUNT(*) AS c FROM product_reviews WHERE status = 'pending'",
        'reviews_total' => "SELECT COUNT(*) AS c FROM product_reviews",
        'revenue_total' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status NOT IN ('cancelled', 'returned')",
        'revenue_paid' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status NOT IN ('cancelled', 'returned') AND payment_status = 'paid'",
        'revenue_net' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status = 'delivered' AND payment_status = 'paid'",
        'revenue_refunded' => "SELECT COALESCE(SUM(grand_total), 0) AS c FROM orders WHERE status = 'returned'",
    ];

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats[$key] = str_starts_with($key, 'revenue_') ? (float) ($row['c'] ?? 0) : (int) ($row['c'] ?? 0);
        }
    }

    require_once __DIR__ . '/inventory-repository.php';
    $stats['inventory_alerts_unread'] = inventoryCountUnreadAlerts($conn);

    require_once __DIR__ . '/return-repository.php';
    $stats['returns_pending'] = returnCountPending($conn);

    return $stats;
}

function adminGetRecentOrders(mysqli $conn, int $limit = 5): array
{
    $stmt = $conn->prepare("SELECT order_code, customer_name, grand_total, status, ordered_at
                            FROM orders ORDER BY id DESC LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}
