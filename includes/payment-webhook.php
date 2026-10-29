<?php

declare(strict_types=1);

/**
 * @return array{token: string, provider: string}
 */
function paymentWebhookConfig(): array
{
    $config = function_exists('appConfig') ? appConfig() : [];
    $webhook = is_array($config['payment_webhook'] ?? null) ? $config['payment_webhook'] : [];

    return [
        'token' => trim((string) ($webhook['token'] ?? '')),
        'provider' => trim((string) ($webhook['provider'] ?? 'sepay')) ?: 'sepay',
    ];
}

function paymentWebhookReadRequestToken(): string
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^(?:Apikey|Api-Key|Bearer)\s+(\S+)/i', $auth, $matches) === 1) {
        return $matches[1];
    }
    if ($auth !== '' && !str_contains($auth, ' ')) {
        return $auth;
    }
    $headerKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($headerKey !== '') {
        return $headerKey;
    }

    return trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
}

function paymentWebhookTokenValid(string $provided, string $expected): bool
{
    return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
}

/**
 * @return list<string>
 */
function paymentWebhookExtractOrderCodes(string $text): array
{
    if (preg_match_all('/\bWS[0-9]{8}\b/i', $text, $matches) < 1) {
        return [];
    }
    $codes = [];
    foreach ($matches[0] as $code) {
        $codes[strtoupper((string) $code)] = strtoupper((string) $code);
    }

    return array_values($codes);
}

/**
 * @param array<string, mixed> $payload
 * @return array{transfer_type: string, amount: int, content: string, event_id: string, codes: list<string>}
 */
function paymentWebhookNormalizePayload(array $payload): array
{
    if (isset($payload['data']) && is_array($payload['data'])) {
        $data = $payload['data'];
        if (array_is_list($data) && isset($data[0]) && is_array($data[0])) {
            $payload = array_merge($payload, $data[0]);
        } elseif (!array_is_list($data)) {
            $payload = array_merge($payload, $data);
        }
    }

    $content = trim(implode(' ', array_filter([
        (string) ($payload['content'] ?? ''),
        (string) ($payload['description'] ?? ''),
        (string) ($payload['desc'] ?? ''),
        (string) ($payload['code'] ?? ''),
        (string) ($payload['orderCode'] ?? ''),
        (string) ($payload['order_code'] ?? ''),
    ], static fn(string $part): bool => $part !== '')));

    $amount = (int) round((float) ($payload['transferAmount']
        ?? $payload['transfer_amount']
        ?? $payload['amount']
        ?? 0));

    return [
        'transfer_type' => strtolower((string) ($payload['transferType'] ?? $payload['transfer_type'] ?? 'in')),
        'amount' => $amount,
        'content' => $content,
        'event_id' => substr((string) ($payload['id'] ?? $payload['referenceCode'] ?? $payload['tid'] ?? ''), 0, 80),
        'codes' => paymentWebhookExtractOrderCodes($content),
    ];
}

function paymentWebhookEnsureTable(mysqli $conn): void
{
    $conn->query(
        'CREATE TABLE IF NOT EXISTS payment_webhook_events (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            provider VARCHAR(32) NOT NULL,
            provider_event_id VARCHAR(80) NOT NULL,
            order_id INT NULL,
            status VARCHAR(24) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_payment_webhook_event (provider, provider_event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * @param array<string, mixed> $payload
 * @return array{ok: bool, http: int, status: string, message: string, order_code: string}
 */
function paymentWebhookApply(mysqli $conn, array $payload, string $providedToken, ?string $expectedToken = null): array
{
    $config = paymentWebhookConfig();
    $expected = $expectedToken ?? $config['token'];
    $provider = $config['provider'];

    if ($expected === '') {
        return [
            'ok' => false,
            'http' => 503,
            'status' => 'disabled',
            'message' => 'Chưa cấu hình token webhook.',
            'order_code' => '',
        ];
    }
    if (!paymentWebhookTokenValid($providedToken, $expected)) {
        return [
            'ok' => false,
            'http' => 401,
            'status' => 'unauthorized',
            'message' => 'Token webhook không hợp lệ.',
            'order_code' => '',
        ];
    }

    $normalized = paymentWebhookNormalizePayload($payload);
    if ($normalized['transfer_type'] !== 'in') {
        return [
            'ok' => true,
            'http' => 200,
            'status' => 'ignored',
            'message' => 'Bỏ qua giao dịch không phải tiền vào.',
            'order_code' => '',
        ];
    }
    if ($normalized['amount'] <= 0 || $normalized['codes'] === []) {
        return [
            'ok' => false,
            'http' => 422,
            'status' => 'unmatched',
            'message' => 'Không tìm thấy mã đơn và số tiền hợp lệ trong nội dung chuyển khoản.',
            'order_code' => '',
        ];
    }

    paymentWebhookEnsureTable($conn);

    if ($normalized['event_id'] !== '') {
        $seen = $conn->prepare(
            'SELECT status, order_id FROM payment_webhook_events WHERE provider = ? AND provider_event_id = ? LIMIT 1'
        );
        if ($seen) {
            $seen->bind_param('ss', $provider, $normalized['event_id']);
            $seen->execute();
            $existing = $seen->get_result()->fetch_assoc();
            $seen->close();
            if ($existing) {
                return [
                    'ok' => true,
                    'http' => 200,
                    'status' => 'duplicate',
                    'message' => 'Giao dịch đã xử lý.',
                    'order_code' => '',
                ];
            }
        }
    }

    $matched = null;
    foreach ($normalized['codes'] as $code) {
        $order = orderGetOrderDetailByCode($conn, $code);
        if ($order === null) {
            continue;
        }
        $method = (string) ($order['payment']['payment_method_code'] ?? '');
        if (!in_array($method, ['bank_transfer', 'vietqr'], true)) {
            continue;
        }
        if (in_array((string) ($order['status'] ?? ''), ['cancelled', 'returned'], true)) {
            continue;
        }
        $expectedAmount = (int) round((float) ($order['grand_total'] ?? 0));
        if ($expectedAmount !== $normalized['amount']) {
            continue;
        }
        if ($matched !== null) {
            return [
                'ok' => false,
                'http' => 409,
                'status' => 'ambiguous',
                'message' => 'Nội dung khớp nhiều đơn, không tự xác nhận.',
                'order_code' => '',
            ];
        }
        $matched = $order;
    }

    if ($matched === null) {
        return [
            'ok' => false,
            'http' => 422,
            'status' => 'unmatched',
            'message' => 'Không khớp đơn chuyển khoản (sai mã, sai số tiền hoặc đơn không phải VietQR).',
            'order_code' => '',
        ];
    }

    $orderId = (int) $matched['id'];
    $orderCode = (string) $matched['order_code'];
    if ((string) ($matched['payment_status'] ?? '') === 'paid') {
        paymentWebhookRemember($conn, $provider, $normalized['event_id'], $orderId, 'already_paid');
        return [
            'ok' => true,
            'http' => 200,
            'status' => 'already_paid',
            'message' => 'Đơn đã thanh toán.',
            'order_code' => $orderCode,
        ];
    }

    if (!orderUpdatePaymentStatus($conn, $orderId, 'paid', 'webhook')) {
        return [
            'ok' => false,
            'http' => 500,
            'status' => 'failed',
            'message' => 'Không cập nhật được trạng thái thanh toán.',
            'order_code' => $orderCode,
        ];
    }

    paymentWebhookRemember($conn, $provider, $normalized['event_id'], $orderId, 'paid');

    return [
        'ok' => true,
        'http' => 200,
        'status' => 'paid',
        'message' => 'Đã xác nhận thanh toán.',
        'order_code' => $orderCode,
    ];
}

function paymentWebhookRemember(mysqli $conn, string $provider, string $eventId, int $orderId, string $status): void
{
    if ($eventId === '') {
        return;
    }
    $stmt = $conn->prepare(
        'INSERT IGNORE INTO payment_webhook_events (provider, provider_event_id, order_id, status) VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('ssis', $provider, $eventId, $orderId, $status);
    $stmt->execute();
    $stmt->close();
}

function paymentWebhookOrderIsWaiting(array $order): bool
{
    $method = (string) ($order['payment']['payment_method_code'] ?? '');
    return in_array($method, ['bank_transfer', 'vietqr'], true)
        && (string) ($order['payment_status'] ?? '') !== 'paid'
        && !in_array((string) ($order['status'] ?? ''), ['cancelled', 'returned'], true);
}

function paymentWebhookSessionOwnsCode(string $orderCode): bool
{
    $sessionCode = (string) ($_SESSION['last_order_code'] ?? '');
    return $orderCode !== '' && $sessionCode !== '' && hash_equals($sessionCode, $orderCode);
}
