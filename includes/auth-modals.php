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

$authPanel = 'login';
if (isset($authOpenModal) && in_array((string) $authOpenModal, ['login', 'register', 'forgot'], true)) {
    $authPanel = (string) $authOpenModal;
}
$authPanelTitle = [
    'login' => 'auth-login-title',
    'register' => 'auth-register-title',
    'forgot' => 'auth-forgot-title',
][$authPanel];
$authModalHidden = $authPanel !== 'login' || (isset($authOpenModal) && $authOpenModal === 'login') ? '' : ' hidden';
?>
<div id="auth-modal-scrim" class="auth-modal-scrim"<?php echo $authModalHidden; ?> data-auth-modal-root>
    <div class="auth-modal-backdrop" data-auth-close tabindex="-1" aria-hidden="true"></div>
    <div class="auth-modal-content" role="dialog" aria-modal="true" aria-labelledby="<?php echo e($authPanelTitle); ?>">
        <button type="button" class="auth-modal-close" data-auth-close aria-label="Đóng">
            <span aria-hidden="true">×</span>
        </button>
        <div class="auth-modal-panels">
            <div class="auth-modal-panel" data-auth-panel="login"<?php echo $authPanel === 'login' ? '' : ' hidden'; ?>>
                <?php include __DIR__ . '/auth-login-form.php'; ?>
            </div>
            <div class="auth-modal-panel" data-auth-panel="register"<?php echo $authPanel === 'register' ? '' : ' hidden'; ?>>
                <?php include __DIR__ . '/auth-register-form.php'; ?>
            </div>
            <div class="auth-modal-panel" data-auth-panel="forgot"<?php echo $authPanel === 'forgot' ? '' : ' hidden'; ?>>
                <?php include __DIR__ . '/auth-forgot-form.php'; ?>
            </div>
        </div>
    </div>
</div>
