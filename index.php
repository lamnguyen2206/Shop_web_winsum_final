<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap/app.php';

include __DIR__ . '/includes/layout/head.php';
include __DIR__ . '/includes/header.php';
?>
    <main>
        <?php if ($authMessage !== '' && !$authSuccess && $view !== 'home'): ?>
            <div class="container auth-page-flash" role="status">
                <p class="auth-notice auth-notice--err"><?php echo e($authMessage); ?></p>
            </div>
        <?php endif; ?>
        <?php include __DIR__ . '/includes/back-button.php'; ?>
        <?php
        if ($is404) {
            include __DIR__ . '/includes/errors/404.php';
        } else {
            include __DIR__ . '/' . ltrim($includeFile, '/');
        }
        ?>
    </main>
<?php
include __DIR__ . '/includes/layout/foot.php';
