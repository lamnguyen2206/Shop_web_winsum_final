<?php

declare(strict_types=1);

/**
 * Form quên mật khẩu (dùng trong modal).
 *
 * @var array<string, string> $authPrefill
 */
require_once __DIR__ . '/csrf.php';

$forgotIdentifier = htmlspecialchars($authPrefill['identifier'] ?? '', ENT_QUOTES, 'UTF-8');
$returnView = (isset($view) && is_string($view) && $view !== '') ? $view : 'home';
?>

<div class="auth-modal-card auth-card">
    <h2 id="auth-forgot-title">Quên mật khẩu</h2>
    <p class="auth-hint auth-hint--block">Nhập số điện thoại hoặc email đã đăng ký. Mật khẩu mới được gửi tới email của tài khoản, không hiện trên trang này.</p>
    <form method="post" action="" class="auth-form">
        <?php echo csrfField(); ?>
        <input type="hidden" name="auth_action" value="forgot">
        <div class="auth-field">
            <input type="text" name="identifier" id="auth-forgot-identifier" placeholder="SĐT / Email" required value="<?php echo $forgotIdentifier; ?>" autocomplete="username">
        </div>
        <button type="submit" class="auth-submit">Đặt mật khẩu mới</button>
    </form>
    <p class="auth-footer">
        <a class="auth-inline-link" href="<?php echo e(auth_login_url($returnView)); ?>" data-auth-switch="login">Quay lại đăng nhập</a>
    </p>
</div>
