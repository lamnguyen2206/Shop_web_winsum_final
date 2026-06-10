<?php
$showBackButton = isset($view) && $view !== 'home' && empty($is404);
?>
<?php if ($showBackButton): ?>
    <div class="container page-back-wrap">
        <a
            class="page-back-link"
            href="<?php echo e(app_url('home')); ?>"
            onclick="if (window.history.length > 1 && document.referrer) { window.history.back(); return false; }"
        >← Quay lại trang trước</a>
    </div>
<?php endif; ?>
