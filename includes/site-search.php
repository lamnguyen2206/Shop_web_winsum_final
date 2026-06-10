<div class="site-search-overlay" id="site-search-overlay" data-api-url="api/product-search.php" hidden aria-hidden="true">
    <div class="site-search-backdrop" data-site-search-close tabindex="-1"></div>
    <div class="site-search-panel" role="dialog" aria-modal="true" aria-labelledby="site-search-title">
        <p id="site-search-title" class="visually-hidden">Tìm kiếm sản phẩm</p>
        <?php if (!empty($navMenuCategories)): ?>
            <div class="site-search-lamp-types" aria-label="Danh mục">
                <span class="site-search-lamp-types__label">Danh mục:</span>
                <div class="site-search-lamp-types__list">
                    <?php foreach ($navMenuCategories as $navCat): ?>
                        <a href="<?php echo e(app_url('catalog', ['category' => $navCat['slug']])); ?>"><?php echo htmlspecialchars($navCat['nav_label']); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <form class="site-search-form" action="index.php" method="get" role="search" data-site-search-form>
            <input type="hidden" name="view" value="catalog">
            <div class="site-search-input-wrap">
                <input
                    type="search"
                    name="q"
                    class="site-search-input"
                    placeholder="Tìm đèn, nội thất, thương hiệu..."
                    autocomplete="off"
                    aria-label="Từ khóa tìm kiếm"
                    data-site-search-input
                >
                <button type="button" class="site-search-clear" data-site-search-clear hidden aria-label="Xóa từ khóa">
                    <span aria-hidden="true">×</span>
                </button>
                <button type="submit" class="site-search-submit" aria-label="Tìm kiếm">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="16.65" y1="16.65" x2="21" y2="21"></line></svg>
                </button>
            </div>
        </form>
        <div class="site-search-dropdown" data-site-search-dropdown hidden>
            <div class="site-search-suggestions" data-site-search-suggestions hidden></div>
            <section class="site-search-products" data-site-search-products hidden>
                <h3 class="site-search-products-title">Sản phẩm đề xuất</h3>
                <div class="site-search-product-list" data-site-search-product-list></div>
            </section>
            <p class="site-search-empty" data-site-search-empty hidden>Không tìm thấy sản phẩm phù hợp.</p>
            <a class="site-search-view-all" data-site-search-view-all hidden href="index.php?view=catalog">Xem tất cả kết quả</a>
        </div>
    </div>
</div>
