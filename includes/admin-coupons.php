<?php

require_once __DIR__ . '/coupon-admin-repository.php';

$adminMessage = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;
$coupons = couponAdminList($conn);
?>

<section class="container admin-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Mã giảm giá</span></p>

    <div class="admin-page-head admin-page-head--toolbar">
        <h1>Quản lý mã giảm giá</h1>
        <a class="btn-secondary" href="<?php echo e(app_url('admin-coupon-create')); ?>">+ Tạo mã mới</a>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <div class="admin-panel admin-panel-wide">
        <?php if ($coupons === []): ?>
            <p class="empty-state">Chưa có mã giảm giá. <a href="<?php echo e(app_url('admin-coupon-create')); ?>">Tạo mã đầu tiên →</a></p>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Mã</th>
                            <th>Tên</th>
                            <th>Loại</th>
                            <th>Ưu đãi</th>
                            <th>Đã dùng</th>
                            <th>Trạng thái</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $coupon): ?>
                            <?php
                            $isActive = (int) ($coupon['is_active'] ?? 0) === 1;
                            $totalLimit = $coupon['total_usage_limit'] !== null ? (int) $coupon['total_usage_limit'] : null;
                            $usesLabel = (string) (int) ($coupon['uses_count'] ?? 0);
                            if ($totalLimit !== null) {
                                $usesLabel .= ' / ' . $totalLimit;
                            }
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars((string) $coupon['code']); ?></code></td>
                                <td><?php echo htmlspecialchars((string) $coupon['name']); ?></td>
                                <td><?php echo htmlspecialchars((string) ($coupon['role_label'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string) ($coupon['short_summary'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars($usesLabel); ?></td>
                                <td>
                                    <span class="badge-status <?php echo $isActive ? 'badge-status--active' : 'badge-status--inactive'; ?>">
                                        <?php echo $isActive ? 'Đang bật' : 'Đã tắt'; ?>
                                    </span>
                                </td>
                                <td class="admin-table-actions">
                                    <a href="<?php echo e(app_url('admin-coupon-edit', ['id' => (int) $coupon['id']])); ?>">Sửa</a>
                                    <form method="post" action="<?php echo e(app_url('admin-coupons')); ?>" class="admin-inline-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_coupon_active">
                                        <input type="hidden" name="coupon_id" value="<?php echo (int) $coupon['id']; ?>">
                                        <button type="submit" class="link-muted"><?php echo $isActive ? 'Tắt' : 'Bật'; ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
