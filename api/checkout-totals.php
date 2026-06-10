<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/cart-store.php';
require_once __DIR__ . '/../includes/order-repository.php';
require_once __DIR__ . '/../includes/customer-auth.php';

$shippingMethods = orderGetShippingMethods($conn);
$shippingMethodId = (int) ($_GET['shipping_method_id'] ?? 0);

if ($shippingMethodId > 0) {
    if (orderApplyShippingToSession($shippingMethods, $shippingMethodId) === null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Phương thức vận chuyển không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$customer = customerCurrent($conn);
$customerId = $customer ? (int) $customer['id'] : null;

cartSyncPricesFromDb($conn);
$items = cartGetItems();
$totals = cartCalculateTotals($items, $conn, $customerId);

echo json_encode([
    'ok' => true,
    'subtotal' => (int) $totals['subtotal'],
    'shipping' => (int) $totals['shipping'],
    'discount' => (int) $totals['discount'],
    'total' => (int) $totals['total'],
    'shipping_method_id' => (int) ($_SESSION['checkout_shipping_method_id'] ?? 0),
], JSON_UNESCAPED_UNICODE);
