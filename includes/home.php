<?php
require_once __DIR__ . '/home-repository.php';

$displayCategories = homeGetDisplayCategories($conn);
$bestsellerProducts = homeGetBestsellerProducts($conn, 8);
$newsPosts = homeGetNewsPosts($conn, 4);
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
                <img src="assets/images/index-banner2.webp" alt="Không gian nội thất Winsum">
                <div class="highlight-badge">BST Mới 2026</div>
            </div>
        </div>
    </section>

    <div class="home-showcase">
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
    </div>

    <div class="home-dark">
        <section class="container home-category-section" aria-label="Danh mục sản phẩm">
            <h2 class="home-category-heading"><span class="heading-eyebrow">our</span>CATEGORY</h2>
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

        <section class="home-about" aria-label="Về chúng tôi">
        <div class="home-about-grid">
            <div class="home-about-media">
                <img
                    src="assets/images/index-about.webp"
                    alt="Không gian trưng bày Winsum Home Decor"
                    loading="lazy"
                >
            </div>
            <div class="home-about-copy">
                <h2>VỀ CHÚNG TÔI</h2>
                <p>Tại Winsum Home Decor, chúng tôi tin rằng đèn không chỉ là công cụ chiếu sáng, mà còn là yếu tố quan trọng tạo nên không gian sống đầy cảm hứng. Chúng tôi chuyên cung cấp các mẫu đèn trang trí cao cấp – tinh tế trong thiết kế, chất lượng trong từng chi tiết – mang đến sự ấm áp, hiện đại và gu thẩm mỹ riêng cho từng ngôi nhà. Winsum tự hào là người bạn đồng hành cùng bạn trong hành trình ánh sáng.</p>
                <a class="home-about-link" href="<?php echo e(app_url('blog')); ?>">Xem chi tiết &gt;</a>
            </div>
        </div>
    </section>

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

    <section class="home-section container home-gallery" aria-label="Không gian cảm hứng">
        <h2 class="home-category-heading"><span class="heading-eyebrow">follow</span>INSTAGRAM</h2>
        <div class="home-gallery-row1">
            <a class="home-gallery-item" href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener" aria-label="Xem Instagram Winsum Home">
                <img src="assets/images/vintage.webp" alt="Góc decor phong cách vintage" loading="lazy">
                <span class="home-gallery-item__overlay" aria-hidden="true">Instagram</span>
            </a>
            <a class="home-gallery-item" href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener" aria-label="Xem Instagram Winsum Home">
                <img src="assets/images/index-c2.webp" alt="Đèn tường trang trí" loading="lazy">
                <span class="home-gallery-item__overlay" aria-hidden="true">Instagram</span>
            </a>
            <a class="home-gallery-item" href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener" aria-label="Xem Instagram Winsum Home">
                <img src="assets/images/index-c3.webp" alt="Đèn bàn trong không gian làm việc" loading="lazy">
                <span class="home-gallery-item__overlay" aria-hidden="true">Instagram</span>
            </a>
            <a class="home-gallery-item" href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener" aria-label="Xem Instagram Winsum Home">
                <img src="assets/images/index-banner2.webp" alt="Đèn thả trong không gian hiện đại" loading="lazy">
                <span class="home-gallery-item__overlay" aria-hidden="true">Instagram</span>
            </a>
            <a class="home-gallery-item" href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener" aria-label="Xem Instagram Winsum Home">
                <img src="assets/images/index-blog3.webp" alt="Không gian phòng khách ấm áp" loading="lazy">
                <span class="home-gallery-item__overlay" aria-hidden="true">Instagram</span>
            </a>
        </div>
        <div class="home-gallery-row2">
            <a class="home-gallery-card" href="<?php echo e(app_url('catalog')); ?>">
                <img src="assets/images/blog_1.png" alt="Không gian home decor" loading="lazy">
                <span class="home-gallery-card__label">Home Decor<small>Winsum</small></span>
            </a>
            <a class="home-gallery-card" href="<?php echo e(app_url('catalog')); ?>">
                <img src="assets/images/blog_2.png" alt="Không gian homestay" loading="lazy">
                <span class="home-gallery-card__label">Homestay<small>Winsum</small></span>
            </a>
            <a class="home-gallery-card" href="<?php echo e(app_url('catalog')); ?>">
                <img src="assets/images/blog_3.png" alt="Không gian quán cà phê" loading="lazy">
                <span class="home-gallery-card__label">Coffee Shop<small>Winsum</small></span>
            </a>
            <a class="home-gallery-card" href="<?php echo e(app_url('catalog')); ?>">
                <img src="assets/images/index-banner1.jpg" alt="Chiếu sáng ngoài trời" loading="lazy">
                <span class="home-gallery-card__label">Out Door<small>Winsum</small></span>
            </a>
        </div>
    </section>

    <section class="home-section home-news container">
        <div class="section-head section-head--stacked">
            <h2><span class="heading-eyebrow">our</span>BLOG</h2>
            <a href="<?php echo e(app_url('blog')); ?>">Xem tất cả</a>
        </div>
        <div class="news-grid">
            <?php if (empty($newsPosts)): ?>
                <article class="news-card placeholder-card"><div class="news-card__body"><h3>Chưa có bài viết</h3></div></article>
            <?php else: ?>
                <?php foreach ($newsPosts as $post): ?>
                    <article class="news-card news-card--overlay">
                        <a class="news-card__media" href="<?php echo e(app_url('post', ['slug' => $post['slug']])); ?>" aria-label="<?php echo htmlspecialchars($post['title']); ?>">
                            <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" loading="lazy">
                            <span class="news-card__overlay">
                                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                                <span class="news-card__cta">Xem ngay</span>
                            </span>
                        </a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    </div>
</section>
