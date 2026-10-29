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

    <?php
    $paymentAccountReturnView = 'admin-payment-account';
    include __DIR__ . '/admin/partials/payment-account-form.php';
    ?>
</section>
