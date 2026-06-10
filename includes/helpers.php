<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url, int $status = 303): void
{
    header('Location: ' . $url, true, $status);
    exit;
}

function app_url(string $view, array $params = []): string
{
    $params['view'] = $view;
    return 'index.php?' . http_build_query($params);
}

/** URL mở form đăng nhập (modal) trên trang đích sau khi tải. */
function auth_login_url(string $returnView = 'home', array $params = []): string
{
    $params['auth'] = 'login';
    return app_url($returnView, $params);
}

/** URL mở form đăng ký (modal) trên trang đích sau khi tải. */
function auth_register_url(string $returnView = 'home', array $params = []): string
{
    $params['auth'] = 'register';
    return app_url($returnView, $params);
}

/** Chuẩn hóa SĐT để so khớp đơn hàng / mã giảm giá. */
function phoneNormalize(string $phone): string
{
    return preg_replace('/\D+/', '', trim($phone)) ?? '';
}

function phoneIsValidVietnamMobile(string $phone): bool
{
    return preg_match('/^0\d{9}$/', phoneNormalize($phone)) === 1;
}
