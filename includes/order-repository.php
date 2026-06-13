<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function orderEnsureSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $orderCols = [
        'inventory_deducted' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'inventory_restocked' => 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
    foreach ($orderCols as $col => $def) {
        $check = $conn->query("SHOW COLUMNS FROM orders LIKE '" . $conn->real_escape_string($col) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE orders ADD COLUMN {$col} {$def}");
        }
    }

    $checkItem = $conn->query("SHOW COLUMNS FROM order_items LIKE 'stock_deducted'");
    if ($checkItem && $checkItem->num_rows === 0) {
        $conn->query('ALTER TABLE order_items ADD COLUMN stock_deducted TINYINT(1) NOT NULL DEFAULT 0');
    }

    $conn->query("UPDATE orders
                  SET status = 'shipped', fulfillment_status = 'shipped'
                  WHERE status IN ('pending', 'processing', 'packed')");

    $conn->query("UPDATE orders
                  SET fulfillment_status = 'shipped'
                  WHERE fulfillment_status IN ('pending', 'processing', 'packed')
                    AND status NOT IN ('cancelled', 'returned', 'delivered')");

    $conn->query("UPDATE orders
                  SET fulfillment_status = CASE
                      WHEN status IN ('cancelled', 'returned') THEN status
                      WHEN status = 'delivered' THEN 'delivered'
                      ELSE 'shipped'
                  END
                  WHERE fulfillment_status IS NULL OR fulfillment_status = ''");

    $conn->query("UPDATE order_shipments
                  SET status = 'shipped', shipped_at = COALESCE(shipped_at, NOW())
                  WHERE status IN ('pending', 'processing', 'packed')");
}

function orderGetShippingMethods(mysqli $conn): array
{
    $result = $conn->query("SELECT id, code, name, fee, eta_label FROM shipping_methods WHERE is_active = 1 ORDER BY fee ASC, id ASC");
    if (!$result) {
        return [];
    }
    $methods = [];
    while ($row = $result->fetch_assoc()) {
        $methods[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'name' => $row['name'],
            'fee' => (float) $row['fee'],
            'eta_label' => $row['eta_label'] ?? ''
        ];
    }
    return $methods;
}

function orderGetPaymentMethods(mysqli $conn): array
{
    $result = $conn->query("SELECT id, code, name, description FROM payment_methods WHERE is_active = 1 ORDER BY id ASC");
    if (!$result) {
        return [];
    }
    $methods = [];
    while ($row = $result->fetch_assoc()) {
        $methods[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'name' => orderPaymentMethodDisplayName((string) $row['code'], (string) $row['name']),
            'description' => $row['description'] ?? ''
        ];
    }
    return $methods;
}

function orderPaymentMethodDisplayName(string $code, string $name): string
{
    if (in_array($code, ['bank_transfer', 'vietqr'], true)) {
        return 'Chuyển khoản VietQR';
    }

    return $name;
}

function orderFindShippingMethod(array $methods, int $id): ?array
{
    foreach ($methods as $method) {
        if ((int) $method['id'] === $id) {
            return $method;
        }
    }
    return null;
}

function orderFindPaymentMethod(array $methods, int $id): ?array
{
    foreach ($methods as $method) {
        if ((int) $method['id'] === $id) {
            return $method;
        }
    }
    return null;
}

/**
 * Gán phí ship vào session theo ID phương thức (trả về phí hoặc null nếu không hợp lệ).
 */
function orderApplyShippingToSession(array $shippingMethods, int $shippingMethodId): ?int
{
    $method = orderFindShippingMethod($shippingMethods, $shippingMethodId);
    if (!$method) {
        return null;
    }
    $fee = (int) round((float) $method['fee']);
    $_SESSION['selected_shipping_fee'] = $fee;
    $_SESSION['checkout_shipping_method_id'] = (int) $method['id'];
    return $fee;
}

function orderGenerateCode(): string
{
    // 10 ký tự: WS + tháng/ngày (4) + 4 số ngẫu nhiên — VD: WS05192384
    return 'WS' . date('md') . sprintf('%04d', random_int(0, 9999));
}

function orderCodeExists(mysqli $conn, string $orderCode): bool
{
    $stmt = $conn->prepare('SELECT id FROM orders WHERE order_code = ? LIMIT 1');
    if (!$stmt) {
        return true;
    }
    $stmt->bind_param('s', $orderCode);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function orderGenerateUniqueCode(mysqli $conn, int $maxAttempts = 10): string
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $orderCode = orderGenerateCode();
        if (!orderCodeExists($conn, $orderCode)) {
            return $orderCode;
        }
    }

    throw new RuntimeException('Không thể tạo mã đơn hàng duy nhất.');
}

function orderCreateFromCheckout(
    mysqli $conn,
    array $customer,
    array $cartItems,
    array $totals,
    ?string $couponCode,
    ?int $customerId,
    int $shippingMethodId,
    int $paymentMethodId,
    ?int $couponId = null
): string {
    $orderCode = orderGenerateUniqueCode($conn);
    $couponCode = $couponCode ?: null;
    $couponIdValue = $couponId && $couponId > 0 ? $couponId : null;
    $customerEmail = $customer['email'] !== '' ? $customer['email'] : null;
    $customerNote = $customer['note'] !== '' ? $customer['note'] : null;

    $conn->begin_transaction();
    try {
        if ($couponIdValue) {
            require_once __DIR__ . '/coupon-repository.php';
            couponAssertAvailableInTransaction(
                $conn,
                (int) $couponIdValue,
                $customerId,
                (string) $customer['phone']
            );
        }

        $stmtOrder = $conn->prepare("INSERT INTO orders
            (order_code, customer_id, customer_name, customer_phone, customer_email, customer_address, customer_note,
             coupon_id, coupon_code, subtotal, shipping_fee, discount_amount, grand_total, status, fulfillment_status, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'shipped', 'shipped', 'unpaid')");
        if (!$stmtOrder) {
            throw new RuntimeException('Không tạo được lệnh lưu đơn hàng.');
        }

        $customerIdValue = $customerId ?: null;
        $subtotal = (float) $totals['subtotal'];
        $shipping = (float) $totals['shipping'];
        $discount = (float) $totals['discount'];
        $grandTotal = (float) $totals['total'];

        $stmtOrder->bind_param(
            'sisssssisdddd',
            $orderCode,
            $customerIdValue,
            $customer['name'],
            $customer['phone'],
            $customerEmail,
            $customer['address'],
            $customerNote,
            $couponIdValue,
            $couponCode,
            $subtotal,
            $shipping,
            $discount,
            $grandTotal
        );
        $stmtOrder->execute();
        $orderId = (int) $stmtOrder->insert_id;
        $stmtOrder->close();

        $stmtItem = $conn->prepare("INSERT INTO order_items
            (order_id, product_id, product_sku, product_name, product_image, unit_price, quantity, line_total)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmtItem) {
            throw new RuntimeException('Không tạo được lệnh lưu chi tiết đơn hàng.');
        }
        foreach ($cartItems as $item) {
            $productId = isset($item['product_id']) && (int) $item['product_id'] > 0 ? (int) $item['product_id'] : null;
            $sku = (string) $item['sku'];
            $name = (string) $item['name'];
            $image = (string) $item['image'];
            $unitPrice = (float) $item['price'];
            $quantity = (int) $item['qty'];
            $lineTotal = $unitPrice * $quantity;
            $stmtItem->bind_param('iisssdid', $orderId, $productId, $sku, $name, $image, $unitPrice, $quantity, $lineTotal);
            $stmtItem->execute();
        }
        $stmtItem->close();

        $stmtShipment = $conn->prepare("INSERT INTO order_shipments
            (order_id, shipping_method_id, recipient_name, recipient_phone, recipient_address, shipping_fee, status, shipped_at)
            VALUES (?, ?, ?, ?, ?, ?, 'shipped', NOW())");
        if (!$stmtShipment) {
            throw new RuntimeException('Không tạo được lệnh vận chuyển.');
        }
        $stmtShipment->bind_param(
            'iisssd',
            $orderId,
            $shippingMethodId,
            $customer['name'],
            $customer['phone'],
            $customer['address'],
            $shipping
        );
        $stmtShipment->execute();
        $stmtShipment->close();

        $transactionCode = null;
        $gatewayResponse = null;
        $paymentStatus = 'pending';
        $paidAt = null;
        $stmtPayment = $conn->prepare("INSERT INTO order_payments
            (order_id, payment_method_id, amount, transaction_code, gateway_response, status, paid_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmtPayment) {
            throw new RuntimeException('Không tạo được lệnh thanh toán.');
        }
        $stmtPayment->bind_param('iidssss', $orderId, $paymentMethodId, $grandTotal, $transactionCode, $gatewayResponse, $paymentStatus, $paidAt);
        $stmtPayment->execute();
        $stmtPayment->close();

        if ($couponIdValue) {
            require_once __DIR__ . '/coupon-repository.php';
            couponRecordRedemption($conn, (int) $couponIdValue, $customerId, $orderId);
        }

        require_once __DIR__ . '/inventory-repository.php';
        $deductResult = inventoryDeductForOrder($conn, $cartItems, $orderId, $orderCode);
        if (!empty($deductResult['any_deducted'])) {
            $stmtInvFlag = $conn->prepare('UPDATE orders SET inventory_deducted = 1 WHERE id = ?');
            if ($stmtInvFlag) {
                $stmtInvFlag->bind_param('i', $orderId);
                $stmtInvFlag->execute();
                $stmtInvFlag->close();
            }
        }

        $conn->commit();
        return $orderCode;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function orderGetCustomerOrders(mysqli $conn, int $customerId): array
{
    $stmt = $conn->prepare("SELECT id, order_code, status, payment_status, fulfillment_status, grand_total, ordered_at
                            FROM orders
                            WHERE customer_id = ?
                            ORDER BY id DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    return $orders;
}

function orderEnrichOrderDetail(mysqli $conn, array $order): array
{
    $orderId = (int) $order['id'];

    $stmtItems = $conn->prepare("SELECT product_sku, product_name, product_image, unit_price, quantity, line_total
                                 FROM order_items
                                 WHERE order_id = ?
                                 ORDER BY id ASC");
    $items = [];
    if ($stmtItems) {
        $stmtItems->bind_param('i', $orderId);
        $stmtItems->execute();
        $itemsResult = $stmtItems->get_result();
        while ($row = $itemsResult->fetch_assoc()) {
            $items[] = $row;
        }
        $stmtItems->close();
    }

    $stmtShipment = $conn->prepare("SELECT os.recipient_name, os.recipient_phone, os.recipient_address, os.shipping_fee, os.status,
                                           os.shipped_at, os.delivered_at,
                                           sm.name AS shipping_method_name, sm.eta_label
                                    FROM order_shipments os
                                    LEFT JOIN shipping_methods sm ON sm.id = os.shipping_method_id
                                    WHERE os.order_id = ?
                                    ORDER BY os.id DESC
                                    LIMIT 1");
    $shipment = null;
    if ($stmtShipment) {
        $stmtShipment->bind_param('i', $orderId);
        $stmtShipment->execute();
        $shipment = $stmtShipment->get_result()->fetch_assoc() ?: null;
        $stmtShipment->close();
    }

    $stmtPayment = $conn->prepare("SELECT op.status, op.amount, pm.code AS payment_method_code, pm.name AS payment_method_name
                                   FROM order_payments op
                                   LEFT JOIN payment_methods pm ON pm.id = op.payment_method_id
                                   WHERE op.order_id = ?
                                   ORDER BY op.id DESC
                                   LIMIT 1");
    $payment = null;
    if ($stmtPayment) {
        $stmtPayment->bind_param('i', $orderId);
        $stmtPayment->execute();
        $payment = $stmtPayment->get_result()->fetch_assoc() ?: null;
        $stmtPayment->close();
        if ($payment) {
            $payment['payment_method_name'] = orderPaymentMethodDisplayName(
                (string) ($payment['payment_method_code'] ?? ''),
                (string) ($payment['payment_method_name'] ?? '')
            );
        }
    }

    $order['items'] = $items;
    $order['shipment'] = $shipment;
    $order['payment'] = $payment;

    require_once __DIR__ . '/return-repository.php';
    returnEnsureSchema($conn);
    $order['return_request'] = returnGetByOrderId($conn, $orderId);

    return $order;
}

/**
 * Cập nhật thanh toán trong transaction hiện có (không kiểm tra lock).
 */
function orderSetPaymentStatusInTransaction(mysqli $conn, int $orderId, string $newStatus, string $changedBy = 'system'): void
{
    $allowed = ['unpaid', 'paid', 'failed', 'refunded'];
    if (!in_array($newStatus, $allowed, true)) {
        return;
    }

    $stmt = $conn->prepare('UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('si', $newStatus, $orderId);
    $stmt->execute();
    $stmt->close();

    $stmtPay = $conn->prepare('UPDATE order_payments SET status = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1');
    if ($stmtPay) {
        $payStatus = $newStatus === 'paid' ? 'paid' : ($newStatus === 'refunded' ? 'refunded' : 'pending');
        $stmtPay->bind_param('si', $payStatus, $orderId);
        $stmtPay->execute();
        $stmtPay->close();
    }
    if ($newStatus === 'paid') {
        $paidAt = date('Y-m-d H:i:s');
        $stmtPaid = $conn->prepare('UPDATE order_payments SET paid_at = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        if ($stmtPaid) {
            $stmtPaid->bind_param('si', $paidAt, $orderId);
            $stmtPaid->execute();
            $stmtPaid->close();
        }
    }
}

/**
 * Ghi delivered_at và auto paid COD khi đơn chuyển sang đã giao (trong transaction).
 */
function orderMarkDeliveredSideEffects(mysqli $conn, int $orderId): void
{
    $stmtShip = $conn->prepare("UPDATE order_shipments
                                SET delivered_at = COALESCE(delivered_at, NOW()),
                                    status = 'delivered',
                                    updated_at = NOW()
                                WHERE order_id = ?");
    if ($stmtShip) {
        $stmtShip->bind_param('i', $orderId);
        $stmtShip->execute();
        $stmtShip->close();
    }

    $stmtOrder = $conn->prepare('SELECT payment_status FROM orders WHERE id = ? LIMIT 1');
    if (!$stmtOrder) {
        return;
    }
    $stmtOrder->bind_param('i', $orderId);
    $stmtOrder->execute();
    $orderRow = $stmtOrder->get_result()->fetch_assoc();
    $stmtOrder->close();
    if (!$orderRow || ($orderRow['payment_status'] ?? '') !== 'unpaid') {
        return;
    }

    $stmtPm = $conn->prepare("SELECT pm.code
                              FROM order_payments op
                              INNER JOIN payment_methods pm ON pm.id = op.payment_method_id
                              WHERE op.order_id = ?
                              ORDER BY op.id DESC
                              LIMIT 1");
    if (!$stmtPm) {
        return;
    }
    $stmtPm->bind_param('i', $orderId);
    $stmtPm->execute();
    $pmRow = $stmtPm->get_result()->fetch_assoc();
    $stmtPm->close();

    if (($pmRow['code'] ?? '') === 'cod') {
        orderSetPaymentStatusInTransaction($conn, $orderId, 'paid', 'system');
    }
}

function orderGetOrderDetailByCode(mysqli $conn, string $orderCode): ?array
{
    $orderCode = trim($orderCode);
    if ($orderCode === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, order_code, customer_name, customer_phone, customer_email, customer_address, customer_note,
                                   subtotal, shipping_fee, discount_amount, grand_total, status, payment_status, fulfillment_status,
                                   inventory_deducted, inventory_restocked, ordered_at
                            FROM orders
                            WHERE order_code = ?
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $orderCode);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        return null;
    }

    return orderEnrichOrderDetail($conn, $order);
}

function orderGetOrderDetailByCodeAndPhone(mysqli $conn, string $orderCode, string $phone): ?array
{
    $order = orderGetOrderDetailByCode($conn, $orderCode);
    if ($order === null) {
        return null;
    }
    if (phoneNormalize((string) $order['customer_phone']) !== phoneNormalize($phone)) {
        return null;
    }
    return $order;
}

function orderGetCustomerOrderDetailByCode(mysqli $conn, int $customerId, string $orderCode): ?array
{
    $orderCode = trim($orderCode);
    if ($orderCode === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT id, order_code, customer_id, customer_name, customer_phone, customer_email, customer_address, customer_note,
                                   subtotal, shipping_fee, discount_amount, grand_total, status, payment_status, fulfillment_status, ordered_at
                            FROM orders
                            WHERE customer_id = ?
                              AND order_code = ?
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $customerId, $orderCode);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        return null;
    }

    return orderEnrichOrderDetail($conn, $order);
}

function orderGetAllOrders(mysqli $conn, int $limit = 50, string $search = '', int $customerId = 0): array
{
    $search = trim($search);
    if ($search === '' && $customerId <= 0) {
        $stmt = $conn->prepare("SELECT id, order_code, customer_id, customer_name, customer_phone, status, payment_status,
                                       fulfillment_status, grand_total, ordered_at
                                FROM orders
                                ORDER BY id DESC
                                LIMIT ?");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $limit);
    } elseif ($search === '') {
        $stmt = $conn->prepare("SELECT id, order_code, customer_id, customer_name, customer_phone, status, payment_status,
                                       fulfillment_status, grand_total, ordered_at
                                FROM orders
                                WHERE customer_id = ?
                                ORDER BY id DESC
                                LIMIT ?");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $customerId, $limit);
    } elseif ($customerId <= 0) {
        $like = '%' . $search . '%';
        $stmt = $conn->prepare("SELECT id, order_code, customer_id, customer_name, customer_phone, status, payment_status,
                                       fulfillment_status, grand_total, ordered_at
                                FROM orders
                                WHERE order_code LIKE ?
                                   OR customer_name LIKE ?
                                   OR customer_phone LIKE ?
                                ORDER BY id DESC
                                LIMIT ?");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('sssi', $like, $like, $like, $limit);
    } else {
        $like = '%' . $search . '%';
        $stmt = $conn->prepare("SELECT id, order_code, customer_id, customer_name, customer_phone, status, payment_status,
                                       fulfillment_status, grand_total, ordered_at
                                FROM orders
                                WHERE customer_id = ?
                                  AND (order_code LIKE ?
                                       OR customer_name LIKE ?
                                       OR customer_phone LIKE ?)
                                ORDER BY id DESC
                                LIMIT ?");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('isssi', $customerId, $like, $like, $like, $limit);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
    return $orders;
}

function orderShouldRestockForStatus(string $status): bool
{
    return in_array($status, ['cancelled', 'returned'], true);
}

/**
 * Khách hủy đơn được khi đơn chưa giao xong, chưa hủy, chưa trả.
 */
function orderCanCustomerCancel(array $order): bool
{
    $status = (string) ($order['status'] ?? '');
    $fulfillment = (string) ($order['fulfillment_status'] ?? '');

    if (in_array($status, ['cancelled', 'returned', 'delivered', 'return_pending', 'return_accepted', 'return_received'], true)) {
        return false;
    }
    if (in_array($fulfillment, ['delivered', 'cancelled', 'returned'], true)) {
        return false;
    }

    return $status === 'shipped';
}

/**
 * @return array{ok:bool,message:string}
 */
function orderCustomerCancel(mysqli $conn, int $orderId, string $changedBy = 'customer'): array
{
    $stmt = $conn->prepare('SELECT id, status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];
    }
    if (!orderCanCustomerCancel($order)) {
        return ['ok' => false, 'message' => 'Đơn hàng không thể hủy ở trạng thái hiện tại.'];
    }
    if (!orderUpdateStatus($conn, $orderId, 'cancelled', $changedBy)) {
        return ['ok' => false, 'message' => 'Không thể hủy đơn hàng. Vui lòng thử lại.'];
    }
    return ['ok' => true, 'message' => 'Đã hủy đơn hàng thành công.'];
}

/**
 * @return array{ok:bool,message:string}
 */
function orderCustomerCancelByCodeAndPhone(mysqli $conn, string $orderCode, string $phone): array
{
    $order = orderGetOrderDetailByCodeAndPhone($conn, $orderCode, $phone);
    if (!$order) {
        return ['ok' => false, 'message' => 'Thông tin đơn hàng hoặc số điện thoại không chính xác.'];
    }
    return orderCustomerCancel($conn, (int) $order['id'], 'customer');
}

/**
 * @return array{ok:bool,message:string}
 */
function orderCustomerCancelForAccount(mysqli $conn, int $customerId, string $orderCode): array
{
    $order = orderGetCustomerOrderDetailByCode($conn, $customerId, $orderCode);
    if (!$order) {
        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];
    }
    return orderCustomerCancel($conn, (int) $order['id'], 'customer');
}

function orderMaybeRestockInventory(mysqli $conn, int $orderId): void
{
    if ($orderId <= 0) {
        return;
    }
    require_once __DIR__ . '/inventory-repository.php';
    inventoryRestockForOrder($conn, $orderId);
}

function orderIsLocked(array $order): bool
{
    return in_array((string) ($order['status'] ?? ''), ['cancelled', 'returned'], true)
        || in_array((string) ($order['fulfillment_status'] ?? ''), ['cancelled', 'returned'], true);
}

function orderStatusBlocksAdminChange(array $order): bool
{
    $status = (string) ($order['status'] ?? '');
    $fulfillment = (string) ($order['fulfillment_status'] ?? '');

    return in_array($status, ['delivered', 'cancelled', 'returned', 'return_pending', 'return_accepted', 'return_received'], true)
        || in_array($fulfillment, ['delivered', 'cancelled', 'returned'], true);
}

function orderFulfillmentBlocksAdminChange(array $order): bool
{
    $status = (string) ($order['status'] ?? '');
    $fulfillment = (string) ($order['fulfillment_status'] ?? '');

    if (in_array($status, ['cancelled', 'returned', 'delivered', 'return_pending', 'return_accepted', 'return_received'], true)) {
        return true;
    }

    return in_array($fulfillment, ['delivered', 'cancelled', 'returned'], true);
}

function orderApplyStatusUpdate(mysqli $conn, int $orderId, string $newStatus, string $changedBy, array $current): bool
{
    $fromStatus = (string) $current['status'];
    $fulfillment = (string) $current['fulfillment_status'];

    if ($newStatus === 'cancelled') {
        $fulfillment = 'cancelled';
    } elseif ($newStatus === 'returned') {
        $fulfillment = 'returned';
    } elseif (in_array($newStatus, ['return_pending', 'return_accepted', 'return_received'], true)) {
        $fulfillment = 'delivered';
    } elseif ($newStatus === 'delivered') {
        $fulfillment = 'delivered';
    } elseif ($newStatus === 'shipped') {
        $fulfillment = 'shipped';
    }

    $stmt = $conn->prepare('UPDATE orders SET status = ?, fulfillment_status = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssi', $newStatus, $fulfillment, $orderId);
    $stmt->execute();
    $stmt->close();

    if ($newStatus === 'delivered') {
        orderMarkDeliveredSideEffects($conn, $orderId);
    }

    if (orderShouldRestockForStatus($newStatus)) {
        orderMaybeRestockInventory($conn, $orderId);
    }

    if (in_array($newStatus, ['cancelled', 'returned'], true)) {
        require_once __DIR__ . '/coupon-repository.php';
        couponReleaseRedemption($conn, $orderId);
    }

    return true;
}

function orderUpdateStatus(mysqli $conn, int $orderId, string $newStatus, string $changedBy = 'admin'): bool
{
    $allowed = ['shipped', 'delivered', 'cancelled', 'returned',
        'return_pending', 'return_accepted', 'return_received'];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }

    $returnFlowActors = ['customer_return', 'return_accept', 'return_reject', 'return_goods_received', 'return_complete'];
    if (in_array($newStatus, ['return_pending', 'return_accepted', 'return_received', 'returned'], true)
        && !in_array($changedBy, $returnFlowActors, true)) {
        return false;
    }

    $stmtCurrent = $conn->prepare('SELECT status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');
    if (!$stmtCurrent) {
        return false;
    }
    $stmtCurrent->bind_param('i', $orderId);
    $stmtCurrent->execute();
    $current = $stmtCurrent->get_result()->fetch_assoc();
    $stmtCurrent->close();
    if (!$current) {
        return false;
    }

    $fromStatus = (string) $current['status'];
    if (orderStatusBlocksAdminChange($current) && $newStatus !== $fromStatus) {
        return false;
    }

    $conn->begin_transaction();
    try {
        if (!orderApplyStatusUpdate($conn, $orderId, $newStatus, $changedBy, $current)) {
            throw new RuntimeException('Không cập nhật được trạng thái đơn.');
        }
        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function orderUpdatePaymentStatus(mysqli $conn, int $orderId, string $newStatus, string $changedBy = 'admin'): bool
{
    $allowed = ['unpaid', 'paid', 'failed', 'refunded'];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }

    $stmtCurrent = $conn->prepare('SELECT status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');
    if (!$stmtCurrent) {
        return false;
    }
    $stmtCurrent->bind_param('i', $orderId);
    $stmtCurrent->execute();
    $current = $stmtCurrent->get_result()->fetch_assoc();
    $stmtCurrent->close();
    if (!$current || orderIsLocked($current)) {
        return false;
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('Không cập nhật trạng thái thanh toán.');
        }
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();

        $stmtPay = $conn->prepare('UPDATE order_payments SET status = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1');
        if ($stmtPay) {
            $payStatus = $newStatus === 'paid' ? 'paid' : ($newStatus === 'refunded' ? 'refunded' : 'pending');
            $stmtPay->bind_param('si', $payStatus, $orderId);
            $stmtPay->execute();
            $stmtPay->close();
        }
        if ($newStatus === 'paid') {
            $paidAt = date('Y-m-d H:i:s');
            $stmtPaid = $conn->prepare('UPDATE order_payments SET paid_at = ? WHERE order_id = ? ORDER BY id DESC LIMIT 1');
            if ($stmtPaid) {
                $stmtPaid->bind_param('si', $paidAt, $orderId);
                $stmtPaid->execute();
                $stmtPaid->close();
            }
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function orderUpdateFulfillmentStatus(mysqli $conn, int $orderId, string $newStatus, string $changedBy = 'admin'): bool
{
    $allowed = ['shipped', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $allowed, true)) {
        return false;
    }

    $stmtCurrent = $conn->prepare('SELECT status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');
    if (!$stmtCurrent) {
        return false;
    }
    $stmtCurrent->bind_param('i', $orderId);
    $stmtCurrent->execute();
    $current = $stmtCurrent->get_result()->fetch_assoc();
    $stmtCurrent->close();
    if (!$current) {
        return false;
    }

    $orderStatus = (string) $current['status'];
    $fromStatus = (string) $current['fulfillment_status'];
    if (orderFulfillmentBlocksAdminChange($current) && $newStatus !== $fromStatus) {
        return false;
    }

    $conn->begin_transaction();
    try {
        $nextOrderStatus = match ($newStatus) {
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled',
            default => $orderStatus,
        };

        $stmt = $conn->prepare('UPDATE orders SET status = ?, fulfillment_status = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('Không cập nhật trạng thái giao hàng.');
        }
        $stmt->bind_param('ssi', $nextOrderStatus, $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();

        if ($newStatus === 'cancelled') {
            orderMaybeRestockInventory($conn, $orderId);
        }

        if ($newStatus === 'delivered') {
            orderMarkDeliveredSideEffects($conn, $orderId);
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function orderStatusLabel(string $status): string
{
    return match ($status) {
        'shipped' => 'Đang giao hàng',
        'delivered' => 'Đã giao hàng',
        'cancelled' => 'Đã hủy',
        'return_pending' => 'Chờ Admin duyệt hoàn hàng',
        'return_accepted' => 'Chờ khách trả hàng',
        'return_received' => 'Đã nhận hàng hoàn — chờ hoàn tiền',
        'returned' => 'Hoàn hàng thành công',
        default => 'Đang giao hàng',
    };
}

function orderPaymentStatusLabel(string $status): string
{
    return match ($status) {
        'unpaid' => 'Chưa thanh toán',
        'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại',
        'refunded' => 'Đã hoàn tiền',
        default => $status,
    };
}

function orderFulfillmentStatusLabel(string $status): string
{
    return match ($status) {
        'shipped' => 'Đang giao hàng',
        'delivered' => 'Đã giao hàng',
        'cancelled' => 'Đã hủy giao',
        'returned' => 'Đã hoàn trả',
        default => 'Đang giao hàng',
    };
}

function orderShippingStatusOptions(): array
{
    return ['shipped', 'delivered'];
}

/** Chuẩn hóa fulfillment sang nhóm trạng thái giao hàng admin. */
function orderFulfillmentToShippingKey(string $fulfillment): string
{
    return match ($fulfillment) {
        'delivered' => 'delivered',
        'cancelled', 'returned' => 'cancelled',
        default => 'shipped',
    };
}

function orderPaymentStatusOptions(): array
{
    return ['unpaid', 'paid', 'refunded'];
}
