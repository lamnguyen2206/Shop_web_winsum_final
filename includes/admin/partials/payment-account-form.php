<?php

/** @var array{code:string,account_number:string,account_name:string} $bank */
/** @var string $paymentAccountReturnView */

$paymentAccountReturnView = $paymentAccountReturnView ?? 'admin-payment-account';
?>
<form method="post" action="<?php echo e(app_url($paymentAccountReturnView)); ?>" class="admin-panel checkout-form admin-payment-account-form">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="save_shop_bank">
    <input type="hidden" name="return_view" value="<?php echo e($paymentAccountReturnView); ?>">
    <h2>Tài khoản nhận chuyển khoản</h2>
    <p>Số này hiện khi khách chọn chuyển khoản. Có thể sửa lại bất cứ lúc nào. Đơn đã đặt không đổi số tiền.</p>

    <label for="bank_code">Mã ngân hàng</label>
    <input id="bank_code" name="bank_code" required maxlength="20" value="<?php echo e($bank['code']); ?>" placeholder="MB, VCB, BIDV, TCB">

    <label for="account_number">Số tài khoản</label>
    <input id="account_number" name="account_number" required inputmode="numeric" maxlength="19" value="<?php echo e($bank['account_number']); ?>" placeholder="Chỉ nhập chữ số">

    <label for="account_name">Tên chủ tài khoản</label>
    <input id="account_name" name="account_name" required maxlength="120" value="<?php echo e($bank['account_name']); ?>" placeholder="WINSUM HOME">

    <button type="submit">Lưu tài khoản nhận tiền</button>
</form>
