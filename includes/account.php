<?php
$accountFlash = pageFlashConsume('account');
$accountMessage = $accountFlash['message'];
$accountSuccess = $accountFlash['success'];
$currentCustomer = customerCurrent($conn);
?>

<section class="container account-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Tài khoản</span></p>
    <h1>Tài khoản khách hàng</h1>

    <?php if ($accountMessage !== ''): ?>
        <p class="account-notice <?php echo $accountSuccess ? 'account-notice--ok' : 'account-notice--err'; ?>">
            <?php echo htmlspecialchars($accountMessage); ?>
        </p>
    <?php endif; ?>

    <?php if ($currentCustomer): ?>
        <div class="account-layout account-layout--single">
            <div class="account-card account-card--summary">
                <h2>Xin chào, <?php echo htmlspecialchars($currentCustomer['full_name']); ?></h2>
                <p>Mã khách hàng: <strong><?php echo htmlspecialchars($currentCustomer['customer_code']); ?></strong></p>
                <div class="account-actions">
                    <a class="btn-secondary" href="index.php?view=orders">Đơn hàng của tôi</a>
                    <a class="btn-secondary" href="index.php?view=catalog">Tiếp tục mua sắm</a>
                </div>
            </div>

            <form method="post" action="<?php echo e(app_url('account')); ?>#profile-edit" class="account-form" id="profile-edit">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_profile">
                <h2>Sửa thông tin</h2>
                <p class="account-form-hint">Cập nhật tên, số điện thoại, email. Để trống mật khẩu mới nếu không đổi.</p>

                <label for="profile_full_name">Tên đăng nhập / Họ tên</label>
                <input id="profile_full_name" type="text" name="full_name" required value="<?php echo htmlspecialchars($currentCustomer['full_name']); ?>" autocomplete="name">

                <label for="profile_phone">Số điện thoại</label>
                <input id="profile_phone" type="tel" name="phone" required inputmode="numeric" pattern="0[0-9]{9}" maxlength="10" title="Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0" value="<?php echo htmlspecialchars($currentCustomer['phone']); ?>" autocomplete="tel">

                <label for="profile_email">Email</label>
                <input id="profile_email" type="email" name="email" value="<?php echo htmlspecialchars((string) ($currentCustomer['email'] ?? '')); ?>" autocomplete="email">

                <label for="profile_new_password">Mật khẩu mới (tuỳ chọn)</label>
                <input id="profile_new_password" type="password" name="new_password" minlength="6" autocomplete="new-password" placeholder="Ít nhất 6 ký tự">

                <button type="submit">Lưu thay đổi</button>
            </form>
        </div>
    <?php else: ?>
        <div class="empty-state account-guest">
            <p>Bạn chưa đăng nhập.</p>
            <p class="account-form-hint">Đăng nhập để xem và sửa thông tin tài khoản, theo dõi đơn hàng.</p>
            <div class="account-actions">
                <a class="btn-secondary" href="<?php echo e(auth_login_url('account')); ?>">Đăng nhập</a>
                <a class="btn-secondary" href="<?php echo e(auth_register_url('account')); ?>">Đăng ký</a>
            </div>
        </div>
    <?php endif; ?>
</section>
