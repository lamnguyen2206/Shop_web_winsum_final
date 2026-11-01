<?php

declare(strict_types=1);

/**
 * Máy XAMPP: copy thành config/local.dev.php nếu root có mật khẩu.
 * Hostinger: copy thành config/local.php. File local.php chứa user dạng
 * u123456789_... sẽ không được dùng khi mở http://localhost.
 * Cả hai file đều không commit.
 */
return [
    'app' => [
        // true chỉ khi debug ở máy local. Trên hosting để false để không lộ lỗi PHP/SQL.
        'debug' => false,
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'winsumwebfinal',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        // Dùng để tạo tài khoản admin lần đầu nếu DB chưa có user role = admin.
        // Trên hosting phải đổi mật khẩu mạnh trước khi mở web.
        'name' => 'admin',
        'phone' => '0901000000',
        'email' => 'admin@winsumhome.vn',
        'password' => 'admin123',
    ],
    'mail' => [
        // 'log' ghi thư vào storage/mail/. Đổi thành 'smtp' và điền thông tin bên dưới để gửi thật.
        'transport' => 'log',
        'from' => 'Winsum Home <noreply@winsumhome.vn>',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
    ],
    'bank' => [
        // Giá trị dự phòng; admin có thể sửa trên web (Bảng quản trị → Tài khoản nhận tiền).
        'code' => 'MB',
        'account_number' => '',
        'account_name' => 'WINSUM HOME',
    ],
    'payment_webhook' => [
        // Token API Key trên SePay (Webhooks). Để trống = tắt webhook.
        'provider' => 'sepay',
        'token' => '',
    ],
];
