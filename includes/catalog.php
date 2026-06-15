<?php
require_once __DIR__ . '/product-repository.php';
require_once __DIR__ . '/customer-auth.php';

$catalogFlash = pageFlashConsume('catalog');
$catalogNotice = $catalogFlash['message'];
$catalogCustomer = customerCurrent($conn);
$catalogAdminNoShop = !customerMayShopOnStorefront($catalogCustomer);

$filters = productBuildFiltersFromRequest();
$categories = productGetFilterCategories($conn);
$perPage = 16;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$totalProducts = productCountSearchProducts($conn, $filters);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $perPage;
$products = productSearchProducts($conn, $filters, $perPage, $offset);

$filterLabels = [
    'q' => 'Từ khóa',
    'category' => 'Loại đèn',
    'color' => 'Màu sắc',
    'min_price' => 'Giá từ',
    'max_price' => 'Giá đến',
];

function catalogBuildUrl(array $filters, int $page = 1): string
{
    $params = ['view' => 'catalog', 'page' => $page];
    foreach ($filters as $key => $value) {
        if ($key === 'sort' && $value === 'featured') {
            continue;
        }
        if ($key === 'min_price' || $key === 'max_price') {
            if ((float) $value <= 0) {
                continue;
            }
            $params[$key] = (int) round((float) $value);
            continue;
        }
        if ($value === '' || $value === 0 || $value === '0') {
            continue;
        }
        $params[$key] = $value;
    }
    return 'index.php?' . http_build_query($params);
}

function catalogFilterDisplayValue(string $key, $value, array $categories): string
{
    if ($key === 'category') {
        foreach ($categories as $item) {
            if ($item['slug'] === $value) {
                return $item['name'];
            }
        }
    }
    if ($key === 'min_price' || $key === 'max_price') {
        return number_format((float) $value, 0, ',', '.') . 'đ';
    }
    return (string) $value;
}
?>

<section class="container catalog-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Sản phẩm</span></p>
    <div class="catalog-head">
        <h1>Tất cả sản phẩm</h1>
        <p>Khám phá bộ sưu tập nội thất và chiếu sáng cao cấp từ Winsum Home.</p>
    </div>

    <?php if ($catalogNotice !== ''): ?>
        <p class="catalog-notice"><?php echo htmlspecialchars($catalogNotice); ?></p>
    <?php endif; ?>

    <?php
    $activeFilters = [];
    foreach ($filterLabels as $key => $label) {
        $value = $filters[$key] ?? '';
        if ($key === 'min_price' || $key === 'max_price') {
            if ((float) $value <= 0) {
                continue;
            }
            $activeFilters[$key] = ['label' => $label, 'value' => $value];
            continue;
        }
        if ($value !== '' && $value !== 0 && $value !== '0') {
            $activeFilters[$key] = ['label' => $label, 'value' => $value];
        }
    }
    ?>
    <?php if (!empty($activeFilters)): ?>
        <div class="filter-badges">
            <?php foreach ($activeFilters as $key => $data): ?>
                <?php
                $nextFilters = $filters;
                $nextFilters[$key] = $key === 'min_price' || $key === 'max_price' ? 0.0 : '';
                $removeUrl = catalogBuildUrl($nextFilters, 1);
                $displayValue = catalogFilterDisplayValue($key, $data['value'], $categories);
                ?>
                <a class="filter-badge" href="<?php echo htmlspecialchars($removeUrl); ?>">
                    <?php echo htmlspecialchars($data['label'] . ': ' . $displayValue); ?>
                    <span>×</span>
                </a>
            <?php endforeach; ?>
            <a class="clear-all-filters" href="index.php?view=catalog">Xóa tất cả</a>
        </div>
    <?php endif; ?>

    <div class="catalog-layout">
        <div class="catalog-results">
            <div class="catalog-results-head">
                <p class="catalog-results-count">
                    Tìm thấy <strong><?php echo (int) $totalProducts; ?></strong> sản phẩm
                    <?php if (($filters['q'] ?? '') !== ''): ?>
                        cho “<?php echo htmlspecialchars($filters['q']); ?>”
                    <?php endif; ?>
                </p>
                <form method="get" action="index.php" class="catalog-sort-form">
                    <input type="hidden" name="view" value="catalog">
                    <?php if (($filters['q'] ?? '') !== ''): ?>
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>">
                    <?php endif; ?>
                    <?php if (($filters['category'] ?? '') !== ''): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($filters['category']); ?>">
                    <?php endif; ?>
                    <?php if (($filters['color'] ?? '') !== ''): ?>
                        <input type="hidden" name="color" value="<?php echo htmlspecialchars($filters['color']); ?>">
                    <?php endif; ?>
                    <?php if ($filters['min_price'] > 0): ?>
                        <input type="hidden" name="min_price" value="<?php echo (int) $filters['min_price']; ?>">
                    <?php endif; ?>
                    <?php if ($filters['max_price'] > 0): ?>
                        <input type="hidden" name="max_price" value="<?php echo (int) $filters['max_price']; ?>">
                    <?php endif; ?>
                    <label class="catalog-sort-form__label" for="catalog-sort">Sắp xếp</label>
                    <select id="catalog-sort" name="sort" class="catalog-sort-form__select" onchange="this.form.submit()">
                        <option value="featured" <?php echo $filters['sort'] === 'featured' ? 'selected' : ''; ?>>Nổi bật</option>
                        <option value="latest" <?php echo $filters['sort'] === 'latest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="price_asc" <?php echo $filters['sort'] === 'price_asc' ? 'selected' : ''; ?>>Giá tăng dần</option>
                        <option value="price_desc" <?php echo $filters['sort'] === 'price_desc' ? 'selected' : ''; ?>>Giá giảm dần</option>
                        <option value="name_asc" <?php echo $filters['sort'] === 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                    </select>
                </form>
                <?php if (!empty($activeFilters)): ?>
                    <a class="clear-all-filters catalog-results-clear" href="<?php echo e(app_url('catalog')); ?>">Xóa tất cả bộ lọc</a>
                <?php endif; ?>
            </div>
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>Chưa có sản phẩm phù hợp với bộ lọc bạn đã chọn.</p>
                </div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($products as $product): ?>
                        <article class="catalog-card">
                            <a href="index.php?view=product&amp;slug=<?php echo urlencode($product['slug']); ?>" class="catalog-image" title="Xem chi tiết: <?php echo htmlspecialchars($product['name']); ?>" aria-label="Xem chi tiết <?php echo htmlspecialchars($product['name']); ?>">
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <span class="catalog-image-overlay">Xem chi tiết</span>
                            </a>
                            <div class="catalog-content">
                                <div class="catalog-card-body">
                                    <p class="catalog-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                    <h3><a href="index.php?view=product&amp;slug=<?php echo urlencode($product['slug']); ?>"><?php echo htmlspecialchars($product['name']); ?></a></h3>
                                    <p class="catalog-price"><?php echo htmlspecialchars($product['price_label']); ?></p>
                                    <p class="catalog-stock">Tình trạng: <strong><?php echo htmlspecialchars(productStockStatusLabel($product['stock_status'])); ?></strong></p>
                                    <p class="catalog-desc"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                </div>
                                <div class="catalog-actions<?php echo $catalogAdminNoShop ? ' catalog-actions--single' : ''; ?>">
                                    <?php if (!$catalogAdminNoShop): ?>
                                    <form method="post" action="<?php echo htmlspecialchars(catalogBuildUrl($filters, $currentPage)); ?>">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="add_to_cart">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                        <input type="hidden" name="qty" value="1">
                                        <button type="submit" <?php echo $product['stock_status'] === 'out_of_stock' ? 'disabled' : ''; ?>>
                                            <?php echo $product['stock_status'] === 'out_of_stock' ? 'Hết hàng' : 'Thêm giỏ'; ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="index.php?view=product&amp;slug=<?php echo urlencode($product['slug']); ?>" class="btn-secondary catalog-actions__detail">Xem chi tiết</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="catalog-pagination" aria-label="Phân trang sản phẩm">
                        <?php
                        $prevPage = max(1, $currentPage - 1);
                        $nextPage = min($totalPages, $currentPage + 1);
                        ?>
                        <a class="<?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(catalogBuildUrl($filters, $prevPage)); ?>">Trước</a>
                        <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                            <a class="<?php echo $page === $currentPage ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(catalogBuildUrl($filters, $page)); ?>">
                                <?php echo $page; ?>
                            </a>
                        <?php endfor; ?>
                        <a class="<?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(catalogBuildUrl($filters, $nextPage)); ?>">Sau</a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
