<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Tạo bảng inventory_alerts nếu DB cũ chưa chạy schema (tránh lỗi khi vào trang admin).
 */
function inventoryEnsureAlertsTable(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->query(
        'CREATE TABLE IF NOT EXISTS inventory_alerts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT NOT NULL,
            order_id BIGINT DEFAULT NULL,
            message TEXT NOT NULL,
            alert_type VARCHAR(30) NOT NULL DEFAULT \'stock_depleted\',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_inventory_alerts_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT fk_inventory_alerts_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            INDEX idx_inventory_alerts_unread (is_read, created_at)
        )'
    );
}

/**
 * Số lượng có thể bán của sản phẩm.
 */
function inventoryGetAvailableQty(mysqli $conn, int $productId): int
{
    if ($productId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT quantity_on_hand
                            FROM inventory_items
                            WHERE product_id = ?
                            LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return 0;
    }

    return max(0, (int) $row['quantity_on_hand']);
}

/**
 * Gộp số lượng theo product_id trong giỏ.
 *
 * @return array<int, int>
 */
function inventoryAggregateCartQty(array $cartItems): array
{
    $need = [];
    foreach ($cartItems as $item) {
        $productId = (int) ($item['product_id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }
        $need[$productId] = ($need[$productId] ?? 0) + max(1, (int) ($item['qty'] ?? 1));
    }
    return $need;
}

/**
 * Kiểm tra có thể mua / thêm giỏ theo tồn kho.
 *
 * @return array{ok:bool,message:string}
 */
function inventoryValidatePurchase(mysqli $conn, int $productId, string $stockStatus, int $requestedQty): array
{
    if ($productId <= 0) {
        return ['ok' => false, 'message' => 'Sản phẩm không hợp lệ.'];
    }
    if ($stockStatus === 'out_of_stock') {
        return ['ok' => false, 'message' => 'Sản phẩm đã hết hàng.'];
    }
    if ($stockStatus === 'preorder') {
        if (!inventoryProductHasRow($conn, $productId)) {
            return ['ok' => false, 'message' => 'Sản phẩm chưa được thiết lập tồn kho.'];
        }
        if ($requestedQty < 1) {
            return ['ok' => false, 'message' => 'Số lượng không hợp lệ.'];
        }
        return ['ok' => true, 'message' => ''];
    }
    if ($requestedQty < 1) {
        return ['ok' => false, 'message' => 'Số lượng không hợp lệ.'];
    }

    $available = inventoryGetAvailableQty($conn, $productId);
    if ($available < $requestedQty) {
        return [
            'ok' => false,
            'message' => 'Chỉ còn ' . $available . ' sản phẩm trong kho. Vui lòng giảm số lượng.',
        ];
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * Kiểm tra toàn bộ giỏ (gộp SL theo SP).
 *
 * @return array{ok:bool,message:string}
 */
function inventoryValidateCartItems(mysqli $conn, array $cartItems): array
{
    require_once __DIR__ . '/product-repository.php';

    $need = inventoryAggregateCartQty($cartItems);
    foreach ($need as $productId => $qty) {
        $product = productGetById($conn, $productId);
        if (!$product) {
            return ['ok' => false, 'message' => 'Một số sản phẩm không còn bán trên cửa hàng.'];
        }
        $check = inventoryValidatePurchase($conn, $productId, $product['stock_status'], $qty);
        if (!$check['ok']) {
            if (strpos($check['message'], 'Chỉ còn') === 0) {
                return [
                    'ok' => false,
                    'message' => 'Sản phẩm "' . $product['name'] . '" — ' . $check['message'],
                ];
            }
            return $check;
        }
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * Trừ tồn kho khi đặt hàng thành công (gọi trong transaction).
 * SP trạng thái in_stock: trừ kho; hết kho → preorder + cảnh báo admin.
 *
 * @return array{alerts: array<int, array<string, mixed>>, any_deducted: bool}
 */
function inventoryDeductForOrder(mysqli $conn, array $cartItems, int $orderId, string $orderCode): array
{
    inventoryEnsureAlertsTable($conn);

    $need = inventoryAggregateCartQty($cartItems);
    $createdAlerts = [];
    $anyDeducted = false;

    $stmtLock = $conn->prepare('SELECT ii.id, ii.quantity_on_hand, p.stock_status, p.name
                                FROM inventory_items ii
                                INNER JOIN products p ON p.id = ii.product_id
                                WHERE ii.product_id = ?
                                FOR UPDATE');
    $stmtUpdateInv = $conn->prepare('UPDATE inventory_items SET quantity_on_hand = ?, updated_at = NOW() WHERE id = ?');
    $stmtUpdateProduct = $conn->prepare("UPDATE products SET stock_status = 'preorder', updated_at = NOW() WHERE id = ?");
    $stmtAlert = $conn->prepare("INSERT INTO inventory_alerts (product_id, order_id, message, alert_type)
                                 VALUES (?, ?, ?, 'stock_depleted')");
    $stmtMarkLine = $conn->prepare('UPDATE order_items SET stock_deducted = 1 WHERE order_id = ? AND product_id = ?');

    if (!$stmtLock || !$stmtUpdateInv || !$stmtUpdateProduct || !$stmtAlert || !$stmtMarkLine) {
        throw new RuntimeException('Không thể cập nhật tồn kho.');
    }

    foreach ($need as $productId => $qty) {
        $stmtLock->bind_param('i', $productId);
        $stmtLock->execute();
        $row = $stmtLock->get_result()->fetch_assoc();
        if (!$row) {
            $stmtLock->free_result();
            require_once __DIR__ . '/product-repository.php';
            $product = productGetById($conn, $productId);
            $productName = $product['name'] ?? ('#' . $productId);
            throw new RuntimeException('Sản phẩm "' . $productName . '" chưa được thiết lập tồn kho.');
        }
        $stmtLock->free_result();

        $stockStatus = (string) $row['stock_status'];
        if ($stockStatus === 'preorder') {
            continue;
        }
        if ($stockStatus === 'out_of_stock') {
            throw new RuntimeException('Sản phẩm "' . $row['name'] . '" đã hết hàng.');
        }

        $onHand = (int) $row['quantity_on_hand'];
        if ($onHand < $qty) {
            throw new RuntimeException('Sản phẩm "' . $row['name'] . '" không đủ tồn kho.');
        }

        $newQty = $onHand - $qty;
        $invId = (int) $row['id'];
        $stmtUpdateInv->bind_param('ii', $newQty, $invId);
        $stmtUpdateInv->execute();
        $anyDeducted = true;

        $stmtMarkLine->bind_param('ii', $orderId, $productId);
        $stmtMarkLine->execute();

        if ($newQty <= 0) {
            $stmtUpdateProduct->bind_param('i', $productId);
            $stmtUpdateProduct->execute();

            $message = 'Sản phẩm "' . $row['name'] . '" đã hết tồn kho sau đơn ' . $orderCode
                . '. Hệ thống đã chuyển sang trạng thái Đặt trước — vui lòng nhập hàng hoặc cập nhật tồn.';
            $stmtAlert->bind_param('iis', $productId, $orderId, $message);
            $stmtAlert->execute();
            $createdAlerts[] = [
                'product_id' => $productId,
                'product_name' => $row['name'],
                'message' => $message,
            ];
        }
    }

    $stmtLock->close();
    $stmtUpdateInv->close();
    $stmtUpdateProduct->close();
    $stmtAlert->close();
    $stmtMarkLine->close();

    return ['alerts' => $createdAlerts, 'any_deducted' => $anyDeducted];
}

/**
 * Hoàn tồn kho khi hủy/trả đơn (chỉ dòng đã trừ kho, một lần duy nhất).
 */
function inventoryRestockForOrder(mysqli $conn, int $orderId): bool
{
    require_once __DIR__ . '/order-repository.php';
    orderEnsureSchema($conn);

    $stmtOrder = $conn->prepare('SELECT inventory_deducted, inventory_restocked FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmtOrder) {
        return false;
    }
    $stmtOrder->bind_param('i', $orderId);
    $stmtOrder->execute();
    $orderRow = $stmtOrder->get_result()->fetch_assoc();
    $stmtOrder->close();
    if (!$orderRow || (int) $orderRow['inventory_deducted'] !== 1 || (int) $orderRow['inventory_restocked'] === 1) {
        return false;
    }

    $stmtLines = $conn->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ? AND stock_deducted = 1 AND product_id IS NOT NULL');
    if (!$stmtLines) {
        return false;
    }
    $stmtLines->bind_param('i', $orderId);
    $stmtLines->execute();
    $linesResult = $stmtLines->get_result();
    $lines = [];
    while ($row = $linesResult->fetch_assoc()) {
        $lines[] = $row;
    }
    $stmtLines->close();
    if ($lines === []) {
        return false;
    }

    $stmtLock = $conn->prepare('SELECT id, quantity_on_hand FROM inventory_items
                                WHERE product_id = ?
                                FOR UPDATE');
    $stmtUpdateInv = $conn->prepare('UPDATE inventory_items SET quantity_on_hand = ?, updated_at = NOW() WHERE id = ?');
    $stmtRestoreProduct = $conn->prepare("UPDATE products SET stock_status = 'in_stock', updated_at = NOW()
                                          WHERE id = ? AND stock_status = 'preorder'");
    $stmtClearLine = $conn->prepare('UPDATE order_items SET stock_deducted = 0 WHERE order_id = ? AND product_id = ?');
    $stmtMarkRestocked = $conn->prepare('UPDATE orders SET inventory_restocked = 1, updated_at = NOW() WHERE id = ?');

    if (!$stmtLock || !$stmtUpdateInv || !$stmtRestoreProduct || !$stmtClearLine || !$stmtMarkRestocked) {
        return false;
    }

    foreach ($lines as $line) {
        $productId = (int) $line['product_id'];
        $qty = (int) $line['quantity'];
        $stmtLock->bind_param('i', $productId);
        $stmtLock->execute();
        $inv = $stmtLock->get_result()->fetch_assoc();
        $stmtLock->free_result();
        if (!$inv) {
            continue;
        }
        $newQty = (int) $inv['quantity_on_hand'] + $qty;
        $invId = (int) $inv['id'];
        $stmtUpdateInv->bind_param('ii', $newQty, $invId);
        $stmtUpdateInv->execute();
        $stmtRestoreProduct->bind_param('i', $productId);
        $stmtRestoreProduct->execute();
        $stmtClearLine->bind_param('ii', $orderId, $productId);
        $stmtClearLine->execute();
    }

    $stmtMarkRestocked->bind_param('i', $orderId);
    $stmtMarkRestocked->execute();

    $stmtLock->close();
    $stmtUpdateInv->close();
    $stmtRestoreProduct->close();
    $stmtClearLine->close();
    $stmtMarkRestocked->close();

    return true;
}

function inventoryGetUnreadAlerts(mysqli $conn, int $limit = 20): array
{
    inventoryEnsureAlertsTable($conn);
    $stmt = $conn->prepare("SELECT a.id, a.product_id, a.order_id, a.message, a.created_at,
                                   p.name AS product_name, p.slug AS product_slug,
                                   o.order_code
                            FROM inventory_alerts a
                            JOIN products p ON p.id = a.product_id
                            LEFT JOIN orders o ON o.id = a.order_id
                            WHERE a.is_read = 0
                            ORDER BY a.id DESC
                            LIMIT ?");
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

function inventoryCountUnreadAlerts(mysqli $conn): int
{
    inventoryEnsureAlertsTable($conn);
    $result = $conn->query('SELECT COUNT(*) AS c FROM inventory_alerts WHERE is_read = 0');
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (int) ($row['c'] ?? 0);
}

function inventoryMarkAlertRead(mysqli $conn, int $alertId): bool
{
    inventoryEnsureAlertsTable($conn);
    $stmt = $conn->prepare('UPDATE inventory_alerts SET is_read = 1 WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $alertId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function inventoryMarkAllAlertsRead(mysqli $conn): bool
{
    inventoryEnsureAlertsTable($conn);
    return (bool) $conn->query('UPDATE inventory_alerts SET is_read = 1 WHERE is_read = 0');
}

function inventoryProductHasRow(mysqli $conn, int $productId): bool
{
    if ($productId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('SELECT id FROM inventory_items WHERE product_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

/**
 * Cập nhật tồn kho từ form admin.
 */
function inventorySetProductQty(mysqli $conn, int $productId, int $quantity): bool
{
    if ($productId <= 0) {
        return false;
    }
    $quantity = max(0, $quantity);

    $stmt = $conn->prepare('SELECT id FROM inventory_items WHERE product_id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        $stmtUp = $conn->prepare('UPDATE inventory_items SET quantity_on_hand = ?, updated_at = NOW() WHERE id = ?');
        if (!$stmtUp) {
            return false;
        }
        $id = (int) $existing['id'];
        $stmtUp->bind_param('ii', $quantity, $id);
        $ok = $stmtUp->execute();
        $stmtUp->close();
        return $ok;
    }

    $stmtIns = $conn->prepare('INSERT INTO inventory_items (product_id, quantity_on_hand) VALUES (?, ?)');
    if (!$stmtIns) {
        return false;
    }
    $stmtIns->bind_param('ii', $productId, $quantity);
    $ok = $stmtIns->execute();
    $stmtIns->close();
    return $ok;
}

function inventoryGetProductQty(mysqli $conn, int $productId): int
{
    return inventoryGetAvailableQty($conn, $productId);
}
