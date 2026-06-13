<?php
require_once __DIR__ . '/order-repository.php';

$adminMessage = '';
$adminMessageOk = null;
$searchQ = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
$customerFilter = null;
if ($customerId > 0) {
    require_once __DIR__ . '/customer-admin-repository.php';
    $customerFilter = customerAdminGetById($conn, $customerId);
    if (!$customerFilter) {
        $customerId = 0;
    }
}
$orders = orderGetAllOrders($conn, 100, $searchQ, $customerId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        $adminMessage = 'Phiên làm việc không hợp lệ.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'update_status') {
            $orderId = (int) ($_POST['order_id'] ?? 0);
            $newStatus = trim((string) ($_POST['status'] ?? ''));
            if (orderUpdateStatus($conn, $orderId, $newStatus, 'admin')) {
                $adminMessage = 'Đã cập nhật trạng thái đơn hàng.';
                $adminMessageOk = true;
            } else {
                $adminMessage = 'Không thể cập nhật trạng thái đơn hàng.';
                $adminMessageOk = false;
            }
            $orders = orderGetAllOrders($conn, 100, $searchQ, $customerId);
        }
    }
}

$statusOptions = ['shipped', 'delivered', 'cancelled'];
?>

<section class="container orders-page admin-page admin-orders-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản trị đơn hàng</span></p>
    <div class="admin-page-head">
        <h1><?php echo $customerId > 0 ? 'Đơn hàng của khách hàng' : 'Quản lý đơn hàng'; ?></h1>
        <?php if ($customerFilter): ?>
            <p class="admin-hint">Đang xem đơn của: <strong><?php echo htmlspecialchars((string) $customerFilter['full_name']); ?></strong> · <?php echo htmlspecialchars((string) $customerFilter['phone']); ?></p>
            <a class="btn-secondary" href="<?php echo e(app_url('admin-customers', ['id' => $customerId])); ?>">← Quay lại khách hàng</a>
            <a class="btn-secondary" href="<?php echo e(app_url('admin-orders')); ?>">Xem tất cả đơn shop</a>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <div class="admin-orders-search">
        <form method="get" action="index.php" class="admin-orders-search-form">
            <input type="hidden" name="view" value="admin-orders">
            <?php if ($customerId > 0): ?>
                <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
            <?php endif; ?>
            <input
                type="search"
                name="q"
                class="admin-orders-search-input"
                value="<?php echo htmlspecialchars($searchQ); ?>"
                placeholder="Tìm theo mã đơn, SĐT hoặc tên khách hàng..."
                autocomplete="off"
            >
            <button type="submit" class="admin-orders-search-btn">Tìm kiếm</button>
        </form>
    </div>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="empty-state">
            <p><?php echo $searchQ !== '' ? 'Không tìm thấy đơn hàng phù hợp với từ khóa tìm kiếm.' : ($customerId > 0 ? 'Khách hàng này chưa có đơn hàng nào.' : 'Chưa có đơn hàng nào trong hệ thống.'); ?></p>
        </div>
    <?php else: ?>
        <div class="admin-panel admin-panel-wide">
        <div class="orders-table admin-orders-table">
            <div class="orders-head">
                <span>Mã đơn</span>
                <span>Khách hàng</span>
                <span>SĐT</span>
                <span>Trạng thái</span>
                <span>Thanh toán</span>
                <span>Tổng tiền</span>
                <span>Ngày đặt</span>
                <span>Chi tiết</span>
                <span>Cập nhật</span>
            </div>
            <?php foreach ($orders as $order): ?>
                <?php
                $isOrderLocked = orderIsLocked($order);
                ?>
                <div class="orders-row admin-order-row">
                    <span>#<?php echo htmlspecialchars($order['order_code']); ?></span>
                    <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    <span><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                    <span class="order-status-text"><?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?></span>
                    <span><?php echo htmlspecialchars(orderPaymentStatusLabel((string) $order['payment_status'])); ?></span>
                    <span><?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ</span>
                    <span><?php echo htmlspecialchars((string) $order['ordered_at']); ?></span>
                    <span>
                        <a class="btn-secondary order-link" href="<?php echo e(app_url('admin-order-detail', ['code' => (string) $order['order_code']])); ?>">Chi tiết</a>
                    </span>
                    <span>
                        <form method="post" action="index.php?view=admin-orders" class="admin-status-form admin-order-status-form">
                            <?php echo csrfField(); ?>
                            <?php if ($customerId > 0): ?>
                                <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                            <?php endif; ?>
                            <?php if ($searchQ !== ''): ?>
                                <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQ); ?>">
                            <?php endif; ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                            <select name="status" class="admin-order-status-select" aria-label="Trạng thái đơn hàng" <?php echo $isOrderLocked ? 'disabled' : ''; ?>>
                                <?php foreach ($statusOptions as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(orderStatusLabel($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="admin-btn-save" <?php echo $isOrderLocked ? 'disabled' : ''; ?>>Lưu</button>
                        </form>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    <?php endif; ?>
</section>
