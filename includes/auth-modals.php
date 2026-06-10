<?php

declare(strict_types=1);

/**
 * Modal đăng nhập / đăng ký — form nổi trên trang, không lớp phủ mờ.
 * Cần biến: $currentCustomer (?array), $authPrefill (array)
 */
if (!empty($currentCustomer)) {
    return;
}

if (!isset($authPrefill) || !is_array($authPrefill)) {
    $authPrefill = [];
}
?>
<div id="auth-modal-scrim" class="auth-modal-scrim" hidden data-auth-modal-root>
    <div class="auth-modal-backdrop" data-auth-close tabindex="-1" aria-hidden="true"></div>
    <div class="auth-modal-content" role="dialog" aria-modal="true" aria-labelledby="auth-login-title">
        <button type="button" class="auth-modal-close" data-auth-close aria-label="Đóng">
            <span aria-hidden="true">×</span>
        </button>
        <div class="auth-modal-panels">
            <div class="auth-modal-panel" data-auth-panel="login">
                <?php include __DIR__ . '/auth-login-form.php'; ?>
            </div>
            <div class="auth-modal-panel" data-auth-panel="register" hidden>
                <?php include __DIR__ . '/auth-register-form.php'; ?>
            </div>
        </div>
    </div>
</div>
