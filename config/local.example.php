<?php

declare(strict_types=1);

/**
 * Copy this file to config/local.php and adjust for the machine.
 * config/local.php is not committed.
 *
 * Hostinger (hPanel → Databases → MySQL Databases): host thường là
 * "localhost", tên DB và user có tiền tố, ví dụ u123456789_winsum.
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
