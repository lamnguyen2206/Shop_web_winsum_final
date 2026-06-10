<?php
require_once __DIR__ . '/product-admin-repository.php';
require_once __DIR__ . '/product-repository.php';
require_once __DIR__ . '/inventory-repository.php';

$adminMessage = '';
$searchQ = trim((string) ($_GET['q'] ?? ''));
$products = productAdminList($conn, 200, $searchQ);
$inventoryAlerts = inventoryGetUnreadAlerts($conn, 10);

if (isset($_GET['msg'])) {
    $adminMessage = (string) $_GET['msg'];
}
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản trị sản phẩm</span></p>

    <div class="admin-page-head">
        <h1>Quản lý sản phẩm</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <div class="admin-panel admin-panel-wide">
        <div class="admin-products-list-head">
            <h2>Danh sách sản phẩm</h2>
            <a class="btn-secondary" href="<?php echo e(app_url('admin-product-create')); ?>">+ Thêm sản phẩm</a>
        </div>
            <?php if ($inventoryAlerts !== []): ?>
            <div class="admin-inventory-alerts">
                <h2>Cảnh báo tồn kho (<?php echo count($inventoryAlerts); ?>)</h2>
                <p class="admin-hint">Sản phẩm hết kho sau đơn hàng — đã chuyển sang <strong>Đặt trước</strong>. Nhập thêm tồn kho hoặc xử lý đơn đặt trước.</p>
                <ul class="admin-alert-list">
                    <?php foreach ($inventoryAlerts as $alert): ?>
                        <li>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                            <div class="admin-alert-actions">
                                <a href="<?php echo e(app_url('admin-product-edit', ['id' => (int) $alert['product_id']])); ?>">Sửa SP</a>
                                <form method="post" class="admin-inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="mark_inventory_read">
                                    <input type="hidden" name="alert_id" value="<?php echo (int) $alert['id']; ?>">
                                    <button type="submit" class="link-muted">Đã xử lý</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" class="admin-inline-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="mark_all_inventory_read">
                    <button type="submit" class="btn-secondary">Đánh dấu tất cả đã xử lý</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="admin-products-list-head">
                <h2>Quản lý danh sách sản phẩm</h2>
                <div class="admin-orders-search admin-products-list-search">
                    <form method="get" action="index.php" class="admin-orders-search-form">
                        <input type="hidden" name="view" value="admin-products">
                        <input
                            type="search"
                            name="q"
                            class="admin-orders-search-input"
                            value="<?php echo htmlspecialchars($searchQ); ?>"
                            placeholder="Tìm theo tên đèn, SKU, slug hoặc danh mục..."
                            autocomplete="off"
                        >
                        <button type="submit" class="admin-orders-search-btn">Tìm kiếm</button>
                        <?php if ($searchQ !== ''): ?>
                            <a class="admin-products-search-clear" href="<?php echo e(app_url('admin-products')); ?>">Xóa lọc</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php if ($searchQ !== '' && empty($products)): ?>
                <p class="admin-hint">Không tìm thấy sản phẩm phù hợp với “<?php echo htmlspecialchars($searchQ); ?>”.</p>
            <?php endif; ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tên</th>
                            <th>SKU</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th>Đã bán</th>
                            <th>Tồn</th>
                            <th>TT kho</th>
                            <th>TT</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                                    <?php if (!(int) $p['is_active']): ?><em class="badge-muted">Ẩn</em><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['sku']); ?></td>
                                <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($p['price_label']); ?></td>
                                <td><?php echo (int) ($p['units_sold'] ?? 0); ?></td>
                                <td><?php echo (int) ($p['stock_quantity'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars(productStockStatusLabel($p['stock_status'])); ?></td>
                                <td><?php echo (int) $p['is_featured'] ? '★' : '—'; ?></td>
                                <td class="admin-actions-cell">
                                    <a href="index.php?view=product&amp;slug=<?php echo urlencode($p['slug']); ?>">Xem</a>
                                    <a href="<?php echo e(app_url('admin-product-edit', ['id' => (int) $p['id'], 'from' => 'admin-products'])); ?>">Sửa chi tiết</a>
                                    <form method="post" action="index.php?view=admin-products" class="admin-inline-form" onsubmit="return confirm('Ẩn sản phẩm này?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete_product">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $p['id']; ?>">
                                        <button type="submit" class="link-danger">Ẩn</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    </div>
</section>
