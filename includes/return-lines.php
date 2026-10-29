<?php

declare(strict_types=1);

/**
 * @return array<int, int> order_item_id => returned qty on non-rejected requests
 */
function returnReturnedQtyMap(mysqli $conn, int $orderId): array
{
    $map = [];
    $stmt = $conn->prepare("SELECT i.order_item_id, SUM(i.quantity) AS qty
                            FROM order_return_items i
                            INNER JOIN order_return_requests r ON r.id = i.return_request_id
                            WHERE r.order_id = ?
                              AND r.status <> 'rejected'
                            GROUP BY i.order_item_id");
    if (!$stmt) {
        return $map;
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $map[(int) $row['order_item_id']] = (int) $row['qty'];
    }
    $stmt->close();

    return $map;
}

function returnOrderHasLegacyFullRequest(mysqli $conn, int $orderId): bool
{
    $stmt = $conn->prepare("SELECT r.id
                            FROM order_return_requests r
                            LEFT JOIN order_return_items i ON i.return_request_id = r.id
                            WHERE r.order_id = ?
                              AND r.status <> 'rejected'
                            GROUP BY r.id
                            HAVING COUNT(i.id) = 0
                            LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $found;
}

/**
 * @return list<array{id:int,product_id:int,product_name:string,product_sku:string,unit_price:float,quantity:int,returned:int,remaining:int}>
 */
function returnListReturnableLines(mysqli $conn, int $orderId): array
{
    $returned = returnReturnedQtyMap($conn, $orderId);
    $legacy = returnOrderHasLegacyFullRequest($conn, $orderId);
    $stmt = $conn->prepare('SELECT id, product_id, product_name, product_sku, unit_price, quantity
                            FROM order_items
                            WHERE order_id = ?
                            ORDER BY id ASC');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lines = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['id'];
        $qty = (int) $row['quantity'];
        $already = $legacy ? $qty : (int) ($returned[$id] ?? 0);
        $lines[] = [
            'id' => $id,
            'product_id' => (int) ($row['product_id'] ?? 0),
            'product_name' => (string) $row['product_name'],
            'product_sku' => (string) $row['product_sku'],
            'unit_price' => (float) $row['unit_price'],
            'quantity' => $qty,
            'returned' => $already,
            'remaining' => max(0, $qty - $already),
        ];
    }
    $stmt->close();

    return $lines;
}

function returnOrderHasOpenRequest(mysqli $conn, int $orderId): bool
{
    $stmt = $conn->prepare("SELECT id FROM order_return_requests
                            WHERE order_id = ?
                              AND status IN ('pending', 'accepted', 'goods_received')
                            LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $found = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $found;
}

function returnOrderHasReturnableQuantity(mysqli $conn, int $orderId): bool
{
    foreach (returnListReturnableLines($conn, $orderId) as $line) {
        if ($line['remaining'] > 0) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int|string, mixed> $postedQty
 * @param list<int|string> $selectedIds
 * @return array{ok:bool,message:string,lines:list<array{order_item_id:int,product_id:int,quantity:int,unit_price:float}>}
 */
function returnNormalizeRequestedLines(mysqli $conn, int $orderId, array $postedQty, array $selectedIds): array
{
    $selected = [];
    foreach ($selectedIds as $id) {
        $selected[(int) $id] = true;
    }
    if ($selected === []) {
        return ['ok' => false, 'message' => 'Vui lòng chọn ít nhất một sản phẩm để hoàn.', 'lines' => []];
    }

    $available = [];
    foreach (returnListReturnableLines($conn, $orderId) as $line) {
        $available[$line['id']] = $line;
    }

    $lines = [];
    foreach (array_keys($selected) as $itemId) {
        if (!isset($available[$itemId]) || $available[$itemId]['remaining'] < 1) {
            return ['ok' => false, 'message' => 'Một dòng hàng không còn số lượng để hoàn.', 'lines' => []];
        }
        $qty = (int) ($postedQty[$itemId] ?? $postedQty[(string) $itemId] ?? 0);
        if ($qty < 1 || $qty > $available[$itemId]['remaining']) {
            return ['ok' => false, 'message' => 'Số lượng hoàn không hợp lệ với sản phẩm còn lại.', 'lines' => []];
        }
        $lines[] = [
            'order_item_id' => $itemId,
            'product_id' => $available[$itemId]['product_id'],
            'quantity' => $qty,
            'unit_price' => $available[$itemId]['unit_price'],
        ];
    }

    return ['ok' => true, 'message' => '', 'lines' => $lines];
}

/**
 * @param list<array{order_item_id:int,product_id:int,quantity:int,unit_price:float}> $lines
 */
function returnInsertLines(mysqli $conn, int $requestId, array $lines): void
{
    $stmt = $conn->prepare('INSERT INTO order_return_items (return_request_id, order_item_id, quantity) VALUES (?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Không lưu được dòng hoàn hàng.');
    }
    foreach ($lines as $line) {
        $itemId = (int) $line['order_item_id'];
        $qty = (int) $line['quantity'];
        $stmt->bind_param('iii', $requestId, $itemId, $qty);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Không lưu được dòng hoàn hàng.');
        }
    }
    $stmt->close();
}

/**
 * @return list<array{order_item_id:int,product_id:int,quantity:int,unit_price:float}>
 */
function returnLinesForRequest(mysqli $conn, int $requestId): array
{
    $stmt = $conn->prepare('SELECT i.order_item_id, i.quantity, oi.product_id, oi.unit_price
                            FROM order_return_items i
                            INNER JOIN order_items oi ON oi.id = i.order_item_id
                            WHERE i.return_request_id = ?');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $lines = [];
    while ($row = $result->fetch_assoc()) {
        $lines[] = [
            'order_item_id' => (int) $row['order_item_id'],
            'product_id' => (int) ($row['product_id'] ?? 0),
            'quantity' => (int) $row['quantity'],
            'unit_price' => (float) $row['unit_price'],
        ];
    }
    $stmt->close();

    return $lines;
}

function returnOrderIsFullyReturned(mysqli $conn, int $orderId): bool
{
    if (returnOrderHasLegacyFullRequest($conn, $orderId)) {
        $stmt = $conn->prepare("SELECT id FROM order_return_requests
                                WHERE order_id = ? AND status = 'completed' LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $done = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($done) {
            return true;
        }
    }

    $lines = returnListReturnableLines($conn, $orderId);

    return $lines !== [] && !returnOrderHasReturnableQuantity($conn, $orderId);
}

function returnRestockLines(mysqli $conn, int $orderId, array $lines): void
{
    $stmtLine = $conn->prepare('SELECT quantity, stock_deducted, product_id FROM order_items WHERE id = ? AND order_id = ? LIMIT 1 FOR UPDATE');
    $stmtInv = $conn->prepare('SELECT id, quantity_on_hand FROM inventory_items WHERE product_id = ? LIMIT 1 FOR UPDATE');
    $stmtUpdate = $conn->prepare('UPDATE inventory_items SET quantity_on_hand = ?, updated_at = NOW() WHERE id = ?');
    $stmtClear = $conn->prepare('UPDATE order_items SET stock_deducted = 0 WHERE id = ?');
    $stmtRestore = $conn->prepare("UPDATE products SET stock_status = 'in_stock', updated_at = NOW() WHERE id = ? AND stock_status = 'preorder'");
    if (!$stmtLine || !$stmtInv || !$stmtUpdate || !$stmtClear || !$stmtRestore) {
        throw new RuntimeException('Không hoàn được tồn kho.');
    }

    foreach ($lines as $line) {
        $itemId = (int) $line['order_item_id'];
        $qty = (int) $line['quantity'];
        $stmtLine->bind_param('ii', $itemId, $orderId);
        $stmtLine->execute();
        $item = $stmtLine->get_result()->fetch_assoc();
        $stmtLine->free_result();
        if (!$item || (int) $item['stock_deducted'] !== 1) {
            continue;
        }
        $productId = (int) $item['product_id'];
        if ($productId <= 0) {
            continue;
        }
        $stmtInv->bind_param('i', $productId);
        $stmtInv->execute();
        $inv = $stmtInv->get_result()->fetch_assoc();
        $stmtInv->free_result();
        if (!$inv) {
            continue;
        }
        $newQty = (int) $inv['quantity_on_hand'] + $qty;
        $invId = (int) $inv['id'];
        $stmtUpdate->bind_param('ii', $newQty, $invId);
        $stmtUpdate->execute();
        $stmtRestore->bind_param('i', $productId);
        $stmtRestore->execute();

        $returned = returnReturnedQtyMap($conn, $orderId);
        if (($returned[$itemId] ?? 0) >= (int) $item['quantity']) {
            $stmtClear->bind_param('i', $itemId);
            $stmtClear->execute();
        }
    }

    $stmtLine->close();
    $stmtInv->close();
    $stmtUpdate->close();
    $stmtClear->close();
    $stmtRestore->close();
}

function returnRefundAmount(mysqli $conn, int $orderId, array $lines, bool $fullOrder): float
{
    $amount = 0.0;
    foreach ($lines as $line) {
        $amount += (float) $line['unit_price'] * (int) $line['quantity'];
    }
    if ($fullOrder) {
        $stmt = $conn->prepare('SELECT shipping_fee FROM orders WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $amount += (float) ($row['shipping_fee'] ?? 0);
        }
    }

    return $amount;
}
