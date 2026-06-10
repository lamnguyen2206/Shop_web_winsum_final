<?php

declare(strict_types=1);

/**
 * Form đăng ký khách (dùng trong modal hoặc trang).
 *
 * @var array<string, string> $authPrefill
 */
require_once __DIR__ . '/csrf.php';

$regPhone = htmlspecialchars($authPrefill['phone'] ?? '');
$regName = htmlspecialchars($authPrefill['full_name'] ?? '');
$regEmail = htmlspecialchars($authPrefill['email'] ?? '');
?>

<div class="auth-modal-card auth-card">
    <h2 id="auth-register-title">Đăng ký tài khoản</h2>
    <form method="post" action="" class="auth-form">
        <?php echo csrfField(); ?>
        <input type="hidden" name="auth_action" value="register">
        <div class="auth-field">
            <input type="tel" name="phone" placeholder="Số điện thoại" required inputmode="numeric" pattern="0[0-9]{9}" maxlength="10" title="Số điện thoại phải có đúng 10 số và bắt đầu bằng số 0" value="<?php echo $regPhone; ?>" autocomplete="tel">
        </div>
        <div class="auth-field">
            <input type="text" name="full_name" placeholder="Tên đăng nhập" required value="<?php echo $regName; ?>" autocomplete="name">
        </div>
        <div class="auth-field">
            <input type="email" name="email" placeholder="Email" value="<?php echo $regEmail; ?>" autocomplete="email">
        </div>
        <div class="auth-field auth-field--toggle">
            <input type="password" name="password" placeholder="Nhập mật khẩu" required minlength="6" autocomplete="new-password">
            <button type="button" class="auth-toggle-pw" data-toggle-password aria-label="Hiện mật khẩu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <p class="auth-hint">Mật khẩu bao gồm ít nhất 6 ký tự</p>
        <button type="submit" class="auth-submit">Đăng ký</button>
    </form>
    <p class="auth-footer">Đã có tài khoản? <button type="button" class="auth-inline-link" data-auth-switch="login">Đăng nhập</button></p>
</div>
