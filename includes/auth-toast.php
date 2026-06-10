<?php
/**
 * Toast thông báo đăng nhập / đăng xuất (biến: $authMessage, $authSuccess).
 */
if ($authMessage === '') {
    return;
}
$toastClass = $authSuccess ? 'auth-toast--success' : 'auth-toast--error';
?>
<div
    id="auth-toast"
    class="auth-toast <?php echo $toastClass; ?>"
    role="status"
    aria-live="polite"
    data-autohide="3000"
>
    <span class="auth-toast-icon" aria-hidden="true"><?php echo $authSuccess ? '✓' : '!'; ?></span>
    <p class="auth-toast-text"><?php echo htmlspecialchars($authMessage); ?></p>
    <button type="button" class="auth-toast-close" aria-label="Đóng thông báo">&times;</button>
</div>
