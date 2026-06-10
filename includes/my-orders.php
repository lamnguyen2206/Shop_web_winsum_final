<?php
require_once __DIR__ . '/customer-auth.php';
require_once __DIR__ . '/order-repository.php';

$currentCustomer = customerCurrent($conn);
$orders = [];
$paymentSuccessCode = '';

if ($currentCustomer) {
    $orders = orderGetCustomerOrders($conn, (int) $currentCustomer['id']);
}

if (($_GET['payment'] ?? '') === 'success') {
    $paymentSuccessCode = trim((string) ($_GET['code'] ?? ''));
}
?>

<section class="container orders-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Đơn hàng của tôi</span></p>
    <h1>Đơn hàng của tôi</h1>

    <?php if ($paymentSuccessCode !== ''): ?>
        <p class="checkout-notice success">
            Thanh toán VietQR demo thành công cho đơn #<?php echo e($paymentSuccessCode); ?>.
        </p>
    <?php endif; ?>

    <?php if (!$currentCustomer): ?>
        <div class="empty-state">
            <p>Đăng nhập để xem đơn hàng trong tài khoản.</p>
            <a class="btn-secondary" href="<?php echo e(auth_login_url('orders')); ?>">Đăng nhập</a>
            <a class="btn-secondary" href="<?php echo e(app_url('order-lookup')); ?>">Tra cứu đơn guest</a>
        </div>
    <?php elseif (empty($orders)): ?>
        <div class="empty-state">
            <p>Bạn chưa có đơn hàng nào.</p>
            <a class="btn-secondary" href="<?php echo e(app_url('catalog')); ?>">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="orders-table">
            <div class="orders-head">
                <span>Mã đơn</span>
                <span>Trạng thái</span>
                <span>Thanh toán</span>
                <span>Vận chuyển</span>
                <span>Tổng tiền</span>
                <span>Ngày đặt</span>
                <span>Thao tác</span>
            </div>
            <?php foreach ($orders as $order): ?>
                <article class="orders-row">
                    <span>#<?php echo htmlspecialchars($order['order_code']); ?></span>
                    <span><?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?></span>
                    <span><?php echo htmlspecialchars(orderPaymentStatusLabel((string) $order['payment_status'])); ?></span>
                    <span><?php echo htmlspecialchars(orderFulfillmentStatusLabel((string) $order['fulfillment_status'])); ?></span>
                    <span><?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ</span>
                    <span><?php echo htmlspecialchars((string) $order['ordered_at']); ?></span>
                    <span><a class="btn-secondary order-link" href="<?php echo e(app_url('order-detail', ['code' => (string) $order['order_code']])); ?>">Xem chi tiết</a></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
