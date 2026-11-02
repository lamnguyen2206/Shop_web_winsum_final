    <?php if ($storefrontGuest) {
        include __DIR__ . '/../auth-modals.php';
    } ?>

    <?php include __DIR__ . '/../footer.php'; ?>

    <?php
    $toastText = isset($authMessage) ? (string) $authMessage : '';
    $toastSuccess = !empty($authSuccess);
    if ($toastText === '' && isset($accountMessage) && (string) $accountMessage !== '') {
        $toastText = (string) $accountMessage;
        $toastSuccess = !empty($accountSuccess);
    }
    if ($toastText !== '') {
        $authMessage = $toastText;
        $authSuccess = $toastSuccess;
        include __DIR__ . '/../auth-toast.php';
    }
    ?>

    <script src="assets/js/main.js"></script>
    <?php foreach ($extraScripts as $scriptSrc): ?>
        <script src="<?php echo e($scriptSrc); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
