<?php
require_once __DIR__ . '/admin-stats.php';
require_once __DIR__ . '/../inventory-repository.php';
require_once __DIR__ . '/../order-repository.php';
require_once __DIR__ . '/../review-repository.php';

$stats = adminGetDashboardStats($conn);
$customerOrderSearch = trim((string) ($_GET['customer_q'] ?? ''));
$revenueFilter = adminParseRevenueFilter($_GET);
$revenueSummary = adminGetRevenuePeriodSummary($conn, $revenueFilter);
$revenueChart = adminGetRevenueChartData($conn, $revenueFilter);
$recentOrders = adminGetRecentOrders($conn, 6);
$recentReviews = reviewAdminGetRecent($conn, 5);
$inventoryAlerts = inventoryGetUnreadAlerts($conn, 5);
$customerOrders = $customerOrderSearch !== ''
    ? orderGetAllOrders($conn, 25, $customerOrderSearch, 0)
    : [];
?>

<section class="container admin-page admin-dashboard-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Bảng điều khiển</span></p>

    <div class="admin-page-head">
        <h1>Bảng quản trị Winsum Home</h1>
    </div>

    <?php include __DIR__ . '/../admin-nav.php'; ?>

    <?php if (!empty($adminTasksPayload)): ?>
        <?php include __DIR__ . '/partials/task-panel.php'; ?>
    <?php endif; ?>

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
        <article class="admin-stat-card">
            <span class="admin-stat-label">Đánh giá sản phẩm</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['reviews_total']; ?></strong>
            <small><a href="<?php echo e(app_url('admin-reviews')); ?>">Quản lý đánh giá →</a></small>
        </article>
        <?php if ((int) $stats['inventory_alerts_unread'] > 0): ?>
        <article class="admin-stat-card admin-stat-card--alert">
            <span class="admin-stat-label">Cảnh báo tồn kho</span>
            <strong class="admin-stat-value"><?php echo (int) $stats['inventory_alerts_unread']; ?></strong>
            <small><a href="index.php?view=admin-products">Xem &amp; nhập hàng →</a></small>
        </article>
        <?php endif; ?>
    </div>

    <div class="admin-panel admin-dashboard-panel--full admin-revenue-chart-panel">
        <div class="admin-dashboard-panel-head">
            <h2>Biểu đồ doanh thu</h2>
            <span class="admin-revenue-period-label"><?php echo htmlspecialchars($revenueFilter['label']); ?></span>
        </div>

        <form method="get" action="index.php" class="admin-revenue-filter" id="admin-revenue-filter">
            <input type="hidden" name="view" value="admin-dashboard">
            <?php if ($customerOrderSearch !== ''): ?>
                <input type="hidden" name="customer_q" value="<?php echo htmlspecialchars($customerOrderSearch); ?>">
            <?php endif; ?>

            <div class="admin-revenue-filter-row">
                <label class="admin-revenue-filter-field">
                    <span>Loại lọc</span>
                    <select name="revenue_period" id="revenue-period" data-revenue-period>
                        <option value="month" <?php echo $revenueFilter['period'] === 'month' ? 'selected' : ''; ?>>Theo tháng</option>
                        <option value="day" <?php echo $revenueFilter['period'] === 'day' ? 'selected' : ''; ?>>Theo ngày</option>
                        <option value="year" <?php echo $revenueFilter['period'] === 'year' ? 'selected' : ''; ?>>Theo năm</option>
                        <option value="range" <?php echo $revenueFilter['period'] === 'range' ? 'selected' : ''; ?>>Khoảng thời gian</option>
                    </select>
                </label>

                <label class="admin-revenue-filter-field" data-revenue-field="month">
                    <span>Tháng</span>
                    <input type="month" name="revenue_month" value="<?php echo htmlspecialchars($revenueFilter['revenue_month']); ?>">
                </label>

                <label class="admin-revenue-filter-field" data-revenue-field="day">
                    <span>Ngày</span>
                    <input type="date" name="revenue_date" value="<?php echo htmlspecialchars($revenueFilter['revenue_date']); ?>">
                </label>

                <label class="admin-revenue-filter-field" data-revenue-field="year">
                    <span>Năm</span>
                    <input type="number" name="revenue_year" min="2000" max="2100" step="1" value="<?php echo htmlspecialchars($revenueFilter['revenue_year']); ?>">
                </label>

                <label class="admin-revenue-filter-field" data-revenue-field="range-from">
                    <span>Từ ngày</span>
                    <input type="date" name="revenue_from" value="<?php echo htmlspecialchars($revenueFilter['revenue_from']); ?>">
                </label>

                <label class="admin-revenue-filter-field" data-revenue-field="range-to">
                    <span>Đến ngày</span>
                    <input type="date" name="revenue_to" value="<?php echo htmlspecialchars($revenueFilter['revenue_to']); ?>">
                </label>

                <button type="submit" class="admin-orders-search-btn admin-revenue-filter-submit">Áp dụng</button>
            </div>
        </form>

        <div class="admin-revenue-summary">
            <article class="admin-revenue-summary-card">
                <span>Doanh thu thuần</span>
                <strong><?php echo number_format($revenueSummary['net'], 0, ',', '.'); ?>đ</strong>
                <small>Đã giao + đã thanh toán</small>
            </article>
            <article class="admin-revenue-summary-card admin-revenue-summary-card--muted">
                <span>Hoàn trả</span>
                <strong><?php echo number_format($revenueSummary['refunded'], 0, ',', '.'); ?>đ</strong>
                <small>Đơn returned</small>
            </article>
            <article class="admin-revenue-summary-card">
                <span>Số đơn</span>
                <strong><?php echo (int) $revenueSummary['orders']; ?></strong>
                <small>Trong kỳ đã chọn</small>
            </article>
        </div>

        <div class="admin-revenue-chart-wrap">
            <canvas id="admin-revenue-chart" aria-label="Biểu đồ doanh thu theo thời gian"></canvas>
        </div>

        <script type="application/json" id="admin-revenue-chart-data"><?php echo json_encode([
            'labels' => $revenueChart['labels'],
            'net' => $revenueChart['net'],
            'refunded' => $revenueChart['refunded'],
            'periodLabel' => $revenueFilter['label'],
        ], JSON_UNESCAPED_UNICODE); ?></script>
    </div>

    <div class="admin-dashboard-panels">
        <div class="admin-panel admin-dashboard-panel--full admin-dashboard-customer-orders">
            <div class="admin-dashboard-panel-head">
                <h2>Tra cứu đơn theo tên khách</h2>
            </div>
            <form method="get" action="index.php" class="admin-orders-search-form admin-dashboard-customer-search">
                <input type="hidden" name="view" value="admin-dashboard">
                <label class="visually-hidden" for="dashboard-customer-q">Tên khách hàng</label>
                <input
                    type="search"
                    id="dashboard-customer-q"
                    name="customer_q"
                    class="admin-orders-search-input"
                    value="<?php echo htmlspecialchars($customerOrderSearch); ?>"
                    placeholder="Nhập tên khách hàng (vd: Nguyễn Văn A)..."
                    autocomplete="off"
                >
                <button type="submit" class="admin-orders-search-btn">Tìm đơn</button>
                <?php if ($customerOrderSearch !== ''): ?>
                    <a class="btn-secondary admin-dashboard-search-clear" href="<?php echo e(app_url('admin-dashboard')); ?>">Xóa</a>
                    <a class="admin-dashboard-panel-link" href="<?php echo e(app_url('admin-orders', ['q' => $customerOrderSearch])); ?>">Mở trong quản lý đơn →</a>
                <?php endif; ?>
            </form>

            <?php if ($customerOrderSearch === ''): ?>
                <p class="admin-hint admin-dashboard-customer-hint">Gõ tên khách để xem các đơn hàng liên quan (khớp một phần tên, SĐT hoặc mã đơn).</p>
            <?php elseif ($customerOrders === []): ?>
                <p class="empty-state">Không tìm thấy đơn nào cho “<?php echo htmlspecialchars($customerOrderSearch); ?>”.</p>
            <?php else: ?>
                <p class="admin-hint"><?php echo count($customerOrders); ?> đơn phù hợp<?php echo count($customerOrders) >= 25 ? ' (hiển thị tối đa 25)' : ''; ?>.</p>
                <div class="admin-table-wrap">
                    <table class="admin-table admin-dashboard-orders-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách</th>
                                <th>SĐT</th>
                                <th>Tổng</th>
                                <th>Trạng thái</th>
                                <th>Thanh toán</th>
                                <th>Ngày</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customerOrders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars((string) $order['order_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string) $order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars((string) $order['customer_phone']); ?></td>
                                    <td><?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?></td>
                                    <td><?php echo htmlspecialchars(orderPaymentStatusLabel((string) ($order['payment_status'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars((string) $order['ordered_at']); ?></td>
                                    <td>
                                        <a class="admin-link-action" href="<?php echo e(app_url('admin-order-detail', ['code' => (string) $order['order_code']])); ?>">Chi tiết</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

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
                                    <td>
                                        <a class="admin-link-action" href="<?php echo e(app_url('admin-order-detail', ['code' => (string) $order['order_code']])); ?>">
                                            <?php echo htmlspecialchars((string) $order['order_code']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars((string) $order['customer_name']); ?></td>
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

        <div class="admin-panel admin-dashboard-panel--full admin-dashboard-recent-reviews">
            <div class="admin-dashboard-panel-head">
                <h2>Đánh giá sản phẩm mới nhất</h2>
                <a class="admin-dashboard-panel-link" href="<?php echo e(app_url('admin-reviews')); ?>">Quản lý đánh giá →</a>
            </div>
            <?php if (empty($recentReviews)): ?>
                <p class="empty-state">Chưa có đánh giá nào.</p>
            <?php else: ?>
                <div class="review-admin-list review-admin-list--compact">
                    <?php foreach ($recentReviews as $review): ?>
                        <article class="review-admin-card">
                            <div class="review-admin-meta">
                                <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                <?php if ((int) $review['rating'] > 0): ?>
                                    <span class="stars" aria-label="<?php echo (int) $review['rating']; ?> sao"><?php echo str_repeat('★', (int) $review['rating']); ?><?php echo str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="review-product">
                                <a href="<?php echo e(app_url('product', ['slug' => $review['product_slug']])); ?>" target="_blank" rel="noopener">
                                    <?php echo htmlspecialchars($review['product_name']); ?>
                                </a>
                            </p>
                            <p class="admin-dashboard-review-excerpt"><?php echo htmlspecialchars(mb_strimwidth($review['content'], 0, 120, '…')); ?></p>
                            <small><?php echo htmlspecialchars($review['created_label'] !== '' ? $review['created_label'] : (string) $review['created_at']); ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
