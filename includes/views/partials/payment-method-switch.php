<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/order-repository.php';

$paymentSwitchOrder = is_array($paymentSwitchOrder ?? null) ? $paymentSwitchOrder : [];
$paymentSwitchPostUrl = (string) ($paymentSwitchPostUrl ?? '');
$paymentSwitchMode = (string) ($paymentSwitchMode ?? 'account');
$paymentSwitchPhone = (string) ($paymentSwitchPhone ?? '');

if ($paymentSwitchOrder !== [] && $paymentSwitchPostUrl !== '' && orderCanCustomerChangePayment($paymentSwitchOrder)):
$paymentSwitchMethods = orderGetPaymentMethods($conn);
$paymentSwitchCurrentId = (int) ($paymentSwitchOrder['payment']['payment_method_id'] ?? 0);
?>
<form method="post" action="<?php echo e($paymentSwitchPostUrl); ?>" class="payment-switch-form">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="<?php echo $paymentSwitchMode === 'guest' ? 'change_guest_payment' : 'change_payment_method'; ?>">
    <input type="hidden" name="order_code" value="<?php echo e((string) $paymentSwitchOrder['order_code']); ?>">
    <?php if ($paymentSwitchMode === 'guest'): ?>
        <input type="hidden" name="customer_phone" value="<?php echo e($paymentSwitchPhone); ?>">
    <?php endif; ?>
    <label for="payment_method_id_switch">Đổi phương thức thanh toán</label>
    <select id="payment_method_id_switch" name="payment_method_id" required>
        <?php foreach ($paymentSwitchMethods as $method): ?>
            <option value="<?php echo (int) $method['id']; ?>" <?php echo $paymentSwitchCurrentId === (int) $method['id'] ? 'selected' : ''; ?>>
                <?php echo e((string) $method['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-secondary">Cập nhật thanh toán</button>
    <p class="payment-switch-note">Chỉ đổi được khi đơn còn chờ xử lý và chưa thanh toán.</p>
</form>
<?php endif; ?>
