    <?php if ($storefrontGuest) {
        include __DIR__ . '/../auth/auth-modals.php';
    } ?>

    <?php include __DIR__ . '/../footer.php'; ?>

    <?php if ($authMessage !== ''): ?>
        <?php include __DIR__ . '/../auth/auth-toast.php'; ?>
    <?php endif; ?>

    <script src="assets/js/main.js"></script>
    <?php foreach ($extraScripts as $scriptSrc): ?>
        <script src="<?php echo e($scriptSrc); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
