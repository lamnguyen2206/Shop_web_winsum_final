<?php
require_once __DIR__ . '/product-admin-repository.php';
require_once __DIR__ . '/product-repository.php';

$view = (string) ($_GET['view'] ?? '');
$isEdit = $view === 'admin-product-edit';
$productId = (int) ($_GET['id'] ?? 0);
$editing = $isEdit && $productId > 0 ? productAdminGetById($conn, $productId) : null;
$categories = productGetFilterCategories($conn);
$adminMessage = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;
$backUrl = app_url('admin-products');

if ($isEdit && !$editing) {
    http_response_code(404);
}

$form = $editing ?: [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'sku' => '',
    'category_id' => $categories[0]['id'] ?? '',
    'short_description' => '',
    'description' => '',
    'base_price' => '',
    'stock_status' => 'in_stock',
    'material' => '',
    'color' => '',
    'warranty_months' => '',
    'is_featured' => 0,
    'is_active' => 1,
    'primary_image' => 'assets/images/blog_1.png',
    'stock_quantity' => 50,
];
?>

<section class="container admin-page admin-product-form-page">
    <p class="breadcrumb">
        <a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> /
        <a href="<?php echo e(app_url('admin-products')); ?>">Quản trị sản phẩm</a> /
        <span><?php echo $isEdit ? 'Chỉnh sửa sản phẩm' : 'Thêm sản phẩm'; ?></span>
    </p>

    <div class="admin-page-head admin-page-head--toolbar">
        <h1><?php echo $isEdit ? 'Chỉnh sửa chi tiết sản phẩm' : 'Thêm sản phẩm mới'; ?></h1>
        <a class="btn-secondary" href="<?php echo e($backUrl); ?>">← Quay lại danh sách sản phẩm</a>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <?php if ($isEdit && !$editing): ?>
        <div class="empty-state">
            <p>Không tìm thấy sản phẩm cần chỉnh sửa.</p>
            <a class="btn-secondary" href="<?php echo e($backUrl); ?>">Quay lại danh sách sản phẩm</a>
        </div>
    <?php else: ?>
        <div class="admin-panel admin-product-form-panel">
            <form method="post" action="<?php echo e(app_url($isEdit ? 'admin-product-edit' : 'admin-product-create', $isEdit ? ['id' => $productId] : [])); ?>" class="admin-form admin-form--detail">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="id" value="<?php echo (int) ($form['id'] ?? 0); ?>">

                <div class="admin-form-grid">
                    <label>Tên sản phẩm
                        <input type="text" name="name" required value="<?php echo htmlspecialchars((string) ($form['name'] ?? '')); ?>">
                    </label>
                    <label>Slug (URL)
                        <input type="text" name="slug" value="<?php echo htmlspecialchars((string) ($form['slug'] ?? '')); ?>" placeholder="tu-dong-neu-de-trong">
                    </label>
                    <label>SKU
                        <input type="text" name="sku" required value="<?php echo htmlspecialchars((string) ($form['sku'] ?? '')); ?>">
                    </label>
                    <label>Danh mục
                        <select name="category_id" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['id']; ?>" <?php echo (int) ($form['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Giá bán (VNĐ)
                        <input type="number" name="base_price" min="0" step="1000" required value="<?php echo htmlspecialchars((string) ($form['base_price'] ?? '')); ?>">
                    </label>
                    <label>Số lượng tồn kho
                        <input type="number" name="stock_quantity" min="0" step="1" value="<?php echo (int) ($form['stock_quantity'] ?? 0); ?>">
                    </label>
                    <label>Tình trạng kho
                        <select name="stock_status">
                            <option value="in_stock" <?php echo ($form['stock_status'] ?? '') === 'in_stock' ? 'selected' : ''; ?>>Còn hàng</option>
                            <option value="preorder" <?php echo ($form['stock_status'] ?? '') === 'preorder' ? 'selected' : ''; ?>>Đặt trước</option>
                            <option value="out_of_stock" <?php echo ($form['stock_status'] ?? '') === 'out_of_stock' ? 'selected' : ''; ?>>Hết hàng</option>
                        </select>
                    </label>
                    <label>Ảnh (đường dẫn)
                        <input type="text" name="image_url" value="<?php echo htmlspecialchars((string) ($form['primary_image'] ?? '')); ?>" placeholder="assets/images/...">
                    </label>
                    <label>Chất liệu
                        <input type="text" name="material" value="<?php echo htmlspecialchars((string) ($form['material'] ?? '')); ?>">
                    </label>
                    <label>Màu sắc
                        <input type="text" name="color" value="<?php echo htmlspecialchars((string) ($form['color'] ?? '')); ?>">
                    </label>
                    <label>Bảo hành (tháng)
                        <input type="number" name="warranty_months" min="0" value="<?php echo htmlspecialchars((string) ($form['warranty_months'] ?? '')); ?>">
                    </label>
                </div>

                <label>Mô tả ngắn
                    <textarea name="short_description" rows="3"><?php echo htmlspecialchars((string) ($form['short_description'] ?? '')); ?></textarea>
                </label>
                <label>Mô tả chi tiết
                    <textarea name="description" rows="8"><?php echo htmlspecialchars((string) ($form['description'] ?? '')); ?></textarea>
                </label>
                <label class="admin-check">
                    <input type="checkbox" name="is_featured" value="1" <?php echo !empty($form['is_featured']) ? 'checked' : ''; ?>> Sản phẩm nổi bật
                </label>
                <p class="admin-field-hint">Trang chủ ưu tiên tối đa 5 SP bán chạy trong 30 ngày; chỉ dùng tick nổi bật khi chưa có đơn hoặc bổ sung thủ công.</p>
                <label class="admin-check">
                    <input type="checkbox" name="is_active" value="1" <?php echo !isset($form['is_active']) || !empty($form['is_active']) ? 'checked' : ''; ?>> Hiển thị trên web
                </label>

                <div class="admin-form-actions">
                    <button type="submit"><?php echo $isEdit ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm'; ?></button>
                    <a class="btn-secondary" href="<?php echo e($backUrl); ?>">Quay lại</a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</section>
