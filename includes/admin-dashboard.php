<?php
require_once __DIR__ . '/admin-stats.php';
require_once __DIR__ . '/inventory-repository.php';
require_once __DIR__ . '/order-repository.php';

$stats = adminGetDashboardStats($conn);
$recentOrders = adminGetRecentOrders($conn, 6);
$inventoryAlerts = inventoryGetUnreadAlerts($conn, 5);
?>

<section class="container admin-page admin-dashboard-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Bảng điều khiển</span></p>

    <div class="admin-page-head">
        <h1>Bảng quản trị Winsum Home</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <div class="admin-stats-grid">
        <article class="admin-stat-card">
            <span class="admin-stat-label">Tổng đơn hàng</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['orders_total']; ?></strong>
            <small><?php echo (int) $stats['orders_pending']; ?> đơn đang giao</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Doanh thu thuần</span>
            <strong class="admin-stat-value"><?php echo number_format($stats['revenue_net'], 0, ',', '.'); ?>đ</strong>
            <small>Đã giao + đã thanh toán</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Đã thu (mọi trạng thái)</span>
            <strong class="admin-stat-value"><?php echo number_format($stats['revenue_paid'], 0, ',', '.'); ?>đ</strong>
            <small>Đơn paid, trừ hủy/trả</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Hoàn trả / giảm trừ</span>
            <strong class="admin-stat-value"><?php echo number_format($stats['revenue_refunded'], 0, ',', '.'); ?>đ</strong>
            <small><?php echo (int) $stats['returns_pending']; ?> yêu cầu hoàn chờ duyệt</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Sản phẩm</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['products_active']; ?></strong>
            <small>/ <?php echo (int) $stats['products_total']; ?> tổng</small>
        </article>
        <article class="admin-stat-card">
            <span class="admin-stat-label">Khách hàng</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['customers_total']; ?></strong>
            <small><a href="index.php?view=admin-customers">Quản lý khách hàng →</a></small>
        </article>
        <article class="admin-stat-card admin-stat-card--alert">
            <span class="admin-stat-label">Đánh giá chờ duyệt</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['reviews_pending']; ?></strong>
            <small>/ <?php echo (int) $stats['reviews_total']; ?> tổng</small>
        </article>
        <?php if ((int) $stats['inventory_alerts_unread'] > 0): ?>
        <article class="admin-stat-card admin-stat-card--alert">
            <span class="admin-stat-label">Cảnh báo tồn kho</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['inventory_alerts_unread']; ?></strong>
            <small><a href="index.php?view=admin-products">Xem &amp; nhập hàng →</a></small>
        </article>
        <?php endif; ?>
    </div>

    <div class="admin-dashboard-panels">
        <?php if ($inventoryAlerts !== []): ?>
        <div class="admin-panel admin-dashboard-panel--full admin-inventory-alerts">
            <h2>Cảnh báo hết tồn kho → Đặt trước</h2>
            <ul class="admin-alert-list">
                <?php foreach ($inventoryAlerts as $alert): ?>
                    <li>
                        <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        <a href="index.php?view=admin-products&amp;edit=<?php echo (int) $alert['product_id']; ?>">Chỉnh sửa &amp; nhập tồn</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><a href="index.php?view=admin-products">Quản lý sản phẩm &amp; tồn kho →</a></p>
        </div>
        <?php endif; ?>

        <div class="admin-panel admin-dashboard-panel--full admin-dashboard-recent-orders">
            <div class="admin-dashboard-panel-head">
                <h2>Đơn hàng mới nhất</h2>
                <a class="admin-dashboard-panel-link" href="<?php echo e(app_url('admin-orders')); ?>">Xem tất cả đơn hàng →</a>
            </div>
            <?php if (empty($recentOrders)): ?>
                <p class="empty-state">Chưa có đơn hàng.</p>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách</th>
                                <th>Tổng</th>
                                <th>Trạng thái</th>
                                <th>Ngày</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_code']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?></td>
                                    <td><?php echo htmlspecialchars((string) $order['ordered_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
