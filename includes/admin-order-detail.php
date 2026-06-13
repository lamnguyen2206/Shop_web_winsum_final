<?php
require_once __DIR__ . '/order-repository.php';
require_once __DIR__ . '/return-repository.php';

$orderCode = trim((string) ($_GET['code'] ?? ''));
$order = $orderCode !== '' ? orderGetOrderDetailByCode($conn, $orderCode) : null;
$adminMessage = trim((string) ($_GET['msg'] ?? ''));
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;
$paymentOptions = orderPaymentStatusOptions();
$shippingKey = $order ? orderFulfillmentToShippingKey((string) $order['fulfillment_status']) : 'pending';
$orderLocked = $order ? orderIsLocked($order) : false;
?>

<section class="container order-detail-page admin-page admin-order-detail-page">
    <p class="breadcrumb">
        <a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> /
        <a href="<?php echo e(app_url('admin-orders')); ?>">Quản lý đơn hàng</a> /
        <span>Chi tiết đơn hàng</span>
    </p>
    <div class="admin-page-head admin-page-head--toolbar">
        <h1>Chi tiết đơn hàng</h1>
        <a class="btn-secondary" href="<?php echo e(app_url('admin-orders')); ?>">← Quay lại danh sách</a>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <?php if (!$order): ?>
        <div class="empty-state">
            <p>Không tìm thấy đơn hàng với mã bạn yêu cầu.</p>
            <a class="btn-secondary" href="<?php echo e(app_url('admin-orders')); ?>">Quay lại danh sách đơn</a>
        </div>
    <?php else: ?>
        <?php if ($orderLocked): ?>
            <p class="admin-order-lock-badge">[ĐƠN HÀNG ĐÃ HOÀN THÀNH - KHÔNG THỂ SỬA]</p>
        <?php endif; ?>
        <?php $returnRequest = $order['return_request'] ?? null; ?>
        <?php if ($returnRequest): ?>
            <div class="admin-notice">
                <strong>Yêu cầu hoàn hàng:</strong>
                <?php echo htmlspecialchars(returnStatusLabel(returnNormalizeStatus((string) $returnRequest['status']))); ?>
                — <?php echo htmlspecialchars(returnReasonLabel((string) $returnRequest['reason'])); ?>.
                <a href="<?php echo e(app_url('admin-returns', ['status' => (string) $returnRequest['status']])); ?>">Xem tại mục Hoàn hàng</a>
            </div>
        <?php endif; ?>
        <div class="order-detail-grid">
            <article class="order-card">
                <h2>Thông tin đơn #<?php echo htmlspecialchars($order['order_code']); ?></h2>
                <p><strong>Ngày đặt:</strong> <?php echo htmlspecialchars((string) $order['ordered_at']); ?></p>
                <p><strong>Trạng thái đơn:</strong> <?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?></p>
                <p><strong>Thanh toán:</strong> <?php echo htmlspecialchars(orderPaymentStatusLabel((string) $order['payment_status'])); ?></p>
                <p><strong>Giao hàng:</strong> <?php echo htmlspecialchars(orderFulfillmentStatusLabel((string) $order['fulfillment_status'])); ?></p>
                <?php if (!empty($order['inventory_deducted'])): ?>
                    <p class="admin-muted">Đã trừ kho<?php echo !empty($order['inventory_restocked']) ? ' (đã hoàn kho)' : ''; ?>.</p>
                <?php endif; ?>
            </article>

            <article class="order-card">
                <h2>Thông tin nhận hàng</h2>
                <p><strong>Người nhận:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p><strong>SĐT:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                <?php if (!empty($order['customer_email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars((string) $order['customer_email']); ?></p>
                <?php endif; ?>
                <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                <?php if (!empty($order['customer_note'])): ?>
                    <p><strong>Ghi chú:</strong> <?php echo htmlspecialchars((string) $order['customer_note']); ?></p>
                <?php endif; ?>
                <p><strong>Vận chuyển:</strong> <?php echo htmlspecialchars((string) ($order['shipment']['shipping_method_name'] ?? '')); ?></p>
                <p><strong>Thanh toán:</strong> <?php echo htmlspecialchars((string) ($order['payment']['payment_method_name'] ?? '')); ?></p>
            </article>
        </div>

        <div class="admin-order-status-panels">
            <?php if ($orderLocked): ?>
                <p class="admin-notice admin-notice--warning">Đơn hàng đã ở trạng thái cuối nên không thể chuyển lại sang trạng thái đang xử lý hoặc đang giao.</p>
            <?php endif; ?>
            <form method="post" action="<?php echo e(app_url('admin-order-detail', ['code' => $order['order_code']])); ?>" class="admin-status-panel-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_payment_status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                <h3>Trạng thái thanh toán</h3>
                <select name="payment_status" aria-label="Trạng thái thanh toán" <?php echo $orderLocked ? 'disabled' : ''; ?>>
                    <?php foreach ($paymentOptions as $ps): ?>
                        <option value="<?php echo htmlspecialchars($ps); ?>" <?php echo ($order['payment_status'] ?? '') === $ps ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(orderPaymentStatusLabel($ps)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="admin-btn-save" <?php echo $orderLocked ? 'disabled' : ''; ?>>Lưu thanh toán</button>
            </form>

            <form method="post" action="<?php echo e(app_url('admin-order-detail', ['code' => $order['order_code']])); ?>" class="admin-status-panel-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="update_fulfillment_status">
                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                <input type="hidden" name="fulfillment_status" value="delivered">
                <h3>Trạng thái giao hàng</h3>
                <p class="admin-status-current">Hiện tại: <strong><?php echo htmlspecialchars(orderFulfillmentStatusLabel((string) $order['fulfillment_status'])); ?></strong></p>
                <?php
                $canMarkDelivered = !$orderLocked
                    && !in_array($shippingKey, ['delivered'], true)
                    && !in_array((string) $order['status'], ['delivered', 'return_pending', 'return_accepted', 'return_received'], true);
                ?>
                <?php if ($canMarkDelivered): ?>
                    <button type="submit" class="admin-btn-save">Xác nhận đã giao</button>
                <?php else: ?>
                    <button type="button" class="admin-btn-save" disabled>Xác nhận đã giao</button>
                <?php endif; ?>
            </form>
        </div>

        <h2 class="admin-order-items-heading">Sản phẩm trong đơn</h2>
        <?php if (empty($order['items'])): ?>
            <p class="empty-state">Đơn hàng không có sản phẩm nào.</p>
        <?php else: ?>
            <div class="orders-table order-items-table admin-order-items-table">
                <div class="orders-head">
                    <span>Sản phẩm</span>
                    <span>SKU</span>
                    <span>Đơn giá</span>
                    <span>Số lượng</span>
                    <span>Thành tiền</span>
                    <span>Ảnh</span>
                </div>
                <?php foreach ($order['items'] as $item): ?>
                    <article class="orders-row">
                        <span><?php echo htmlspecialchars($item['product_name']); ?></span>
                        <span><?php echo htmlspecialchars($item['product_sku']); ?></span>
                        <span><?php echo number_format((float) $item['unit_price'], 0, ',', '.'); ?>đ</span>
                        <span><?php echo (int) $item['quantity']; ?></span>
                        <span><?php echo number_format((float) $item['line_total'], 0, ',', '.'); ?>đ</span>
                        <span><img class="order-item-img" src="<?php echo htmlspecialchars((string) ($item['product_image'] ?? 'assets/images/blog_1.png')); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>"></span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <aside class="cart-summary order-total-box">
            <h2>Tổng kết thanh toán</h2>
            <div class="summary-line"><span>Tạm tính</span><strong><?php echo number_format((float) $order['subtotal'], 0, ',', '.'); ?>đ</strong></div>
            <div class="summary-line"><span>Phí vận chuyển</span><strong><?php echo number_format((float) $order['shipping_fee'], 0, ',', '.'); ?>đ</strong></div>
            <div class="summary-line"><span>Giảm giá</span><strong><?php echo number_format((float) $order['discount_amount'], 0, ',', '.'); ?>đ</strong></div>
            <div class="summary-line total"><span>Thành tiền</span><strong><?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ</strong></div>
        </aside>
    <?php endif; ?>
</section>
