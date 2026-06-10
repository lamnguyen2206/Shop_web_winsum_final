<?php

declare(strict_types=1);

/**
 * Form đăng nhập khách (dùng trong modal hoặc trang).
 *
 * @var array<string, string> $authPrefill
 */
require_once __DIR__ . '/csrf.php';

$loginIdentifier = htmlspecialchars($authPrefill['identifier'] ?? '');
?>

<div class="auth-modal-card auth-card">
    <h2 id="auth-login-title">Đăng nhập</h2>
    <form method="post" action="" class="auth-form">
        <?php echo csrfField(); ?>
        <input type="hidden" name="auth_action" value="login">
        <div class="auth-field">
            <input type="text" name="identifier" placeholder="SĐT / Email" required value="<?php echo $loginIdentifier; ?>" autocomplete="username" id="auth-login-identifier">
        </div>
        <div class="auth-field auth-field--toggle">
            <input type="password" name="password" placeholder="Nhập mật khẩu" required autocomplete="current-password">
            <button type="button" class="auth-toggle-pw" data-toggle-password aria-label="Hiện mật khẩu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
        </div>
        <button type="submit" class="auth-submit">Đăng nhập</button>
    </form>
    <p class="auth-footer">Chưa có tài khoản? <button type="button" class="auth-inline-link" data-auth-switch="register">Đăng ký</button></p>
</div>
