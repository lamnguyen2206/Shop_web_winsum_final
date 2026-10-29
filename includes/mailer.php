<?php

declare(strict_types=1);

function mailConfig(): array
{
    $config = function_exists('appConfig') ? appConfig() : [];
    $mail = is_array($config['mail'] ?? null) ? $config['mail'] : [];

    return [
        'transport' => (string) ($mail['transport'] ?? 'log'),
        'from' => (string) ($mail['from'] ?? 'Winsum Home <noreply@winsumhome.vn>'),
        'smtp_host' => (string) ($mail['smtp_host'] ?? ''),
        'smtp_port' => (int) ($mail['smtp_port'] ?? 587),
        'smtp_user' => (string) ($mail['smtp_user'] ?? ''),
        'smtp_pass' => (string) ($mail['smtp_pass'] ?? ''),
    ];
}

function mailWriteLog(string $to, string $subject, string $body): void
{
    $dir = dirname(__DIR__) . '/storage/mail';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = $dir . '/order-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt';
    $contents = "To: {$to}\nSubject: {$subject}\n\n{$body}\n";
    file_put_contents($file, $contents);
}

function mailSend(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    mailWriteLog($to, $subject, $body);

    $config = mailConfig();
    if ($config['transport'] !== 'smtp' || $config['smtp_host'] === '') {
        return true;
    }

    $headers = 'From: ' . $config['from'] . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
    return @mail($to, $subject, $body, $headers);
}

function mailSendOrderConfirmation(mysqli $conn, string $orderCode): void
{
    require_once __DIR__ . '/order-repository.php';
    $order = orderGetOrderDetailByCode($conn, $orderCode);
    if (!$order) {
        return;
    }
    $email = trim((string) ($order['customer_email'] ?? ''));
    if ($email === '') {
        return;
    }

    $lines = [];
    foreach ($order['items'] as $item) {
        $lines[] = '- ' . $item['product_name'] . ' x ' . (int) $item['quantity']
            . ' = ' . number_format((float) $item['line_total'], 0, ',', '.') . 'đ';
    }
    $body = "Xin chào {$order['customer_name']},\n\n"
        . "Winsum Home đã nhận đơn {$order['order_code']}.\n"
        . "Trạng thái: " . orderStatusLabel((string) $order['status']) . ".\n\n"
        . implode("\n", $lines) . "\n\n"
        . 'Tổng tiền: ' . number_format((float) $order['grand_total'], 0, ',', '.') . "đ\n"
        . "Đơn chờ quản trị xác nhận trước khi giao.\n";

    mailSend($email, 'Xac nhan don hang ' . $order['order_code'], $body);
}
