<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/order-repository.php';
require_once __DIR__ . '/../includes/payment-webhook.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'message' => 'Webhook VietQR/SePay. Gửi POST JSON khi có tiền vào tài khoản.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Chỉ nhận POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}
if (!is_array($payload)) {
    $payload = [];
}

$result = paymentWebhookApply($conn, $payload, paymentWebhookReadRequestToken());
http_response_code($result['http']);
echo json_encode([
    'success' => $result['ok'],
    'status' => $result['status'],
    'message' => $result['message'],
], JSON_UNESCAPED_UNICODE);
