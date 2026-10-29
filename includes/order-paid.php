<?php

declare(strict_types=1);

require_once __DIR__ . '/order-repository.php';
require_once __DIR__ . '/payment-webhook.php';

$code = strtoupper(trim((string) ($_GET['code'] ?? $_SESSION['last_order_code'] ?? '')));
$order = null;
if ($code !== '' && paymentWebhookSessionOwnsCode($code)) {
    $order = orderGetOrderDetailByCode($conn, $code);
}

$paid = $order !== null && (string) ($order['payment_status'] ?? '') === 'paid';
if (!$paid) {
    redirect(app_url('checkout'));
}

$registerPhone = trim((string) ($_SESSION['last_order_phone'] ?? ''));
$registerParams = $registerPhone !== '' ? ['phone' => $registerPhone] : [];
?>
<section class="container checkout-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Đặt hàng thành công</span></p>
    <div class="checkout-success-box checkout-paid-box">
        <h1>Thanh toán thành công</h1>
        <p>Đã nhận chuyển khoản cho đơn <strong><?php echo e((string) $order['order_code']); ?></strong>.</p>
        <p>Số tiền: <strong><?php echo number_format((int) round((float) $order['grand_total']), 0, ',', '.'); ?>đ</strong></p>
        <?php if ($currentCustomer): ?>
            <a class="btn-secondary" href="<?php echo e(app_url('orders')); ?>">Xem đơn hàng của tôi</a>
        <?php else: ?>
            <?php if ($registerPhone !== ''): ?>
                <a class="btn-secondary" href="<?php echo e(app_url('order-lookup', ['code' => (string) $order['order_code'], 'phone' => $registerPhone])); ?>">Tra cứu đơn vừa đặt</a>
            <?php endif; ?>
            <a class="btn-secondary" href="<?php echo e(auth_register_url('account', $registerParams)); ?>">Đăng ký tài khoản</a>
        <?php endif; ?>
        <a class="read-more" href="<?php echo e(app_url('catalog')); ?>">Tiếp tục mua sắm</a>
    </div>
</section>
