<?php

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';

$adminMessage = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;
$bank = shopBankAccount();
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Tài khoản nhận tiền</span></p>

    <div class="admin-page-head">
        <h1>Tài khoản nhận chuyển khoản</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?php echo e(app_url('admin-payment-account')); ?>" class="admin-panel checkout-form">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="save_shop_bank">
        <p>Số này hiện trên trang thanh toán khi khách chọn chuyển khoản. Đơn đã đặt không đổi số tiền.</p>

        <label for="bank_code">Mã ngân hàng</label>
        <input id="bank_code" name="bank_code" required maxlength="20" value="<?php echo e($bank['code']); ?>" placeholder="MB, VCB, BIDV, TCB">

        <label for="account_number">Số tài khoản</label>
        <input id="account_number" name="account_number" required inputmode="numeric" maxlength="19" value="<?php echo e($bank['account_number']); ?>" placeholder="Chỉ nhập chữ số">

        <label for="account_name">Tên chủ tài khoản</label>
        <input id="account_name" name="account_name" required maxlength="120" value="<?php echo e($bank['account_name']); ?>" placeholder="WINSUM HOME">

        <button type="submit">Lưu tài khoản nhận tiền</button>
    </form>
</section>
