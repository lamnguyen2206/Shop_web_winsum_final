<?php
require_once __DIR__ . '/cart-store.php';
require_once __DIR__ . '/order-repository.php';

$checkoutBlockedAdmin = adminCurrent();
$orderPlaced = false;
$orderMessage = '';
$orderCode = '';
$orderTotal = 0;
$orderPaymentCode = '';
$orderPaymentName = '';

if (!empty($_SESSION['checkout_result']) && is_array($_SESSION['checkout_result'])) {
    $checkoutResult = $_SESSION['checkout_result'];
    unset($_SESSION['checkout_result']);
    $orderPlaced = !empty($checkoutResult['placed']);
    $orderMessage = (string) ($checkoutResult['message'] ?? '');
    $orderCode = (string) ($checkoutResult['code'] ?? '');
    $orderTotal = (int) ($checkoutResult['total'] ?? 0);
    $orderPaymentCode = (string) ($checkoutResult['payment_method_code'] ?? '');
    $orderPaymentName = (string) ($checkoutResult['payment_method_name'] ?? '');
}

$checkoutProvince = trim((string) ($_POST['customer_province'] ?? ''));
$checkoutWard = trim((string) ($_POST['customer_ward'] ?? ''));
$checkoutStreet = trim((string) ($_POST['customer_street'] ?? ''));

$shippingMethods = orderGetShippingMethods($conn);
$paymentMethods = orderGetPaymentMethods($conn);
$currentCustomer = customerCurrent($conn);
$customerId = $currentCustomer ? (int) $currentCustomer['id'] : null;

$defaultShippingId = !empty($shippingMethods) ? (int) $shippingMethods[0]['id'] : 0;
$defaultPaymentId = !empty($paymentMethods) ? (int) $paymentMethods[0]['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedShippingId = (int) ($_POST['shipping_method_id'] ?? 0);
    $selectedPaymentId = (int) ($_POST['payment_method_id'] ?? 0);
} else {
    $selectedShippingId = (int) ($_SESSION['checkout_shipping_method_id'] ?? $defaultShippingId);
    $selectedPaymentId = (int) ($_SESSION['checkout_payment_method_id'] ?? $defaultPaymentId);
}

if ($selectedShippingId > 0) {
    orderApplyShippingToSession($shippingMethods, $selectedShippingId);
} elseif ($defaultShippingId > 0) {
    orderApplyShippingToSession($shippingMethods, $defaultShippingId);
    $selectedShippingId = $defaultShippingId;
}

if ($selectedPaymentId > 0) {
    $_SESSION['checkout_payment_method_id'] = $selectedPaymentId;
} elseif ($defaultPaymentId > 0) {
    $selectedPaymentId = $defaultPaymentId;
    $_SESSION['checkout_payment_method_id'] = $defaultPaymentId;
}

cartSyncPricesFromDb($conn);
$cartItems = cartGetItems();
$totals = cartCalculateTotals($cartItems, $conn, $customerId);
$cartIsEmpty = $cartItems === [];

$checkoutCanSubmit = !$checkoutBlockedAdmin
    && !$cartIsEmpty
    && !$orderPlaced
    && !empty($shippingMethods)
    && !empty($paymentMethods);
?>

<section class="container checkout-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <a href="<?php echo e(app_url('cart')); ?>">Giỏ hàng</a> / <span>Thanh toán</span></p>
    <h1>Thông tin thanh toán</h1>

    <?php if ($checkoutBlockedAdmin): ?>
        <p class="checkout-notice error">Tài khoản quản trị không thể đặt hàng qua website. Vui lòng đăng xuất quản trị nếu bạn muốn mua với tư cách khách.</p>
    <?php endif; ?>

    <?php if ($orderMessage !== ''): ?>
        <p class="checkout-notice <?php echo $orderPlaced ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($orderMessage); ?></p>
    <?php endif; ?>

    <?php if ($orderPlaced): ?>
        <?php
        $registerPhone = trim((string) ($_SESSION['last_order_phone'] ?? ''));
        $registerParams = $registerPhone !== '' ? ['phone' => $registerPhone] : [];
        $showVietQrDemo = $orderCode !== ''
            && $orderTotal > 0
            && in_array($orderPaymentCode, ['bank_transfer', 'vietqr'], true);
        $vietQrBankCode = 'MB';
        $vietQrAccountNumber = '0123456789';
        $vietQrAccountName = 'WINSUM HOME';
        $vietQrTransferContent = $orderCode;
        $vietQrImageUrl = 'https://img.vietqr.io/image/'
            . rawurlencode($vietQrBankCode)
            . '-'
            . rawurlencode($vietQrAccountNumber)
            . '-compact2.png?'
            . http_build_query([
                'amount' => $orderTotal,
                'addInfo' => $vietQrTransferContent,
                'accountName' => $vietQrAccountName,
            ]);
        ?>
        <div class="checkout-success-box">
            <p>Cảm ơn bạn! Đơn hàng đã được ghi nhận.</p>
            <?php if ($showVietQrDemo): ?>
                <div class="vietqr-demo-box">
                    <h2>Thanh toán VietQR</h2>
                    <p>Quét mã QR để chuyển khoản, sau đó bấm “Tôi đã thanh toán” để hoàn tất demo.</p>
                    <img src="<?php echo e($vietQrImageUrl); ?>" alt="Mã QR thanh toán VietQR cho đơn <?php echo e($orderCode); ?>">
                    <dl>
                        <div>
                            <dt>Ngân hàng</dt>
                            <dd><?php echo e($vietQrBankCode); ?></dd>
                        </div>
                        <div>
                            <dt>Số tài khoản</dt>
                            <dd><?php echo e($vietQrAccountNumber); ?></dd>
                        </div>
                        <div>
                            <dt>Chủ tài khoản</dt>
                            <dd><?php echo e($vietQrAccountName); ?></dd>
                        </div>
                        <div>
                            <dt>Số tiền</dt>
                            <dd><?php echo number_format($orderTotal, 0, ',', '.'); ?>đ</dd>
                        </div>
                        <div>
                            <dt>Nội dung</dt>
                            <dd><?php echo e($vietQrTransferContent); ?></dd>
                        </div>
                    </dl>
                    <form method="post" action="<?php echo e(app_url('checkout')); ?>" class="vietqr-demo-form">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="confirm_vietqr_demo">
                        <input type="hidden" name="order_code" value="<?php echo e($orderCode); ?>">
                        <button type="submit">Tôi đã thanh toán</button>
                    </form>
                    <small>Chức năng này dùng cho demo đồ án. Bản thực tế cần webhook ngân hàng để xác nhận tự động.</small>
                </div>
            <?php elseif ($orderPaymentName !== ''): ?>
                <p>Phương thức thanh toán: <?php echo e($orderPaymentName); ?></p>
            <?php endif; ?>
            <?php if ($currentCustomer): ?>
                <a class="btn-secondary" href="<?php echo e(app_url('orders')); ?>">Xem đơn hàng của tôi</a>
            <?php else: ?>
                <p class="checkout-guest-register-hint">Đăng ký để gắn các đơn trước đó (cùng SĐT) vào tài khoản.</p>
                <?php if ($orderCode !== '' && $registerPhone !== ''): ?>
                    <a class="btn-secondary" href="<?php echo e(app_url('order-lookup', ['code' => $orderCode, 'phone' => $registerPhone])); ?>">Tra cứu đơn vừa đặt</a>
                <?php endif; ?>
                <a class="btn-secondary" href="<?php echo e(auth_register_url('account', $registerParams)); ?>">Đăng ký tài khoản</a>
                <a class="read-more" href="<?php echo e(auth_login_url('orders')); ?>">Đã có tài khoản? Đăng nhập</a>
            <?php endif; ?>
            <a class="read-more" href="<?php echo e(app_url('catalog')); ?>">Tiếp tục mua sắm</a>
        </div>
    <?php elseif ($cartIsEmpty): ?>
        <div class="checkout-empty">
            <p>Giỏ hàng của bạn đang trống.</p>
            <a class="btn-secondary" href="<?php echo e(app_url('catalog')); ?>">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
    <div class="checkout-layout">
        <form method="post" action="<?php echo e(app_url('checkout')); ?>" class="checkout-form" data-checkout-form>
            <?php echo csrfField(); ?>
            <label for="customer_name">Họ và tên</label>
            <input id="customer_name" type="text" name="customer_name" required value="<?php echo htmlspecialchars((string) ($currentCustomer['full_name'] ?? $_POST['customer_name'] ?? '')); ?>">

            <label for="customer_phone">Số điện thoại</label>
            <input id="customer_phone" type="tel" name="customer_phone" required inputmode="numeric" pattern="0[0-9]{9}" maxlength="10" title="Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0" value="<?php echo htmlspecialchars((string) ($currentCustomer['phone'] ?? $_POST['customer_phone'] ?? '')); ?>">

            <label for="customer_email">Email (không bắt buộc)</label>
            <input id="customer_email" type="email" name="customer_email" value="<?php echo htmlspecialchars((string) ($currentCustomer['email'] ?? $_POST['customer_email'] ?? '')); ?>">

            <fieldset class="checkout-address-fields">
                <legend>Địa chỉ nhận hàng</legend>

                <label for="customer_province">Tỉnh/Thành phố</label>
                <input id="customer_province" type="text" name="customer_province" required placeholder="VD: TP. Hồ Chí Minh" value="<?php echo e($checkoutProvince); ?>">

                <label for="customer_ward">Phường/Xã (theo địa chỉ sau sáp nhập)</label>
                <input id="customer_ward" type="text" name="customer_ward" required placeholder="VD: Phường Sài Gòn" value="<?php echo e($checkoutWard); ?>">

                <label for="customer_street">Địa chỉ cụ thể</label>
                <input id="customer_street" type="text" name="customer_street" required placeholder="Số nhà, tên đường, tòa nhà..." value="<?php echo e($checkoutStreet); ?>">
            </fieldset>

            <label for="customer_note">Ghi chú đơn hàng</label>
            <textarea id="customer_note" name="customer_note" rows="3"><?php echo htmlspecialchars((string) ($_POST['customer_note'] ?? '')); ?></textarea>

            <label for="shipping_method_id">Phương thức vận chuyển</label>
            <select id="shipping_method_id" name="shipping_method_id" required data-checkout-shipping>
                <?php if (empty($shippingMethods)): ?>
                    <option value="">Chưa có phương thức vận chuyển</option>
                <?php else: ?>
                    <?php foreach ($shippingMethods as $method): ?>
                        <option
                            value="<?php echo (int) $method['id']; ?>"
                            data-shipping-fee="<?php echo (int) round((float) $method['fee']); ?>"
                            <?php echo ($selectedShippingId === (int) $method['id']) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($method['name']); ?> - <?php echo number_format($method['fee'], 0, ',', '.'); ?>đ (<?php echo htmlspecialchars($method['eta_label']); ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <label for="payment_method_id">Phương thức thanh toán</label>
            <select id="payment_method_id" name="payment_method_id" required>
                <?php if (empty($paymentMethods)): ?>
                    <option value="">Chưa có phương thức thanh toán</option>
                <?php else: ?>
                    <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?php echo (int) $method['id']; ?>" <?php echo ($selectedPaymentId === (int) $method['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($method['name']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <button type="submit" name="checkout_submit" value="1" <?php echo $checkoutCanSubmit ? '' : 'disabled'; ?>>XÁC NHẬN ĐẶT HÀNG</button>
        </form>

        <aside class="cart-summary" data-checkout-summary data-totals-api="api/checkout-totals.php">
            <h2>Đơn hàng của bạn</h2>
            <?php foreach ($cartItems as $item): ?>
                <div class="summary-line">
                    <span><?php echo htmlspecialchars($item['name']); ?> x<?php echo (int) $item['qty']; ?></span>
                    <strong><?php echo number_format($item['price'] * $item['qty'], 0, ',', '.'); ?>đ</strong>
                </div>
            <?php endforeach; ?>
            <div class="summary-line">
                <span>Tạm tính</span>
                <strong data-checkout-subtotal><?php echo number_format($totals['subtotal'], 0, ',', '.'); ?>đ</strong>
            </div>
            <div class="summary-line">
                <span>Phí vận chuyển</span>
                <strong data-checkout-shipping><?php echo number_format($totals['shipping'], 0, ',', '.'); ?>đ</strong>
            </div>
            <div class="summary-line">
                <span>Giảm giá</span>
                <strong data-checkout-discount><?php echo number_format($totals['discount'], 0, ',', '.'); ?>đ</strong>
            </div>
            <?php if ($totals['coupon_code'] !== ''): ?>
                <p class="checkout-coupon-note">Mã đang dùng: <strong><?php echo e($totals['coupon_code']); ?></strong>. <a href="<?php echo e(app_url('cart')); ?>">Đổi mã tại giỏ hàng</a></p>
            <?php else: ?>
                <p class="checkout-coupon-note"><a href="<?php echo e(app_url('cart')); ?>">Áp mã giảm giá tại giỏ hàng</a> (mỗi đơn một mã).</p>
            <?php endif; ?>
            <div class="summary-line total">
                <span>Tổng thanh toán</span>
                <strong data-checkout-total><?php echo number_format($totals['total'], 0, ',', '.'); ?>đ</strong>
            </div>
        </aside>
    </div>
    <?php endif; ?>
</section>
