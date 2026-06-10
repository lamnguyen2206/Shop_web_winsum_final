<?php
require_once __DIR__ . '/order-repository.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/customer-auth.php';

$currentCustomer = customerCurrent($conn);
if ($currentCustomer) {
    redirect(app_url('orders'));
}

$lookupCode = trim((string) ($_GET['code'] ?? ''));
$lookupPhone = trim((string) ($_GET['phone'] ?? ''));
$lookupNotice = trim((string) ($_GET['msg'] ?? ''));
$lookupSuccess = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
$paymentSuccess = (string) ($_GET['payment'] ?? '') === 'success';
$lookupOrder = null;
$lookupError = '';

if ($lookupCode !== '' || $lookupPhone !== '') {
    if ($lookupCode === '' || $lookupPhone === '') {
        $lookupError = 'Vui lòng nhập đủ mã đơn hàng và số điện thoại.';
    } elseif (!phoneIsValidVietnamMobile($lookupPhone)) {
        $lookupError = 'Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0.';
    } else {
        $lookupPhone = phoneNormalize($lookupPhone);
        $lookupOrder = orderGetOrderDetailByCodeAndPhone($conn, $lookupCode, $lookupPhone);
        if (!$lookupOrder) {
            $lookupError = 'Không tìm thấy đơn hàng khớp với mã đơn và số điện thoại.';
        }
    }
}
?>

<section class="container order-detail-page order-lookup-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Tra cứu đơn hàng</span></p>
    <h1>Tra cứu đơn hàng</h1>

    <?php if ($paymentSuccess && $lookupCode !== ''): ?>
        <p class="checkout-notice success">Thanh toán VietQR demo thành công cho đơn #<?php echo e($lookupCode); ?>.</p>
    <?php endif; ?>
    <?php if ($lookupNotice !== ''): ?>
        <p class="checkout-notice <?php echo $lookupSuccess ? 'success' : 'error'; ?>"><?php echo e($lookupNotice); ?></p>
    <?php endif; ?>
    <?php if ($lookupError !== ''): ?>
        <p class="checkout-notice error"><?php echo e($lookupError); ?></p>
    <?php endif; ?>

    <form method="get" action="index.php" class="account-form order-lookup-form">
        <input type="hidden" name="view" value="order-lookup">
        <h2>Nhập thông tin đơn hàng</h2>
        <p class="account-form-hint">Dành cho khách vãng lai chưa đăng nhập. Mã đơn có dạng WS...</p>

        <label for="lookup_code">Mã đơn hàng</label>
        <input id="lookup_code" type="text" name="code" required value="<?php echo e($lookupCode); ?>" placeholder="VD: WS05261234">

        <label for="lookup_phone">Số điện thoại đặt hàng</label>
        <input id="lookup_phone" type="tel" name="phone" required inputmode="numeric" pattern="0[0-9]{9}" maxlength="10" title="Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0" value="<?php echo e($lookupPhone); ?>" placeholder="VD: 0901234567">

        <button type="submit">Tra cứu đơn</button>
    </form>

    <?php if ($lookupOrder): ?>
        <?php $canCancel = orderCanCustomerCancel($lookupOrder); ?>
        <div class="order-detail-grid">
            <article class="order-card">
                <h2>Thông tin đơn #<?php echo e((string) $lookupOrder['order_code']); ?></h2>
                <p><strong>Ngày đặt:</strong> <?php echo e((string) $lookupOrder['ordered_at']); ?></p>
                <p><strong>Trạng thái:</strong> <?php echo e(orderStatusLabel((string) $lookupOrder['status'])); ?></p>
                <p><strong>Thanh toán:</strong> <?php echo e(orderPaymentStatusLabel((string) ($lookupOrder['payment_status'] ?? ''))); ?></p>
                <p><strong>Vận chuyển:</strong> <?php echo e(orderFulfillmentStatusLabel((string) $lookupOrder['fulfillment_status'])); ?></p>
            </article>

            <article class="order-card">
                <h2>Thông tin nhận hàng</h2>
                <p><strong>Người nhận:</strong> <?php echo e((string) $lookupOrder['customer_name']); ?></p>
                <p><strong>SĐT:</strong> <?php echo e((string) $lookupOrder['customer_phone']); ?></p>
                <p><strong>Địa chỉ:</strong> <?php echo e((string) $lookupOrder['customer_address']); ?></p>
                <p><strong>Vận chuyển:</strong> <?php echo e((string) ($lookupOrder['shipment']['shipping_method_name'] ?? '')); ?></p>
                <p><strong>Thanh toán:</strong> <?php echo e((string) ($lookupOrder['payment']['payment_method_name'] ?? '')); ?></p>
            </article>
        </div>

        <div class="orders-table order-items-table">
            <div class="orders-head">
                <span>Sản phẩm</span>
                <span>SKU</span>
                <span>Đơn giá</span>
                <span>Số lượng</span>
                <span>Thành tiền</span>
                <span>Ảnh</span>
            </div>
            <?php foreach ($lookupOrder['items'] as $item): ?>
                <article class="orders-row">
                    <span><?php echo e((string) $item['product_name']); ?></span>
                    <span><?php echo e((string) $item['product_sku']); ?></span>
                    <span><?php echo number_format((float) $item['unit_price'], 0, ',', '.'); ?>đ</span>
                    <span><?php echo (int) $item['quantity']; ?></span>
                    <span><?php echo number_format((float) $item['line_total'], 0, ',', '.'); ?>đ</span>
                    <span><img class="order-item-img" src="<?php echo e((string) ($item['product_image'] ?? 'assets/images/blog_1.png')); ?>" alt="<?php echo e((string) $item['product_name']); ?>"></span>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary order-total-box">
            <h2>Tổng kết thanh toán</h2>
            <div class="summary-line"><span>Tạm tính</span><strong><?php echo number_format((float) $lookupOrder['subtotal'], 0, ',', '.'); ?>đ</strong></div>
            <div class="summary-line"><span>Phí vận chuyển</span><strong><?php echo number_format((float) $lookupOrder['shipping_fee'], 0, ',', '.'); ?>đ</strong></div>
            <div class="summary-line"><span>Giảm giá</span><strong><?php echo number_format((float) $lookupOrder['discount_amount'], 0, ',', '.'); ?>đ</strong></div>
            <div class="summary-line total"><span>Thành tiền</span><strong><?php echo number_format((float) $lookupOrder['grand_total'], 0, ',', '.'); ?>đ</strong></div>
        </aside>

        <?php if ($canCancel): ?>
            <form method="post" action="<?php echo e(app_url('order-lookup')); ?>" class="order-cancel-form" onsubmit="return confirm('Bạn chắc chắn muốn hủy đơn hàng này?');">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="cancel_guest_order">
                <input type="hidden" name="order_code" value="<?php echo e((string) $lookupOrder['order_code']); ?>">
                <input type="hidden" name="customer_phone" value="<?php echo e($lookupPhone); ?>">
                <button type="submit" class="btn-secondary btn-cancel-order">Hủy đơn hàng</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
