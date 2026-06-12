<?php
require __DIR__ . '/home-repository.php';

$displayCategories = homeGetDisplayCategories($conn);
$bestsellerProducts = homeGetBestsellerProducts($conn, 8);
$newsPosts = homeGetNewsPosts($conn, 2);
?>

<section class="home-page">
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">WINSUM HOME</p>
                <h1>Nội thất và chiếu sáng cao cấp cho không gian sống đẳng cấp</h1>
                <p class="subtitle">Khám phá bộ sưu tập đèn trang trí, nội thất nhập khẩu và giải pháp thiết kế đồng bộ theo chuẩn châu Âu.</p>
                <div class="hero-actions">
                    <a href="<?php echo e(app_url('catalog')); ?>" class="btn btn-primary">Mua sắm ngay</a>
                    <a href="<?php echo e(app_url('blog')); ?>" class="btn btn-ghost">Xem tin tức</a>
                </div>
            </div>
            <div class="hero-highlight">
                <img src="assets/images/blog_3.png" alt="Không gian nội thất Winsum">
                <div class="highlight-badge">BST Mới 2026</div>
            </div>
        </div>
    </section>

    <div class="home-showcase">
        <section class="container home-voucher-section" aria-label="Mã giảm giá">
            <article class="voucher-card">
                <div class="voucher-card__content">
                    <h3 class="voucher-card__title">XIN CHÀO</h3>
                    <p class="voucher-card__desc">Giảm 40.000đ cho toàn bộ đơn hàng.</p>
                    <p class="voucher-card__code">Mã: <strong>WINSUMXINCHAO</strong></p>
                    <p class="voucher-card__note">Áp dụng 1 mã trên mỗi khách hàng.</p>
                </div>
                <div class="voucher-card__actions">
                    <button
                        type="button"
                        class="voucher-card__info"
                        aria-label="Thông tin mã giảm giá"
                        title="Áp dụng 1 mã trên mỗi khách hàng."
                    >
                        <span aria-hidden="true">i</span>
                    </button>
                    <button type="button" class="voucher-card__copy" data-copy-code="WINSUMXINCHAO">Sao chép</button>
                </div>
            </article>
        </section>

        <section class="container home-benefits-section" aria-label="Cam kết dịch vụ">
            <div class="home-benefits-grid">
                <article class="benefit-card">
                    <div class="benefit-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7h11v8H3z"/><path d="M14 10h4l3 3v2h-7V10z"/><circle cx="7" cy="17" r="1.5"/><circle cx="17" cy="17" r="1.5"/></svg>
                    </div>
                    <h4>Giao hàng hỏa tốc</h4>
                    <p>Nhận hàng trong vòng 24h</p>
                </article>
                <article class="benefit-card">
                    <div class="benefit-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="8" width="18" height="13" rx="1"/><path d="M12 8V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v3"/><path d="M12 12v4"/><path d="M8 12h8"/></svg>
                    </div>
                    <h4>Quà tặng hấp dẫn</h4>
                    <p>Nhiều ưu đãi khuyến mãi hot</p>
                </article>
                <article class="benefit-card">
                    <div class="benefit-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="9" r="5"/><path d="M7 20h10l1-4H6l1 4z"/></svg>
                    </div>
                    <h4>Bảo đảm chất lượng</h4>
                    <p>Sản phẩm đã được kiểm định</p>
                </article>
                <article class="benefit-card">
                    <div class="benefit-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 5h16v10a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V5z"/><path d="M8 19h8"/><path d="M12 15v4"/></svg>
                    </div>
                    <h4>Hotline: 0387239676</h4>
                    <p>Dịch vụ hỗ trợ bạn 24/7</p>
                </article>
            </div>
        </section>

        <section class="container home-category-section" aria-label="Danh mục sản phẩm">
            <h2 class="home-category-heading">CATEGORY</h2>
            <div class="home-category-grid">
                <?php if (empty($displayCategories)): ?>
                    <article class="home-category-card home-category-card--empty">
                        <p>Chưa có danh mục hiển thị.</p>
                    </article>
                <?php else: ?>
                    <?php foreach ($displayCategories as $category): ?>
                        <?php
                        $catLabel = productCategoryNavLabel($category['slug'], $category['name']);
                        $catImage = homeCategoryImageUrl($category['slug']);
                        ?>
                        <a
                            class="home-category-card"
                            href="<?php echo e(app_url('catalog', ['category' => $category['slug']])); ?>"
                        >
                            <div class="home-category-card__media">
                                <img
                                    src="<?php echo htmlspecialchars($catImage); ?>"
                                    alt="<?php echo htmlspecialchars($catLabel); ?>"
                                    loading="lazy"
                                >
                            </div>
                            <h3 class="home-category-card__name"><?php echo htmlspecialchars(mb_strtoupper($catLabel, 'UTF-8')); ?></h3>
                            <?php if (!empty($category['product_count'])): ?>
                                <p class="home-category-card__count"><?php echo (int) $category['product_count']; ?> sản phẩm</p>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="home-section container home-bestsellers">
        <div class="section-head">
            <h2>Sản phẩm chủ lực</h2>
            <a href="<?php echo e(app_url('catalog')); ?>">Tất cả sản phẩm</a>
        </div>
        <p class="home-section-note">Top sản phẩm bán chạy trong 30 ngày gần nhất (theo đơn hợp lệ).</p>
        <div class="product-grid">
            <?php if (empty($bestsellerProducts)): ?>
                <article class="product-card placeholder-card">
                    <div class="product-info">
                        <h3>Chưa có dữ liệu bán hàng</h3>
                        <p>Đánh dấu sản phẩm nổi bật trong quản trị hoặc chờ đơn hàng đầu tiên.</p>
                    </div>
                </article>
            <?php else: ?>
                <?php foreach ($bestsellerProducts as $product): ?>
                    <article class="product-card">
                        <a href="<?php echo e(app_url('product', ['slug' => $product['slug'] ?? ''])); ?>" class="product-card-image" title="Xem chi tiết: <?php echo htmlspecialchars($product['name']); ?>" aria-label="Xem chi tiết <?php echo htmlspecialchars($product['name']); ?>">
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </a>
                        <div class="product-info">
                            <p class="product-category"><?php echo htmlspecialchars($product['category']); ?></p>
                            <h3><a href="<?php echo e(app_url('product', ['slug' => $product['slug'] ?? ''])); ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                            <p class="product-price"><?php echo htmlspecialchars($product['price']); ?></p>
                            <?php if (!empty($product['units_sold'])): ?>
                                <p class="product-sold-badge">Đã bán <?php echo (int) $product['units_sold']; ?></p>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="home-section home-news container">
        <div class="section-head">
            <h2>Our Blog</h2>
            <a href="<?php echo e(app_url('blog')); ?>">Đến trang blog</a>
        </div>
        <div class="news-grid">
            <?php if (empty($newsPosts)): ?>
                <article class="news-card placeholder-card"><div><h3>Chưa có bài viết</h3></div></article>
            <?php else: ?>
                <?php foreach ($newsPosts as $post): ?>
                    <article class="news-card">
                        <a href="<?php echo e(app_url('post', ['slug' => $post['slug']])); ?>">
                            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                        </a>
                        <div>
                            <h3><a href="<?php echo e(app_url('post', ['slug' => $post['slug']])); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                            <p><?php echo htmlspecialchars($post['excerpt']); ?></p>
                            <a class="read-more" href="<?php echo e(app_url('post', ['slug' => $post['slug']])); ?>">Xem ngay</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</section>
