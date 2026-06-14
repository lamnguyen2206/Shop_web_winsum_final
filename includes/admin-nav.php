<?php
require_once __DIR__ . '/helpers.php';

$adminView = isset($_GET['view']) ? (string) $_GET['view'] : '';
?>
<nav class="admin-nav" aria-label="Menu quản trị">
    <a class="<?php echo $adminView === 'admin-dashboard' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-dashboard')); ?>">Tổng quan</a>
    <a class="<?php echo $adminView === 'admin-orders' || $adminView === 'admin-order-detail' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-orders')); ?>">Quản lý đơn</a>
    <a class="<?php echo $adminView === 'admin-returns' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-returns')); ?>">Hoàn hàng</a>
    <a class="<?php echo $adminView === 'admin-customers' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-customers')); ?>">Khách hàng</a>
    <a class="<?php echo in_array($adminView, ['admin-products', 'admin-product-create', 'admin-product-edit'], true) ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-products')); ?>">Sản phẩm</a>
    <a class="<?php echo in_array($adminView, ['admin-coupons', 'admin-coupon-create', 'admin-coupon-edit'], true) ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-coupons')); ?>">Mã giảm giá</a>
    <a class="<?php echo $adminView === 'admin-reviews' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-reviews')); ?>">Đánh giá</a>
    <a class="<?php echo $adminView === 'admin-blog' || $adminView === 'blog-editor' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-blog')); ?>">Quản lý blog</a>
    <a class="<?php echo $adminView === 'admin-blog-comments' ? 'active' : ''; ?>" href="<?php echo e(app_url('admin-blog-comments')); ?>">Bình luận blog</a>
</nav>
