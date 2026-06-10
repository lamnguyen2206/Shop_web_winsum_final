<?php
require_once __DIR__ . '/blog-repository.php';
require_once __DIR__ . '/admin-auth.php';

$blogPosts = blogGetAllPosts($conn);
$featuredPosts = blogGetFeaturedPosts($conn, 3);
if ($featuredPosts === []) {
    $featuredPosts = array_slice($blogPosts, 0, 3);
}
?>

<section class="blog-page container">
    <header class="blog-hero">
        <h1 class="blog-title">Tin tức</h1>
    </header>

    <div class="blog-layout">
        <main class="blog-main">
            <?php if (empty($blogPosts)): ?>
                <p class="blog-empty">Chưa có bài viết nào.</p>
            <?php else: ?>
                <div class="blog-grid">
                    <?php foreach ($blogPosts as $post): ?>
                        <?php $postUrl = app_url('post', ['slug' => $post['slug']]); ?>
                        <article class="blog-card">
                            <a href="<?php echo e($postUrl); ?>" class="blog-card__thumb">
                                <img
                                    src="<?php echo htmlspecialchars($post['image']); ?>"
                                    alt="<?php echo htmlspecialchars($post['title']); ?>"
                                    loading="lazy"
                                >
                            </a>
                            <div class="blog-card__body">
                                <h2 class="blog-card__title">
                                    <a href="<?php echo e($postUrl); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                </h2>
                                <p class="blog-card__meta">
                                    <span class="blog-card__meta-item">
                                        <svg class="blog-card__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                                        <?php echo htmlspecialchars($post['date_label']); ?>
                                    </span>
                                    <span class="blog-card__meta-item">
                                        <svg class="blog-card__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                        <?php echo htmlspecialchars($post['read_time']); ?>
                                    </span>
                                </p>
                                <p class="blog-card__excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                                <a href="<?php echo e($postUrl); ?>" class="blog-card__read-more">Đọc tiếp</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>

        <aside class="blog-sidebar" aria-label="Danh mục và tin nổi bật">
            <div class="blog-sidebar__block">
                <h2 class="blog-sidebar__heading">DANH MỤC TIN TỨC</h2>
                <ul class="blog-sidebar__links">
                    <li><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a></li>
                    <li><a href="<?php echo e(app_url('catalog')); ?>">Tất cả sản phẩm</a></li>
                    <li>
                        <a href="<?php echo e(app_url('catalog')); ?>" class="blog-sidebar__link--has-icon">
                            Danh mục
                            <span class="blog-sidebar__chevron" aria-hidden="true">▾</span>
                        </a>
                    </li>
                    <li><a href="<?php echo e(app_url('blog')); ?>" class="is-active">Blog</a></li>
                </ul>
            </div>

            <div class="blog-sidebar__block">
                <h2 class="blog-sidebar__heading">TIN NỔI BẬT</h2>
                <div class="blog-sidebar__featured">
                    <?php if (empty($featuredPosts)): ?>
                        <p class="blog-sidebar__empty">Chưa có tin nổi bật.</p>
                    <?php else: ?>
                        <?php foreach ($featuredPosts as $post): ?>
                            <a href="<?php echo e(app_url('post', ['slug' => $post['slug']])); ?>" class="blog-sidebar__featured-item">
                                <img
                                    src="<?php echo htmlspecialchars($post['image']); ?>"
                                    alt=""
                                    width="72"
                                    height="72"
                                    loading="lazy"
                                >
                                <span class="blog-sidebar__featured-title"><?php echo htmlspecialchars($post['title']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</section>
