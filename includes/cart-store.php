<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function cartSessionKey(): string
{
    if (empty($_SESSION['cart_session_key']) || !is_string($_SESSION['cart_session_key'])) {
        $_SESSION['cart_session_key'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['cart_session_key'];
}

function cartConnection(): mysqli
{
    global $conn;
    if (!$conn instanceof mysqli) {
        throw new RuntimeException('Chưa có kết nối database cho giỏ hàng.');
    }

    return $conn;
}

function cartCurrentId(bool $create = true): int
{
    $conn = cartConnection();
    $customerId = (int) ($_SESSION['customer_id'] ?? 0);

    if ($customerId > 0) {
        $stmt = $conn->prepare('SELECT id FROM carts WHERE customer_id = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int) $row['id'];
        }
        if (!$create) {
            return 0;
        }
        $insert = $conn->prepare('INSERT INTO carts (customer_id, session_key) VALUES (?, NULL)');
        if (!$insert) {
            return 0;
        }
        $insert->bind_param('i', $customerId);
        $insert->execute();
        $id = (int) $insert->insert_id;
        $insert->close();

        return $id;
    }

    $key = cartSessionKey();
    $stmt = $conn->prepare('SELECT id FROM carts WHERE session_key = ? AND customer_id IS NULL LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        return (int) $row['id'];
    }
    if (!$create) {
        return 0;
    }
    $insert = $conn->prepare('INSERT INTO carts (customer_id, session_key) VALUES (NULL, ?)');
    if (!$insert) {
        return 0;
    }
    $insert->bind_param('s', $key);
    $insert->execute();
    $id = (int) $insert->insert_id;
    $insert->close();

    return $id;
}

function cartSyncCouponToSession(int $cartId): void
{
    if ($cartId <= 0) {
        return;
    }
    $conn = cartConnection();
    $stmt = $conn->prepare('SELECT coupon_code, coupon_id FROM carts WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $_SESSION['cart_coupon'] = (string) ($row['coupon_code'] ?? '');
    $_SESSION['cart_coupon_id'] = (int) ($row['coupon_id'] ?? 0);
}

function cartGetItems(): array
{
    $cartId = cartCurrentId(false);
    if ($cartId <= 0) {
        return [];
    }
    cartSyncCouponToSession($cartId);
    $conn = cartConnection();
    $stmt = $conn->prepare("SELECT ci.product_id, ci.qty, p.name, p.slug, p.sku, p.base_price, p.stock_status,
                                   COALESCE(pi.image_url, 'assets/images/blog_1.png') AS image
                            FROM cart_items ci
                            INNER JOIN products p ON p.id = ci.product_id AND p.is_active = 1
                            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                            WHERE ci.cart_id = ?
                            ORDER BY ci.id ASC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $cartId);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $productId = (int) $row['product_id'];
        $items[] = [
            'id' => 'product-' . $productId,
            'product_id' => $productId,
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'sku' => (string) $row['sku'],
            'price' => (int) round((float) $row['base_price']),
            'qty' => (int) $row['qty'],
            'image' => (string) $row['image'],
            'stock_status' => (string) $row['stock_status'],
        ];
    }
    $stmt->close();

    return $items;
}

function cartSetItems(array $items): void
{
    $cartId = cartCurrentId(true);
    if ($cartId <= 0) {
        return;
    }
    $conn = cartConnection();
    $conn->begin_transaction();
    try {
        $delete = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        if (!$delete) {
            throw new RuntimeException('Không cập nhật được giỏ hàng.');
        }
        $delete->bind_param('i', $cartId);
        $delete->execute();
        $delete->close();

        $insert = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, qty) VALUES (?, ?, ?)');
        if (!$insert) {
            throw new RuntimeException('Không cập nhật được giỏ hàng.');
        }
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = max(1, (int) ($item['qty'] ?? 1));
            if ($productId <= 0) {
                continue;
            }
            $insert->bind_param('iii', $cartId, $productId, $qty);
            $insert->execute();
        }
        $insert->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
    }
    unset($_SESSION['cart_coupon_auto_skip']);
}

function cartAddItem(array $item, int $qty = 1): void
{
    $productId = (int) ($item['product_id'] ?? 0);
    $qty = max(1, $qty);
    if ($productId <= 0) {
        return;
    }
    $cartId = cartCurrentId(true);
    $conn = cartConnection();
    $stmt = $conn->prepare('SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $cartId, $productId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($existing) {
        $newQty = (int) $existing['qty'] + $qty;
        $id = (int) $existing['id'];
        $update = $conn->prepare('UPDATE cart_items SET qty = ? WHERE id = ?');
        if ($update) {
            $update->bind_param('ii', $newQty, $id);
            $update->execute();
            $update->close();
        }
        return;
    }
    $insert = $conn->prepare('INSERT INTO cart_items (cart_id, product_id, qty) VALUES (?, ?, ?)');
    if ($insert) {
        $insert->bind_param('iii', $cartId, $productId, $qty);
        $insert->execute();
        $insert->close();
    }
}

function cartRemoveItemById(string $itemId): void
{
    $productId = (int) str_replace('product-', '', $itemId);
    if ($productId <= 0) {
        return;
    }
    $cartId = cartCurrentId(false);
    if ($cartId <= 0) {
        return;
    }
    $conn = cartConnection();
    $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ii', $cartId, $productId);
    $stmt->execute();
    $stmt->close();
}

function cartUpdateQuantities(array $qtyMap): void
{
    $cartId = cartCurrentId(false);
    if ($cartId <= 0) {
        return;
    }
    $conn = cartConnection();
    $stmt = $conn->prepare('UPDATE cart_items SET qty = ? WHERE cart_id = ? AND product_id = ?');
    if (!$stmt) {
        return;
    }
    foreach ($qtyMap as $itemId => $qty) {
        $productId = (int) str_replace('product-', '', (string) $itemId);
        $quantity = max(1, (int) $qty);
        if ($productId <= 0) {
            continue;
        }
        $stmt->bind_param('iii', $quantity, $cartId, $productId);
        $stmt->execute();
    }
    $stmt->close();
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
    $cartId = cartCurrentId(false);
    if ($cartId > 0) {
        $conn = cartConnection();
        $stmt = $conn->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();
        }
        $coupon = $conn->prepare("UPDATE carts SET coupon_code = '', coupon_id = NULL WHERE id = ?");
        if ($coupon) {
            $coupon->bind_param('i', $cartId);
            $coupon->execute();
            $coupon->close();
        }
    }
    $_SESSION['cart_coupon'] = '';
    $_SESSION['cart_coupon_id'] = 0;
    unset($_SESSION['cart_coupon_auto_skip']);
}

function cartSyncPricesFromDb(mysqli $conn): void
{
    cartGetItems();
}

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
    $cartId = cartCurrentId(true);
    $code = $coupon === null ? '' : (string) $coupon['code'];
    $couponId = $coupon === null ? 0 : (int) $coupon['id'];
    $_SESSION['cart_coupon'] = $code;
    $_SESSION['cart_coupon_id'] = $couponId;
    if ($cartId <= 0) {
        return;
    }
    $conn = cartConnection();
    if ($coupon === null) {
        $stmt = $conn->prepare("UPDATE carts SET coupon_code = '', coupon_id = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $cartId);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $stmt = $conn->prepare('UPDATE carts SET coupon_code = ?, coupon_id = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('sii', $code, $couponId, $cartId);
        $stmt->execute();
        $stmt->close();
    }
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

function cartMergeGuestIntoCustomer(int $customerId): void
{
    if ($customerId <= 0) {
        return;
    }
    $conn = cartConnection();
    $key = (string) ($_SESSION['cart_session_key'] ?? '');
    $_SESSION['customer_id'] = $customerId;
    $customerCartId = cartCurrentId(true);

    if ($key === '') {
        return;
    }
    $stmt = $conn->prepare('SELECT id, coupon_code, coupon_id FROM carts WHERE session_key = ? AND customer_id IS NULL LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $guest = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$guest) {
        return;
    }
    $guestId = (int) $guest['id'];
    if ($guestId === $customerCartId) {
        return;
    }

    $items = $conn->prepare('SELECT product_id, qty FROM cart_items WHERE cart_id = ?');
    if ($items) {
        $items->bind_param('i', $guestId);
        $items->execute();
        $result = $items->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $items->close();
        foreach ($rows as $row) {
            cartAddItem(['product_id' => (int) $row['product_id']], (int) $row['qty']);
        }
    }

    $customerCoupon = $conn->prepare('SELECT coupon_code FROM carts WHERE id = ? LIMIT 1');
    $hasCoupon = false;
    if ($customerCoupon) {
        $customerCoupon->bind_param('i', $customerCartId);
        $customerCoupon->execute();
        $couponRow = $customerCoupon->get_result()->fetch_assoc();
        $customerCoupon->close();
        $hasCoupon = trim((string) ($couponRow['coupon_code'] ?? '')) !== '';
    }
    if (!$hasCoupon && trim((string) ($guest['coupon_code'] ?? '')) !== '') {
        cartSetCoupon([
            'code' => (string) $guest['coupon_code'],
            'id' => (int) ($guest['coupon_id'] ?? 0),
        ]);
    }

    $conn->query('DELETE FROM carts WHERE id = ' . $guestId);
    unset($_SESSION['cart_session_key']);
}
