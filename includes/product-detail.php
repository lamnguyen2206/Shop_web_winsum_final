<?php
require_once __DIR__ . '/product-repository.php';
require_once __DIR__ . '/review-repository.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = productGetBySlug($conn, $slug);
$productFlash = pageFlashConsume('product');
$detailNotice = $productFlash['message'];
$reviewNotice = '';
$currentCustomer = customerCurrent($conn);
$productAdminView = adminCurrent();

if (!$product) {
    http_response_code(404);
    ?>
    <section class="container product-page product-page--empty">
        <h1>Không tìm thấy sản phẩm</h1>
        <p>Sản phẩm có thể đã được cập nhật hoặc không còn hiển thị.</p>
        <a href="<?php echo e(app_url('catalog')); ?>" class="btn-secondary">Quay lại danh mục</a>
    </section>
    <?php
    return;
}

$related = productGetRelatedByCategory($conn, $product['category_id'], $product['id'], 4);
$reviews = reviewGetApprovedByProduct($conn, (int) $product['id']);
$customerIdForReview = $currentCustomer ? (int) $currentCustomer['id'] : 0;
$hasReviewedProduct = $customerIdForReview > 0
    && reviewCustomerHasReviewedProduct($conn, $customerIdForReview, (int) $product['id']);
$canReviewProduct = $currentCustomer && !$productAdminView && !$hasReviewedProduct
    ? reviewCustomerCanReviewProduct($conn, $customerIdForReview, (int) $product['id'])
    : false;
$ratingStats = [
    'average' => (float) $product['rating_average'],
    'count' => (int) $product['rating_count'],
];

$stockLabels = [
    'in_stock' => ['label' => 'Còn hàng', 'class' => 'in-stock'],
    'out_of_stock' => ['label' => 'Hết hàng', 'class' => 'out-stock'],
    'preorder' => ['label' => 'Đặt trước', 'class' => 'preorder'],
];
$stock = $stockLabels[$product['stock_status']] ?? $stockLabels['in_stock'];
$inventoryQty = inventoryGetAvailableQty($conn, (int) $product['id']);
?>

<section class="container product-page" data-product-detail>
    <p class="breadcrumb">
        <a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> /
        <a href="<?php echo e(app_url('catalog', ['category' => $product['category_slug']])); ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> /
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </p>

    <?php if ($detailNotice !== ''): ?>
        <p class="catalog-notice"><?php echo htmlspecialchars($detailNotice); ?></p>
    <?php endif; ?>

    <?php if ($productAdminView): ?>
        <div class="product-admin-bar">
            <p>Bạn đang xem với tài khoản quản trị.</p>
            <a class="btn-secondary" href="<?php echo e(app_url('admin-product-edit', ['id' => (int) $product['id']])); ?>">Chỉnh sửa sản phẩm</a>
        </div>
    <?php endif; ?>

    <div class="product-layout">
        <div class="product-gallery" data-gallery>
            <div class="product-gallery-main">
                <img class="product-cover" data-main-image src="<?php echo htmlspecialchars($product['images'][0]['url']); ?>" alt="<?php echo htmlspecialchars($product['images'][0]['alt']); ?>">
            </div>
            <?php if (count($product['images']) > 1): ?>
                <div class="thumb-grid" role="list">
                    <?php foreach ($product['images'] as $index => $image): ?>
                        <button type="button" class="thumb-btn<?php echo $index === 0 ? ' is-active' : ''; ?>" data-thumb="<?php echo htmlspecialchars($image['url']); ?>" aria-label="Xem ảnh <?php echo $index + 1; ?>">
                            <img src="<?php echo htmlspecialchars($image['url']); ?>" alt="<?php echo htmlspecialchars($image['alt']); ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <aside class="product-summary">
            <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="product-rating-row">
                <?php if ($ratingStats['count'] > 0): ?>
                    <span class="stars-display" aria-label="Đánh giá <?php echo $ratingStats['average']; ?>/5">
                        <?php
                        $fullStars = (int) round($ratingStats['average']);
                        echo str_repeat('★', max(0, min(5, $fullStars)));
                        echo str_repeat('☆', max(0, 5 - $fullStars));
                        ?>
                    </span>
                    <span><?php echo $ratingStats['average']; ?>/5</span>
                    <a href="#product-reviews">(<?php echo $ratingStats['count']; ?> đánh giá)</a>
                <?php else: ?>
                    <span class="rating-empty">Chưa có đánh giá</span>
                <?php endif; ?>
            </div>

            <div class="price-block">
                <strong class="price-current"><?php echo htmlspecialchars($product['price_label']); ?></strong>
                <?php if ($product['compare_price_label']): ?>
                    <span class="price-compare"><?php echo htmlspecialchars($product['compare_price_label']); ?></span>
                <?php endif; ?>
            </div>

            <span class="stock-badge stock-badge--<?php echo htmlspecialchars($stock['class']); ?>"><?php echo htmlspecialchars($stock['label']); ?></span>
            <?php if ($product['stock_status'] === 'in_stock' && $inventoryQty >= 0): ?>
                <p class="product-inventory-qty">Tồn kho: <strong><?php echo (int) $inventoryQty; ?></strong> sản phẩm</p>
            <?php elseif ($product['stock_status'] === 'preorder'): ?>
                <p class="product-inventory-qty product-inventory-qty--preorder">Đang nhận đặt trước — chờ nhập hàng</p>
                <p class="preorder-notice">Đơn có sản phẩm <strong>Đặt trước</strong> sẽ không trừ tồn kho khi đặt hàng.</p>
            <?php endif; ?>

            <p class="product-short"><?php echo htmlspecialchars($product['short_description']); ?></p>

            <ul class="product-attrs">
                <li><span>SKU</span> <?php echo htmlspecialchars($product['sku']); ?></li>
                <?php if ($product['material'] !== ''): ?><li><span>Chất liệu</span> <?php echo htmlspecialchars($product['material']); ?></li><?php endif; ?>
                <?php if ($product['color'] !== ''): ?><li><span>Màu sắc</span> <?php echo htmlspecialchars($product['color']); ?></li><?php endif; ?>
                <?php if ($product['warranty_months'] !== null): ?><li><span>Bảo hành</span> <?php echo (int) $product['warranty_months']; ?> tháng</li><?php endif; ?>
            </ul>

            <?php if (!$productAdminView): ?>
            <form method="post" action="index.php?view=product&amp;slug=<?php echo urlencode($product['slug']); ?>" class="add-cart-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_to_cart">
                <div class="qty-row">
                    <label for="qty">Số lượng</label>
                    <input id="qty" type="number" name="qty" min="1" value="1">
                </div>
                <button type="submit" class="btn-add-cart" <?php echo $product['stock_status'] === 'out_of_stock' ? 'disabled' : ''; ?>>
                    <?php echo $product['stock_status'] === 'out_of_stock' ? 'Hết hàng' : 'Thêm vào giỏ hàng'; ?>
                </button>
                <a class="btn-secondary btn-full" href="<?php echo e(app_url('cart')); ?>">Xem giỏ hàng</a>
            </form>
            <?php else: ?>
                <p class="product-admin-note">Tài khoản quản trị không mua qua website. Dùng nút trên để chỉnh sửa thông tin sản phẩm.</p>
            <?php endif; ?>
        </aside>
    </div>

    <div class="product-tabs" data-product-tabs>
        <div class="product-tabs-nav" role="tablist">
            <button type="button" class="is-active" data-tab="desc" role="tab">Mô tả</button>
            <button type="button" data-tab="specs" role="tab">Thông số</button>
            <button type="button" data-tab="reviews" role="tab">Đánh giá (<?php echo count($reviews); ?>)</button>
        </div>

        <div class="product-tabs-panels">
            <article class="product-tab-panel is-active" data-panel="desc" role="tabpanel">
                <h2>Mô tả sản phẩm</h2>
                <div class="product-description-body">
                    <?php if (trim($product['description']) !== ''): ?>
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    <?php else: ?>
                        <p>Đang cập nhật mô tả chi tiết cho sản phẩm này.</p>
                    <?php endif; ?>
                </div>
            </article>

            <article class="product-tab-panel" data-panel="specs" role="tabpanel" hidden>
                <h2>Thông số kỹ thuật</h2>
                <table class="spec-table">
                    <tbody>
                        <tr><th>Danh mục</th><td><?php echo htmlspecialchars($product['category_name']); ?></td></tr>
                        <tr><th>Mã SKU</th><td><?php echo htmlspecialchars($product['sku']); ?></td></tr>
                        <tr><th>Tình trạng</th><td><?php echo htmlspecialchars($stock['label']); ?></td></tr>
                        <?php if ($product['material'] !== ''): ?><tr><th>Chất liệu</th><td><?php echo htmlspecialchars($product['material']); ?></td></tr><?php endif; ?>
                        <?php if ($product['color'] !== ''): ?><tr><th>Màu sắc</th><td><?php echo htmlspecialchars($product['color']); ?></td></tr><?php endif; ?>
                        <?php if ($product['warranty_months'] !== null): ?><tr><th>Bảo hành</th><td><?php echo (int) $product['warranty_months']; ?> tháng</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </article>

            <article class="product-tab-panel" id="product-reviews" data-panel="reviews" role="tabpanel" hidden>
                <h2>Đánh giá từ khách hàng</h2>

                <?php if ($reviewNotice !== ''): ?>
                    <p class="catalog-notice"><?php echo htmlspecialchars($reviewNotice); ?></p>
                <?php endif; ?>

                <?php if (empty($reviews)): ?>
                    <p class="reviews-empty">Chưa có đánh giá được duyệt. Hãy là người đầu tiên!</p>
                <?php else: ?>
                    <ul class="review-list">
                        <?php foreach ($reviews as $review): ?>
                            <li class="review-item">
                                <div class="review-item-head">
                                    <strong><?php echo htmlspecialchars($review['reviewer_name']); ?></strong>
                                    <span class="stars-display"><?php echo str_repeat('★', (int) $review['rating']); ?><?php echo str_repeat('☆', 5 - (int) $review['rating']); ?></span>
                                    <time><?php echo htmlspecialchars((string) $review['created_at']); ?></time>
                                </div>
                                <?php if ($review['title'] !== ''): ?>
                                    <h3><?php echo htmlspecialchars($review['title']); ?></h3>
                                <?php endif; ?>
                                <p><?php echo nl2br(htmlspecialchars($review['content'])); ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($canReviewProduct): ?>
                    <div class="review-form-wrap">
                        <h3>Gửi đánh giá của bạn</h3>
                        <form method="post" action="index.php?view=product&amp;slug=<?php echo urlencode($product['slug']); ?>#product-reviews" class="review-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="submit_review">
                            <div class="review-form-grid">
                                <label>Họ tên
                                    <input type="text" name="reviewer_name" required value="<?php echo htmlspecialchars((string) ($currentCustomer['full_name'] ?? '')); ?>">
                                </label>
                                <label>Email
                                    <input type="email" name="reviewer_email" value="<?php echo htmlspecialchars((string) ($currentCustomer['email'] ?? '')); ?>">
                                </label>
                                <label>Số sao
                                    <select name="rating" required>
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?> sao</option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label>Tiêu đề (tuỳ chọn)
                                    <input type="text" name="review_title">
                                </label>
                            </div>
                            <label>Nội dung đánh giá
                                <textarea name="review_content" rows="4" required placeholder="Chia sẻ trải nghiệm của bạn về sản phẩm..."></textarea>
                            </label>
                            <button type="submit">Gửi đánh giá</button>
                            <p class="form-hint">Đánh giá sẽ hiển thị ngay trên trang sản phẩm.</p>
                        </form>
                    </div>
                <?php elseif (!$productAdminView): ?>
                    <div class="review-form-wrap">
                        <h3>Gửi đánh giá của bạn</h3>
                        <p class="form-hint">
                            <?php if ($hasReviewedProduct): ?>
                                Bạn đã đánh giá sản phẩm này rồi.
                            <?php elseif ($currentCustomer): ?>
                                Bạn chỉ được đánh giá sản phẩm trong đơn hàng đã giao thành công.
                            <?php else: ?>
                                Vui lòng đăng nhập và mua sản phẩm này trước khi đánh giá.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </div>

    <?php if (!empty($related)): ?>
        <section class="related-products">
            <h2>Sản phẩm liên quan</h2>
            <div class="catalog-grid catalog-grid--compact">
                <?php foreach ($related as $item): ?>
                    <article class="catalog-card">
                        <a href="index.php?view=product&amp;slug=<?php echo urlencode($item['slug']); ?>" class="catalog-image">
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                        </a>
                        <div class="catalog-content">
                            <p class="catalog-category"><?php echo htmlspecialchars($item['category_name']); ?></p>
                            <h3><a href="index.php?view=product&amp;slug=<?php echo urlencode($item['slug']); ?>"><?php echo htmlspecialchars($item['name']); ?></a></h3>
                            <p class="catalog-price"><?php echo htmlspecialchars($item['price_label']); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
