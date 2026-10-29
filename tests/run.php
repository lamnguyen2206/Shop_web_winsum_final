<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * php tests/run.php
 * Uses database winsumwebfinal_test. Creates and imports it when missing.
 */

$root = dirname(__DIR__);
$testDb = 'winsumwebfinal_test';
putenv('WINSUM_DB_NAME=' . $testDb);

$config = require $root . '/config/local.example.php';
if (is_file($root . '/config/local.php')) {
    $config = require $root . '/config/local.php';
}
$db = $config['db'];
$server = new mysqli((string) $db['host'], (string) $db['user'], (string) $db['pass']);
if ($server->connect_error) {
    fwrite(STDERR, $server->connect_error . "\n");
    exit(1);
}
$server->set_charset('utf8mb4');
$server->query('CREATE DATABASE IF NOT EXISTS `' . $testDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$check = new mysqli((string) $db['host'], (string) $db['user'], (string) $db['pass'], $testDb);
$exists = $check->query("SHOW TABLES LIKE 'products'");
if (!$exists || $exists->num_rows === 0) {
    $sql = file_get_contents($root . '/winsumwebfinal.sql');
    if ($sql === false || !$check->multi_query($sql)) {
        fwrite(STDERR, 'Import failed: ' . $check->error . "\n");
        exit(1);
    }
    while ($check->more_results() && $check->next_result()) {
    }
}
$check->close();

passthru('WINSUM_DB_NAME=' . escapeshellarg($testDb) . ' php ' . escapeshellarg($root . '/scripts/migrate.php'), $migrateCode);
if ($migrateCode !== 0) {
    exit($migrateCode);
}

require_once $root . '/config/database.php';
require_once $root . '/includes/helpers.php';
require_once $root . '/includes/order-repository.php';
require_once $root . '/includes/coupon-repository.php';
require_once $root . '/includes/customer-auth.php';
require_once $root . '/includes/admin-auth.php';
require_once $root . '/includes/cart-store.php';

$failures = 0;

function check(bool $ok, string $message): void
{
    global $failures;
    if ($ok) {
        echo "PASS {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL {$message}\n";
}

function testOrderFlow(mysqli $conn): void
{
    $product = $conn->query('SELECT p.id, p.base_price, ii.quantity_on_hand
                             FROM products p
                             INNER JOIN inventory_items ii ON ii.product_id = p.id
                             WHERE p.is_active = 1 AND p.stock_status = \'in_stock\' AND ii.quantity_on_hand > 0
                             ORDER BY p.id ASC LIMIT 1')->fetch_assoc();
    $shipping = $conn->query('SELECT id, fee FROM shipping_methods WHERE is_active = 1 ORDER BY id ASC LIMIT 1')->fetch_assoc();
    $payment = $conn->query('SELECT id FROM payment_methods WHERE is_active = 1 ORDER BY id ASC LIMIT 1')->fetch_assoc();
    if (!$product || !$shipping || !$payment) {
        check(false, 'order flow fixtures');
        return;
    }
    $before = (int) $product['quantity_on_hand'];
    $productId = (int) $product['id'];
    $price = (int) round((float) $product['base_price']);
    $code = orderCreateFromCheckout(
        $conn,
        [
            'name' => 'Test Buyer',
            'phone' => '0900000001',
            'email' => 'buyer@example.com',
            'address' => 'Ha Noi',
            'note' => '',
        ],
        [[
            'product_id' => $productId,
            'sku' => 'TEST',
            'name' => 'Test lamp',
            'image' => 'assets/images/blog_1.png',
            'price' => $price,
            'qty' => 1,
        ]],
        ['subtotal' => $price, 'shipping' => (int) $shipping['fee'], 'discount' => 0, 'total' => $price + (int) $shipping['fee']],
        null,
        null,
        (int) $shipping['id'],
        (int) $payment['id'],
        null
    );
    $order = $conn->query("SELECT id, status, fulfillment_status FROM orders WHERE order_code = '" . $conn->real_escape_string($code) . "'")->fetch_assoc();
    $afterCreate = (int) $conn->query('SELECT quantity_on_hand FROM inventory_items WHERE product_id = ' . $productId)->fetch_assoc()['quantity_on_hand'];
    check($order && $order['status'] === 'pending' && $order['fulfillment_status'] === 'pending', 'new order is pending');
    check($afterCreate === $before, 'inventory unchanged until shipped');

    $orderId = (int) $order['id'];
    check(orderUpdateFulfillmentStatus($conn, $orderId, 'processing'), 'admin confirms order');
    check(orderUpdateFulfillmentStatus($conn, $orderId, 'shipped'), 'admin ships order');
    $afterShip = (int) $conn->query('SELECT quantity_on_hand FROM inventory_items WHERE product_id = ' . $productId)->fetch_assoc()['quantity_on_hand'];
    $shipped = $conn->query('SELECT status FROM orders WHERE id = ' . $orderId)->fetch_assoc();
    check($shipped && $shipped['status'] === 'shipped' && $afterShip === $before - 1, 'shipping deducts one unit');
}

function testCouponExhausted(mysqli $conn): void
{
    $coupon = $conn->query('SELECT id, code FROM coupons WHERE is_active = 1 AND code = \'FREESHIP\' LIMIT 1')->fetch_assoc();
    if (!$coupon) {
        check(false, 'coupon fixture');
        return;
    }
    $couponId = (int) $coupon['id'];
    $conn->begin_transaction();
    $conn->query('UPDATE coupons SET total_usage_limit = 0 WHERE id = ' . $couponId);
    $result = couponValidate($conn, (string) $coupon['code'], 10000000, null, '0900000099');
    $conn->rollback();
    check($result['ok'] === false, 'exhausted coupon is rejected');
}

function testOrderOwnership(mysqli $conn): void
{
    $order = $conn->query('SELECT order_code, customer_id FROM orders WHERE customer_id IS NOT NULL ORDER BY id ASC LIMIT 1')->fetch_assoc();
    $other = $conn->query('SELECT id FROM customers WHERE id <> ' . (int) ($order['customer_id'] ?? 0) . ' ORDER BY id ASC LIMIT 1')->fetch_assoc();
    if (!$order || !$other) {
        check(false, 'ownership fixtures');
        return;
    }
    $found = orderGetCustomerOrderDetailByCode($conn, (int) $other['id'], (string) $order['order_code']);
    check($found === null, 'customer cannot read another customer order');
}

function testAdminCannotAddToCart(): void
{
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['customer_role'] = 'admin';
    $blocked = !storefrontMayAddToCart(['id' => 1, 'role' => 'admin']);
    unset($_SESSION['admin_logged_in'], $_SESSION['customer_role']);
    check($blocked, 'admin cannot add to cart');
}

testOrderFlow($conn);
testCouponExhausted($conn);
testOrderOwnership($conn);
testAdminCannotAddToCart();

if ($failures > 0) {
    fwrite(STDERR, "{$failures} test(s) failed\n");
    exit(1);
}

echo "All tests passed\n";
