<?php
require_once __DIR__ . '/customer-auth.php';
require_once __DIR__ . '/order-repository.php';
require_once __DIR__ . '/return-repository.php';
require_once __DIR__ . '/csrf.php';

$currentCustomer = customerCurrent($conn);
$orderCode = trim((string) ($_GET['code'] ?? ''));
$pageNotice = trim((string) ($_GET['msg'] ?? ''));
$pageSuccess = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
$order = null;
$returnCheck = ['ok' => false, 'message' => ''];
$returnDeadline = null;

if ($currentCustomer && $orderCode !== '') {
    $order = orderGetCustomerOrderDetailByCode($conn, (int) $currentCustomer['id'], $orderCode);
    if ($order) {
        $returnCheck = returnCanCustomerRequest($conn, $order, (int) $currentCustomer['id']);
        $returnDeadline = $returnCheck['deadline'] ?? returnGetDeadlineForOrder($conn, (int) $order['id']);
    }
}
?>

<section class="container order-return-page order-detail-page">
    <p class="breadcrumb">
        <a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> /
        <a href="<?php echo e(app_url('orders')); ?>">Đơn hàng của tôi</a> /
        <?php if ($order): ?>
            <a href="<?php echo e(app_url('order-detail', ['code' => $order['order_code']])); ?>">Chi tiết đơn #<?php echo htmlspecialchars($order['order_code']); ?></a> /
        <?php endif; ?>
        <span>Hoàn hàng / Trả tiền</span>
    </p>
    <h1>Yêu cầu hoàn hàng / Trả tiền</h1>

    <?php if (!$currentCustomer): ?>
        <div class="empty-state">
            <p>Bạn cần đăng nhập để gửi yêu cầu hoàn hàng.</p>
            <?php
            $loginParams = $orderCode !== '' ? ['code' => $orderCode] : [];
            ?>
            <a class="btn-secondary" href="<?php echo e(auth_login_url('order-return', $loginParams)); ?>">Đăng nhập</a>
        </div>
    <?php elseif (!$order): ?>
        <div class="empty-state">
            <p>Không tìm thấy đơn hàng với mã bạn yêu cầu.</p>
            <a class="btn-secondary" href="<?php echo e(app_url('orders')); ?>">Quay lại danh sách đơn</a>
        </div>
    <?php elseif (!$returnCheck['ok']): ?>
        <?php if ($pageNotice !== ''): ?>
            <p class="checkout-notice <?php echo $pageSuccess ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($pageNotice); ?></p>
        <?php endif; ?>
        <div class="empty-state">
            <p><?php echo htmlspecialchars((string) $returnCheck['message']); ?></p>
            <a class="btn-secondary" href="<?php echo e(app_url('order-detail', ['code' => $order['order_code']])); ?>">Quay lại chi tiết đơn</a>
        </div>
    <?php else: ?>
        <?php if ($pageNotice !== ''): ?>
            <p class="checkout-notice <?php echo $pageSuccess ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($pageNotice); ?></p>
        <?php endif; ?>

        <p class="order-return-intro">
            Đơn hàng <strong>#<?php echo htmlspecialchars($order['order_code']); ?></strong> đã giao thành công.
            <?php if ($returnDeadline): ?>
                Hạn gửi yêu cầu hoàn hàng đến <strong><?php echo htmlspecialchars(date('d/m/Y', strtotime($returnDeadline))); ?></strong>.
            <?php endif; ?>
            Sau khi gửi, đơn chuyển sang <strong>Chờ Admin duyệt</strong> — hệ thống chưa hoàn tiền hay trừ doanh thu cho đến khi quy trình hoàn tất.
        </p>

        <div class="order-return-layout">
            <form method="post" action="<?php echo e(app_url('order-return', ['code' => $order['order_code']])); ?>" class="checkout-form order-return-form" enctype="multipart/form-data">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="submit_return_request">
                <input type="hidden" name="order_code" value="<?php echo htmlspecialchars($order['order_code']); ?>">

                <label for="return_reason">Lý do hoàn hàng</label>
                <select name="return_reason" id="return_reason" required>
                    <option value="">— Chọn lý do —</option>
                    <?php foreach (returnReasonOptions() as $key => $label): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo (($_POST['return_reason'] ?? '') === $key) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="return_description">Mô tả chi tiết</label>
                <textarea name="return_description" id="return_description" rows="5" required placeholder="Mô tả tình trạng sản phẩm, lý do hoàn hàng..."><?php echo htmlspecialchars((string) ($_POST['return_description'] ?? '')); ?></textarea>

                <label for="return_evidence">Ảnh bằng chứng <span class="order-return-hint">(bắt buộc, tối đa 2MB)</span></label>
                <input type="file" name="evidence" id="return_evidence" accept="image/jpeg,image/png,image/webp" required>

                <fieldset class="checkout-address-fields">
                    <legend>Thông tin nhận hoàn tiền</legend>
                    <label for="bank_account_name">Tên chủ tài khoản</label>
                    <input type="text" name="bank_account_name" id="bank_account_name" required value="<?php echo htmlspecialchars((string) ($_POST['bank_account_name'] ?? ($currentCustomer['full_name'] ?? ''))); ?>">

                    <label for="bank_name">Tên ngân hàng</label>
                    <input type="text" name="bank_name" id="bank_name" required placeholder="VD: Vietcombank" value="<?php echo htmlspecialchars((string) ($_POST['bank_name'] ?? '')); ?>">

                    <label for="bank_account_number">Số tài khoản</label>
                    <input type="text" name="bank_account_number" id="bank_account_number" required inputmode="numeric" value="<?php echo htmlspecialchars((string) ($_POST['bank_account_number'] ?? '')); ?>">
                </fieldset>

                <button type="submit">Gửi yêu cầu hoàn hàng</button>
            </form>

            <aside class="cart-summary order-return-summary">
                <h2>Đơn hàng #<?php echo htmlspecialchars($order['order_code']); ?></h2>
                <div class="summary-line"><span>Ngày đặt</span><strong><?php echo htmlspecialchars((string) $order['ordered_at']); ?></strong></div>
                <div class="summary-line"><span>Trạng thái</span><strong><?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?></strong></div>
                <div class="summary-line"><span>Số sản phẩm</span><strong><?php echo count($order['items']); ?></strong></div>
                <div class="summary-line total"><span>Thành tiền</span><strong><?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ</strong></div>
                <a class="btn-secondary order-return-back" href="<?php echo e(app_url('order-detail', ['code' => $order['order_code']])); ?>">Quay lại chi tiết đơn</a>
            </aside>
        </div>
    <?php endif; ?>
</section>
