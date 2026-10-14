<?php

declare(strict_types=1);

/**
 * @return array{bank_code:string,account_number:string,account_name:string}
 */
function vietqrConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = dirname(__DIR__) . '/config/vietqr.php';
    $loaded = is_file($path) ? require $path : [];
    if (!is_array($loaded)) {
        $loaded = [];
    }

    $account = preg_replace('/\s+/', '', (string) ($loaded['account_number'] ?? '')) ?? '';
    $config = [
        'bank_code' => strtoupper(trim((string) ($loaded['bank_code'] ?? ''))),
        'account_number' => $account,
        'account_name' => trim((string) ($loaded['account_name'] ?? '')),
    ];

    return $config;
}

function vietqrIsConfigured(): bool
{
    $config = vietqrConfig();

    return $config['bank_code'] !== ''
        && $config['account_number'] !== ''
        && $config['account_name'] !== '';
}

function vietqrIsTransferMethod(string $code): bool
{
    return in_array($code, ['bank_transfer', 'vietqr'], true);
}

function vietqrImageUrl(string $orderCode, int $amount): ?string
{
    $orderCode = trim($orderCode);
    if (!vietqrIsConfigured() || $orderCode === '' || $amount <= 0) {
        return null;
    }

    $config = vietqrConfig();
    $query = http_build_query([
        'amount' => $amount,
        'addInfo' => $orderCode,
        'accountName' => $config['account_name'],
    ], '', '&', PHP_QUERY_RFC3986);

    return 'https://img.vietqr.io/image/'
        . rawurlencode($config['bank_code'])
        . '-'
        . rawurlencode($config['account_number'])
        . '-compact2.png?'
        . $query;
}

/**
 * @return array{bank_code:string,account_number:string,account_name:string,amount:int,content:string,image_url:string}|null
 */
function vietqrPayload(string $orderCode, int $amount): ?array
{
    $imageUrl = vietqrImageUrl($orderCode, $amount);
    if ($imageUrl === null) {
        return null;
    }

    $config = vietqrConfig();

    return [
        'bank_code' => $config['bank_code'],
        'account_number' => $config['account_number'],
        'account_name' => $config['account_name'],
        'amount' => $amount,
        'content' => trim($orderCode),
        'image_url' => $imageUrl,
    ];
}

function vietqrOrderAwaitingPayment(array $order): bool
{
    $method = (string) ($order['payment']['payment_method_code'] ?? '');
    $paymentStatus = (string) ($order['payment_status'] ?? '');
    $status = (string) ($order['status'] ?? '');

    if (!vietqrIsTransferMethod($method) || $paymentStatus !== 'unpaid') {
        return false;
    }

    return !in_array($status, ['cancelled', 'returned', 'return_pending', 'return_accepted', 'return_received'], true);
}

/**
 * Shop đối soát sao kê rồi xác nhận. Khách không gọi được hàm này.
 *
 * @return array{ok:bool,message:string}
 */
function vietqrConfirmReceived(mysqli $conn, int $orderId): array
{
    require_once __DIR__ . '/order-repository.php';

    if ($orderId <= 0) {
        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];
    }

    $stmt = $conn->prepare("SELECT o.id, o.status, o.payment_status, pm.code AS payment_method_code
                            FROM orders o
                            LEFT JOIN order_payments op ON op.order_id = o.id
                            LEFT JOIN payment_methods pm ON pm.id = op.payment_method_id
                            WHERE o.id = ?
                            ORDER BY op.id DESC
                            LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'message' => 'Không thể đọc đơn hàng.'];
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];
    }
    if (!vietqrIsTransferMethod((string) ($order['payment_method_code'] ?? ''))) {
        return ['ok' => false, 'message' => 'Đơn này không thanh toán bằng VietQR.'];
    }
    if ((string) ($order['payment_status'] ?? '') === 'paid') {
        return ['ok' => false, 'message' => 'Đơn đã được ghi nhận thanh toán.'];
    }
    if ((string) ($order['payment_status'] ?? '') !== 'unpaid') {
        return ['ok' => false, 'message' => 'Đơn không còn ở trạng thái chờ chuyển khoản.'];
    }
    if (!vietqrOrderAwaitingPayment($order)) {
        return ['ok' => false, 'message' => 'Đơn đã hủy hoặc đang hoàn hàng nên không thể xác nhận thu tiền.'];
    }

    $updated = orderUpdatePaymentStatus($conn, $orderId, 'paid', 'vietqr_reconcile');
    if (!$updated) {
        return ['ok' => false, 'message' => 'Không thể ghi nhận thanh toán. Vui lòng thử lại.'];
    }

    return ['ok' => true, 'message' => 'Đã xác nhận nhận chuyển khoản VietQR.'];
}
