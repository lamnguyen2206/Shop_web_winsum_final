<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/order-repository.php';
require_once __DIR__ . '/../includes/payment-webhook.php';

$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
if ($code === '' || !paymentWebhookSessionOwnsCode($code)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Không xem được đơn này.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$order = orderGetOrderDetailByCode($conn, $code);
if ($order === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Không tìm thấy đơn.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$paid = (string) ($order['payment_status'] ?? '') === 'paid';
echo json_encode([
    'ok' => true,
    'code' => (string) $order['order_code'],
    'paid' => $paid,
    'payment_status' => (string) ($order['payment_status'] ?? ''),
    'redirect' => $paid ? app_url('order-paid', ['code' => (string) $order['order_code']]) : '',
], JSON_UNESCAPED_UNICODE);
