<?php

require_once __DIR__ . '/coupon-admin-repository.php';

$view = (string) ($_GET['view'] ?? '');
$isEdit = $view === 'admin-coupon-edit';
$couponId = (int) ($_GET['id'] ?? 0);
$editing = $isEdit && $couponId > 0 ? couponAdminGetById($conn, $couponId) : null;
$hasRoleColumn = couponAdminHasRoleColumn($conn);
$adminMessage = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;
$backUrl = app_url('admin-coupons');

if ($isEdit && !$editing) {
    http_response_code(404);
}

$form = $editing ?: [
    'id' => 0,
    'code' => '',
    'name' => '',
    'description' => '',
    'discount_type' => 'fixed',
    'coupon_role' => 'discount',
    'discount_value' => '',
    'min_order_amount' => '',
    'max_discount_amount' => '',
    'total_usage_limit' => '',
    'per_customer_limit' => '1',
    'starts_at' => '',
    'ends_at' => '',
    'is_active' => 1,
];

$roleValue = couponResolveRole($form);
?>

<section class="container admin-page admin-product-form-page">
    <p class="breadcrumb">
        <a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> /
        <a href="<?php echo e(app_url('admin-coupons')); ?>">Mã giảm giá</a> /
        <span><?php echo $isEdit ? 'Chỉnh sửa mã' : 'Tạo mã mới'; ?></span>
    </p>

    <div class="admin-page-head admin-page-head--toolbar">
        <h1><?php echo $isEdit ? 'Chỉnh sửa mã giảm giá' : 'Tạo mã giảm giá'; ?></h1>
        <a class="btn-secondary" href="<?php echo e($backUrl); ?>">← Quay lại danh sách</a>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <?php if ($isEdit && !$editing): ?>
        <div class="empty-state">
            <p>Không tìm thấy mã giảm giá.</p>
            <a class="btn-secondary" href="<?php echo e($backUrl); ?>">Quay lại danh sách</a>
        </div>
    <?php else: ?>
        <div class="admin-panel admin-product-form-panel">
            <form method="post" action="<?php echo e(app_url($isEdit ? 'admin-coupon-edit' : 'admin-coupon-create', $isEdit ? ['id' => $couponId] : [])); ?>" class="admin-form admin-form--detail">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_coupon">
                <input type="hidden" name="id" value="<?php echo (int) ($form['id'] ?? 0); ?>">

                <div class="admin-form-grid">
                    <label>Mã coupon
                        <input type="text" name="code" required maxlength="50" value="<?php echo htmlspecialchars((string) ($form['code'] ?? '')); ?>" placeholder="WINSUM20" style="text-transform: uppercase;">
                    </label>
                    <label>Tên hiển thị
                        <input type="text" name="name" required maxlength="120" value="<?php echo htmlspecialchars((string) ($form['name'] ?? '')); ?>">
                    </label>
                    <?php if ($hasRoleColumn): ?>
                    <label>Nhóm hiển thị (tab voucher)
                        <select name="coupon_role">
                            <?php foreach (couponRoleDefinitions() as $roleKey => $roleLabel): ?>
                                <option value="<?php echo htmlspecialchars($roleKey); ?>" <?php echo $roleValue === $roleKey ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($roleLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php endif; ?>
                    <label>Loại giảm
                        <select name="discount_type" id="couponDiscountType">
                            <option value="fixed" <?php echo ($form['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Giảm cố định (VNĐ)</option>
                            <option value="percent" <?php echo ($form['discount_type'] ?? '') === 'percent' ? 'selected' : ''; ?>>Giảm theo %</option>
                            <option value="shipping" <?php echo ($form['discount_type'] ?? '') === 'shipping' ? 'selected' : ''; ?>>Giảm phí ship</option>
                        </select>
                    </label>
                    <label>Giá trị giảm
                        <input type="number" name="discount_value" min="0" step="any" required value="<?php echo htmlspecialchars((string) ($form['discount_value'] ?? '')); ?>">
                    </label>
                    <label>Đơn tối thiểu (VNĐ, tùy chọn)
                        <input type="number" name="min_order_amount" min="0" step="1000" value="<?php echo htmlspecialchars((string) ($form['min_order_amount'] ?? '')); ?>">
                    </label>
                    <label>Giảm tối đa (%, tùy chọn)
                        <input type="number" name="max_discount_amount" min="0" step="1000" value="<?php echo htmlspecialchars((string) ($form['max_discount_amount'] ?? '')); ?>">
                    </label>
                    <label>Giới hạn toàn hệ thống (lượt, tùy chọn)
                        <input type="number" name="total_usage_limit" min="1" step="1" value="<?php echo htmlspecialchars((string) ($form['total_usage_limit'] ?? '')); ?>">
                    </label>
                    <label>Giới hạn / khách (lượt, tùy chọn)
                        <input type="number" name="per_customer_limit" min="1" step="1" value="<?php echo htmlspecialchars((string) ($form['per_customer_limit'] ?? '')); ?>">
                    </label>
                    <label>Bắt đầu (tùy chọn)
                        <input type="datetime-local" name="starts_at" value="<?php echo htmlspecialchars(couponAdminFormatDatetimeLocal($form['starts_at'] ?? null)); ?>">
                    </label>
                    <label>Kết thúc (tùy chọn)
                        <input type="datetime-local" name="ends_at" value="<?php echo htmlspecialchars(couponAdminFormatDatetimeLocal($form['ends_at'] ?? null)); ?>">
                    </label>
                </div>

                <label>Mô tả điều kiện
                    <textarea name="description" rows="3"><?php echo htmlspecialchars((string) ($form['description'] ?? '')); ?></textarea>
                </label>

                <label class="admin-check">
                    <input type="checkbox" name="is_active" value="1" <?php echo !isset($form['is_active']) || !empty($form['is_active']) ? 'checked' : ''; ?>> Đang hoạt động
                </label>

                <p class="admin-field-hint">Mã cố định: nhập số tiền (VD 40000). Phần trăm: nhập 10 = 10%. Freeship: nhập tối đa phí ship được giảm (VD 30000).</p>

                <div class="admin-form-actions">
                    <button type="submit"><?php echo $isEdit ? 'Cập nhật mã' : 'Tạo mã'; ?></button>
                    <a class="btn-secondary" href="<?php echo e($backUrl); ?>">Quay lại</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
