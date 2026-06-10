<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function cartGetItems(): array
{
    if (!isset($_SESSION['cart_items']) || !is_array($_SESSION['cart_items'])) {
        $_SESSION['cart_items'] = [];
    }
    return $_SESSION['cart_items'];
}

function cartSetItems(array $items): void
{
    $_SESSION['cart_items'] = array_values($items);
    unset($_SESSION['cart_coupon_auto_skip']);
}

function cartAddItem(array $item, int $qty = 1): void
{
    $items = cartGetItems();
    $itemId = (string) $item['id'];
    $qty = max(1, $qty);

    foreach ($items as &$existing) {
        if ((string) $existing['id'] === $itemId) {
            $existing['qty'] = (int) $existing['qty'] + $qty;
            cartSetItems($items);
            return;
        }
    }
    unset($existing);

    $items[] = [
        'id' => $itemId,
        'product_id' => (int) ($item['product_id'] ?? 0),
        'name' => (string) $item['name'],
        'slug' => (string) ($item['slug'] ?? ''),
        'sku' => (string) $item['sku'],
        'price' => (int) $item['price'],
        'qty' => $qty,
        'image' => (string) $item['image'],
    ];
    cartSetItems($items);
}

function cartRemoveItemById(string $itemId): void
{
    $items = array_values(array_filter(cartGetItems(), static function (array $item) use ($itemId) {
        return (string) $item['id'] !== $itemId;
    }));
    cartSetItems($items);
}

function cartUpdateQuantities(array $qtyMap): void
{
    $items = cartGetItems();
    foreach ($items as &$item) {
        if (isset($qtyMap[$item['id']])) {
            $item['qty'] = max(1, (int) $qtyMap[$item['id']]);
        }
    }
    unset($item);
    cartSetItems($items);
}

function cartCountItems(): int
{
    $count = 0;
    foreach (cartGetItems() as $item) {
        $count += (int) $item['qty'];
    }
    return $count;
}

function cartClear(): void
{
    $_SESSION['cart_items'] = [];
    $_SESSION['cart_coupon'] = '';
    $_SESSION['cart_coupon_id'] = 0;
    unset($_SESSION['cart_coupon_auto_skip']);
}

function cartSyncPricesFromDb(mysqli $conn): void
{
    $items = cartGetItems();
    if (empty($items)) {
        return;
    }

    $stmt = $conn->prepare("SELECT id, base_price, stock_status, name, sku
                            FROM products
                            WHERE id = ?
                              AND is_active = 1
                            LIMIT 1");
    if (!$stmt) {
        return;
    }

    $updated = [];
    foreach ($items as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        if ($productId <= 0) {
            $updated[] = $item;
            continue;
        }
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            continue;
        }
        $item['price'] = (int) round((float) $row['base_price']);
        $item['name'] = (string) $row['name'];
        $item['sku'] = (string) $row['sku'];
        $item['stock_status'] = (string) $row['stock_status'];
        $updated[] = $item;
    }
    $stmt->close();

    cartSetItems($updated);
}

/**
 * Kiểm tra giỏ trước khi đặt hàng (tồn tại, còn bán, chưa hết hàng).
 *
 * @return array{ok:bool,message:string}
 */
function cartValidateForCheckout(mysqli $conn, array $items): array
{
    if ($items === []) {
        return ['ok' => false, 'message' => 'Giỏ hàng đang trống, chưa thể thanh toán.'];
    }

    require_once __DIR__ . '/inventory-repository.php';
    return inventoryValidateCartItems($conn, $items);
}

function cartSetCoupon(?array $coupon): void
{
    if ($coupon === null) {
        $_SESSION['cart_coupon'] = '';
        $_SESSION['cart_coupon_id'] = 0;
        return;
    }
    $_SESSION['cart_coupon'] = (string) $coupon['code'];
    $_SESSION['cart_coupon_id'] = (int) $coupon['id'];
}

function cartCalculateTotals(array $items, ?mysqli $conn = null, ?int $customerId = null): array
{
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += ((int) $item['price']) * ((int) $item['qty']);
    }

    $shipping = isset($_SESSION['selected_shipping_fee']) ? (int) $_SESSION['selected_shipping_fee'] : ($subtotal > 0 ? 30000 : 0);
    $discount = 0.0;
    $couponCode = trim((string) ($_SESSION['cart_coupon'] ?? ''));

    if ($couponCode !== '' && $conn instanceof mysqli) {
        require_once __DIR__ . '/coupon-repository.php';
        $validation = couponValidate($conn, $couponCode, (float) $subtotal, $customerId);
        if ($validation['ok']) {
            $discount = couponCalculateDiscount($validation['coupon'], (float) $subtotal, (float) $shipping);
        } else {
            cartSetCoupon(null);
        }
    }

    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'discount' => (int) round($discount),
        'total' => max(0, (int) round($subtotal + $shipping - $discount)),
        'coupon_code' => $couponCode,
        'coupon_id' => (int) ($_SESSION['cart_coupon_id'] ?? 0),
    ];
}
