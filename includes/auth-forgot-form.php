<?php

declare(strict_types=1);

/**
 * Form quên mật khẩu (dùng trong modal).
 *
 * @var array<string, string> $authPrefill
 */
require_once __DIR__ . '/csrf.php';

$forgotIdentifier = htmlspecialchars($authPrefill['identifier'] ?? '', ENT_QUOTES, 'UTF-8');
$newPassword = (string) ($authPrefill['new_password'] ?? '');
?>

<div class="auth-modal-card auth-card">
    <h2 id="auth-forgot-title">Quên mật khẩu</h2>
    <p class="auth-hint auth-hint--block">Nhập số điện thoại hoặc email đã đăng ký. Mật khẩu mới hiện ngay trên trang này.</p>
    <?php if ($newPassword !== ''): ?>
        <div class="auth-reset-box" role="status">
            <p>Mật khẩu mới của bạn</p>
            <strong><?php echo htmlspecialchars($newPassword, ENT_QUOTES, 'UTF-8'); ?></strong>
            <p>Hãy chép mật khẩu này. Nó chỉ hiện một lần. Sau khi đăng nhập, có thể đổi lại trong Tài khoản.</p>
        </div>
    <?php endif; ?>
    <form method="post" action="" class="auth-form">
        <?php echo csrfField(); ?>
        <input type="hidden" name="auth_action" value="forgot">
        <div class="auth-field">
            <input type="text" name="identifier" id="auth-forgot-identifier" placeholder="SĐT / Email" required value="<?php echo $forgotIdentifier; ?>" autocomplete="username">
        </div>
        <button type="submit" class="auth-submit">Đặt mật khẩu mới</button>
    </form>
    <p class="auth-footer">
        <button type="button" class="auth-inline-link" data-auth-switch="login">Quay lại đăng nhập</button>
    </p>
</div>
