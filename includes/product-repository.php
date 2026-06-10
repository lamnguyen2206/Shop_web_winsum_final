<?php
require_once __DIR__ . '/../config/database.php';

function productFormatPrice(float $amount): string
{
    return number_format($amount, 0, ',', '.') . 'đ';
}

/** Số sản phẩm chủ lực đề xuất trên trang chủ và trong quản trị. */
function productFeaturedHomeLimit(): int
{
    return 5;
}

function productStockStatusLabel(string $status): string
{
    $map = [
        'in_stock' => 'Còn hàng',
        'out_of_stock' => 'Hết hàng',
        'preorder' => 'Đặt trước',
    ];
    return $map[$status] ?? $status;
}

function productGetFilterCategories(mysqli $conn): array
{
    $result = $conn->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
    if (!$result) {
        return [];
    }

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    return $categories;
}

/** Mô tả ngắn mặc định theo slug danh mục đèn. */
function productCategoryDefaultDescription(string $slug): string
{
    $map = [
        'den-tha-tran' => 'Đèn treo trần, phù hợp phòng khách, phòng ăn và không gian rộng.',
        'den-tuong' => 'Đèn tường trang trí, tạo điểm nhấn hành lang và khu vực sinh hoạt.',
        'den-ban' => 'Đèn bàn làm việc, đọc sách và trang trí bàn trà, bàn làm việc.',
        'den-san' => 'Đèn sàn đứng, chiếu sáng góc sofa hoặc khu tiếp khách.',
        'den-chum' => 'Đèn chùm cao cấp, làm điểm nhấn cho phòng khách sang trọng.',
        'ke-trang-tri' => 'Kệ và phụ kiện trang trí bổ sung cho không gian nội thất.',
    ];

    return $map[$slug] ?? 'Khám phá sản phẩm được tuyển chọn từ Winsum Home.';
}

/** Danh mục catalog kèm số lượng sản phẩm đang bán. */
function productGetCatalogCategories(mysqli $conn): array
{
    $sql = "SELECT c.id, c.name, c.slug, c.description, c.sort_order,
                   COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
                AND p.is_active = 1
                AND (p.published_at IS NULL OR p.published_at <= NOW())
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.slug, c.description, c.sort_order
            ORDER BY c.sort_order ASC, c.name ASC";
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $slug = (string) $row['slug'];
        $description = trim((string) ($row['description'] ?? ''));
        $categories[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => $slug,
            'description' => $description !== '' ? $description : productCategoryDefaultDescription($slug),
            'product_count' => (int) $row['product_count'],
        ];
    }
    return $categories;
}

/** Chỉ các danh mục loại đèn (slug bắt đầu bằng den-). */
function productGetLampCategories(mysqli $conn): array
{
    return array_values(array_filter(
        productGetCatalogCategories($conn),
        static fn(array $cat): bool => str_starts_with($cat['slug'], 'den-')
    ));
}

/** Tên hiển thị menu (title case) theo slug danh mục. */
function productCategoryNavLabel(string $slug, string $fallbackName): string
{
    $map = [
        'den-ban' => 'Đèn bàn',
        'den-san' => 'Đèn sàn',
        'den-tha-tran' => 'Đèn thả trần',
        'den-tuong' => 'Đèn tường',
        'den-chum' => 'Đèn chùm',
        'ke-trang-tri' => 'Kệ trang trí',
    ];

    return $map[$slug] ?? $fallbackName;
}

/** Danh mục cho menu header (thứ tự cố định, gồm kệ trang trí). */
function productGetNavMenuCategories(mysqli $conn): array
{
    $order = ['den-ban', 'den-san', 'den-tha-tran', 'den-tuong', 'den-chum', 'ke-trang-tri'];
    $bySlug = [];
    foreach (productGetCatalogCategories($conn) as $cat) {
        $bySlug[$cat['slug']] = $cat;
    }

    $items = [];
    foreach ($order as $slug) {
        if (!isset($bySlug[$slug])) {
            continue;
        }
        $cat = $bySlug[$slug];
        $cat['nav_label'] = productCategoryNavLabel($slug, $cat['name']);
        $items[] = $cat;
    }
    return $items;
}

function productGetFilterBrands(mysqli $conn): array
{
    $result = $conn->query("SELECT id, name, slug FROM brands WHERE is_active = 1 ORDER BY name ASC");
    if (!$result) {
        return [];
    }

    $brands = [];
    while ($row = $result->fetch_assoc()) {
        $brands[] = $row;
    }
    return $brands;
}

function productGetFilterMaterialOptions(mysqli $conn): array
{
    $result = $conn->query("SELECT DISTINCT material
                            FROM products
                            WHERE is_active = 1
                              AND material IS NOT NULL
                              AND material <> ''
                            ORDER BY material ASC");
    if (!$result) {
        return [];
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row['material'];
    }
    return $items;
}

function productGetFilterColorOptions(mysqli $conn): array
{
    $result = $conn->query("SELECT DISTINCT color
                            FROM products
                            WHERE is_active = 1
                              AND color IS NOT NULL
                              AND color <> ''
                            ORDER BY color ASC");
    if (!$result) {
        return [];
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row['color'];
    }
    return $items;
}

function productGetAvailablePriceRange(mysqli $conn): array
{
    $result = $conn->query("SELECT MIN(base_price) AS min_price, MAX(base_price) AS max_price FROM products WHERE is_active = 1");
    $row = $result ? $result->fetch_assoc() : null;
    $min = isset($row['min_price']) ? (float) $row['min_price'] : 0;
    $max = isset($row['max_price']) ? (float) $row['max_price'] : 0;
    return ['min' => $min, 'max' => $max];
}

function productBuildFiltersFromRequest(): array
{
    return [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'category' => trim((string) ($_GET['category'] ?? '')),
        'brand' => trim((string) ($_GET['brand'] ?? '')),
        'material' => trim((string) ($_GET['material'] ?? '')),
        'color' => trim((string) ($_GET['color'] ?? '')),
        'stock' => trim((string) ($_GET['stock'] ?? '')),
        'min_price' => (float) ($_GET['min_price'] ?? 0),
        'max_price' => (float) ($_GET['max_price'] ?? 0),
        'sort' => trim((string) ($_GET['sort'] ?? 'featured')),
    ];
}

function productMapListRow(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'sku' => $row['sku'],
        'short_description' => $row['short_description'] ?? '',
        'base_price' => (float) $row['base_price'],
        'compare_at_price' => isset($row['compare_at_price']) && $row['compare_at_price'] !== null
            ? (float) $row['compare_at_price'] : null,
        'price_label' => productFormatPrice((float) $row['base_price']),
        'stock_status' => $row['stock_status'],
        'category_name' => $row['category_name'] ?? 'Chưa phân loại',
        'category_slug' => $row['category_slug'] ?? '',
        'brand_name' => $row['brand_name'] ?? 'Winsum Home',
        'brand_slug' => $row['brand_slug'] ?? '',
        'image' => $row['image_url'] ?: 'assets/images/blog_1.png'
    ];
}

function productBuildSearchConditions(array $filters): array
{
    $where = [
        "p.is_active = 1",
        "(p.published_at IS NULL OR p.published_at <= NOW())",
    ];
    $types = '';
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = "(p.name LIKE CONCAT('%', ?, '%') OR p.short_description LIKE CONCAT('%', ?, '%'))";
        $types .= 'ss';
        $params[] = $filters['q'];
        $params[] = $filters['q'];
    }

    if ($filters['category'] !== '') {
        $where[] = "c.slug = ?";
        $types .= 's';
        $params[] = $filters['category'];
    }

    if ($filters['stock'] !== '' && in_array($filters['stock'], ['in_stock', 'out_of_stock', 'preorder'], true)) {
        $where[] = "p.stock_status = ?";
        $types .= 's';
        $params[] = $filters['stock'];
    }

    if ($filters['min_price'] > 0) {
        $where[] = "p.base_price >= ?";
        $types .= 'd';
        $params[] = $filters['min_price'];
    }

    if ($filters['max_price'] > 0) {
        $where[] = "p.base_price <= ?";
        $types .= 'd';
        $params[] = $filters['max_price'];
    }

    if ($filters['brand'] !== '') {
        $where[] = "b.slug = ?";
        $types .= 's';
        $params[] = $filters['brand'];
    }

    if ($filters['material'] !== '') {
        $where[] = "p.material = ?";
        $types .= 's';
        $params[] = $filters['material'];
    }

    if ($filters['color'] !== '') {
        $where[] = "p.color = ?";
        $types .= 's';
        $params[] = $filters['color'];
    }

    return [
        'where_sql' => implode(' AND ', $where),
        'types' => $types,
        'params' => $params,
    ];
}

function productResolveSortSql(string $sort): string
{
    if ($sort === 'price_asc') {
        return "p.base_price ASC, p.id DESC";
    }
    if ($sort === 'price_desc') {
        return "p.base_price DESC, p.id DESC";
    }
    if ($sort === 'name_asc') {
        return "p.name ASC, p.id DESC";
    }
    if ($sort === 'latest') {
        return "p.published_at DESC, p.id DESC";
    }
    return "p.is_featured DESC, p.published_at DESC, p.id DESC";
}

function productCountSearchProducts(mysqli $conn, array $filters): int
{
    $conditions = productBuildSearchConditions($filters);
    $sql = "SELECT COUNT(*) AS total
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            WHERE {$conditions['where_sql']}";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($conditions['types'] !== '') {
        $stmt->bind_param($conditions['types'], ...$conditions['params']);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

function productSearchProducts(mysqli $conn, array $filters, int $limit = 12, int $offset = 0): array
{
    $conditions = productBuildSearchConditions($filters);
    $sortSql = productResolveSortSql($filters['sort']);
    $sql = "SELECT p.id, p.name, p.slug, p.sku, p.short_description, p.base_price, p.compare_at_price, p.stock_status,
                   c.name AS category_name, c.slug AS category_slug,
                   b.name AS brand_name, b.slug AS brand_slug,
                   pi.image_url
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
            WHERE {$conditions['where_sql']}
            ORDER BY {$sortSql}
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $types = $conditions['types'] . 'ii';
    $params = $conditions['params'];
    $params[] = $limit;
    $params[] = max(0, $offset);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = productMapListRow($row);
    }
    $stmt->close();

    return $items;
}

function productGetBySlug(mysqli $conn, string $slug): ?array
{
    $sql = "SELECT p.id, p.category_id, p.name, p.slug, p.sku, p.short_description, p.description, p.base_price, p.compare_at_price,
                   p.stock_status, p.material, p.color, p.warranty_months, p.rating_average, p.rating_count,
                   c.name AS category_name, c.slug AS category_slug
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.slug = ?
              AND p.is_active = 1
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $product = [
        'id' => (int) $row['id'],
        'category_id' => (int) $row['category_id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'sku' => $row['sku'],
        'short_description' => $row['short_description'] ?? '',
        'description' => $row['description'] ?? '',
        'base_price' => (float) $row['base_price'],
        'compare_at_price' => $row['compare_at_price'] !== null ? (float) $row['compare_at_price'] : null,
        'price_label' => productFormatPrice((float) $row['base_price']),
        'compare_price_label' => $row['compare_at_price'] !== null ? productFormatPrice((float) $row['compare_at_price']) : null,
        'stock_status' => $row['stock_status'],
        'material' => $row['material'] ?? '',
        'color' => $row['color'] ?? '',
        'warranty_months' => $row['warranty_months'] !== null ? (int) $row['warranty_months'] : null,
        'rating_average' => round((float) ($row['rating_average'] ?? 0), 1),
        'rating_count' => (int) ($row['rating_count'] ?? 0),
        'category_name' => $row['category_name'],
        'category_slug' => $row['category_slug'],
        'images' => [],
    ];

    $stmtImages = $conn->prepare("SELECT image_url, alt_text, sort_order, is_primary
                                  FROM product_images
                                  WHERE product_id = ?
                                  ORDER BY is_primary DESC, sort_order ASC, id ASC");
    if ($stmtImages) {
        $productId = $product['id'];
        $stmtImages->bind_param('i', $productId);
        $stmtImages->execute();
        $imagesRes = $stmtImages->get_result();
        while ($img = $imagesRes->fetch_assoc()) {
            $product['images'][] = [
                'url' => $img['image_url'],
                'alt' => $img['alt_text'] ?: $product['name'],
            ];
        }
        $stmtImages->close();
    }

    if (empty($product['images'])) {
        $product['images'][] = ['url' => 'assets/images/blog_1.png', 'alt' => $product['name']];
    }

    return $product;
}

function productGetRelatedByCategory(mysqli $conn, int $categoryId, int $excludeId, int $limit = 4): array
{
    $stmt = $conn->prepare("SELECT p.id, p.name, p.slug, p.sku, p.short_description, p.base_price, p.stock_status,
                                   c.name AS category_name, c.slug AS category_slug,
                                   pi.image_url
                            FROM products p
                            JOIN categories c ON c.id = p.category_id
                            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                            WHERE p.category_id = ?
                              AND p.id <> ?
                              AND p.is_active = 1
                            ORDER BY p.is_featured DESC, p.published_at DESC, p.id DESC
                            LIMIT ?");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('iii', $categoryId, $excludeId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = productMapListRow($row);
    }
    $stmt->close();
    return $items;
}

function productGetById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT p.id, p.name, p.slug, p.sku, p.base_price, p.stock_status,
                                   c.name AS category_name,
                                   pi.image_url
                            FROM products p
                            JOIN categories c ON c.id = p.category_id
                            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                            WHERE p.id = ?
                              AND p.is_active = 1
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'slug' => $row['slug'],
        'sku' => $row['sku'],
        'price' => (int) round((float) $row['base_price']),
        'image' => $row['image_url'] ?: 'assets/images/blog_1.png',
        'category' => $row['category_name'],
        'stock_status' => $row['stock_status'],
    ];
}

/**
 * Sản phẩm bán chạy (tổng số lượng trong đơn hàng hợp lệ).
 *
 * @return array<int, array<string, mixed>>
 */
function productGetBestSellers(mysqli $conn, ?int $limit = null): array
{
    $limit = $limit ?? productFeaturedHomeLimit();
    $limit = max(1, min(24, $limit));
    $sql = "SELECT p.id, p.slug, p.name, p.base_price, p.is_featured,
                   c.name AS category_name,
                   COALESCE(pi.image_url, 'assets/images/blog_1.png') AS image_url,
                   COALESCE(sales.units_sold, 0) AS units_sold
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
            LEFT JOIN (
                SELECT oi.product_id, SUM(oi.quantity) AS units_sold
                FROM order_items oi
                INNER JOIN orders o ON o.id = oi.order_id
                WHERE oi.product_id IS NOT NULL
                  AND o.ordered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                  AND o.status NOT IN ('cancelled', 'returned')
                  AND o.fulfillment_status NOT IN ('cancelled', 'returned')
                GROUP BY oi.product_id
            ) sales ON sales.product_id = p.id
            WHERE p.is_active = 1
              AND (p.published_at IS NULL OR p.published_at <= NOW())
            HAVING units_sold > 0
            ORDER BY units_sold DESC, p.is_featured DESC, p.published_at DESC, p.id DESC
            LIMIT " . (int) $limit;

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'category' => $row['category_name'],
            'price' => productFormatPrice((float) $row['base_price']),
            'image' => $row['image_url'],
            'units_sold' => (int) $row['units_sold'],
        ];
    }
    return $items;
}

/**
 * Gợi ý + sản phẩm cho tìm kiếm AJAX (header / catalog).
 *
 * @return array<string, mixed>
 */
function productSearchAjax(mysqli $conn, string $query, int $productLimit = 8, int $suggestionLimit = 5): array
{
    $query = trim($query);
    if (mb_strlen($query) < 2) {
        return [
            'ok' => true,
            'query' => $query,
            'suggestions' => [],
            'products' => [],
            'total' => 0,
            'catalog_url' => 'index.php?view=catalog',
        ];
    }

    $filters = productBuildFiltersFromRequest();
    $filters['q'] = $query;
    $filters['sort'] = 'featured';

    $total = productCountSearchProducts($conn, $filters);
    $rows = productSearchProducts($conn, $filters, max(1, min(12, $productLimit)), 0);

    $products = [];
    foreach ($rows as $row) {
        $base = (float) $row['base_price'];
        $compare = !empty($row['compare_at_price']) ? (float) $row['compare_at_price'] : 0.0;

        $discountPercent = null;
        if ($compare > $base && $compare > 0) {
            $discountPercent = (int) round((1 - $base / $compare) * 100);
        }

        $products[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'image' => $row['image'],
            'price_label' => $row['price_label'],
            'compare_price_label' => $compare > $base ? productFormatPrice($compare) : null,
            'discount_percent' => $discountPercent,
            'category_name' => $row['category_name'],
            'url' => 'index.php?view=product&slug=' . rawurlencode($row['slug']),
        ];
    }

    $suggestions = [];
    $seen = [];

    $stmtCat = $conn->prepare("SELECT name, slug FROM categories
                               WHERE is_active = 1 AND name LIKE CONCAT('%', ?, '%')
                               ORDER BY sort_order ASC LIMIT 2");
    if ($stmtCat) {
        $stmtCat->bind_param('s', $query);
        $stmtCat->execute();
        $catResult = $stmtCat->get_result();
        while ($cat = $catResult->fetch_assoc()) {
            $key = 'cat:' . $cat['slug'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $suggestions[] = [
                    'type' => 'category',
                    'text' => $query . ' trong ' . $cat['name'],
                    'highlight' => $cat['name'],
                    'url' => 'index.php?view=catalog&category=' . rawurlencode($cat['slug']) . '&q=' . rawurlencode($query),
                ];
            }
        }
        $stmtCat->close();
    }

    foreach ($rows as $row) {
        if (count($suggestions) >= $suggestionLimit) {
            break;
        }
        $text = mb_strtolower($row['name'], 'UTF-8');
        $key = 'p:' . $text;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $suggestions[] = [
            'type' => 'keyword',
            'text' => $row['name'],
            'highlight' => null,
            'url' => 'index.php?view=catalog&q=' . rawurlencode($row['name']),
        ];
    }

    if (count($suggestions) < $suggestionLimit) {
        $stmtKw = $conn->prepare("SELECT DISTINCT p.name FROM products p
                                  WHERE p.is_active = 1
                                    AND (p.name LIKE CONCAT('%', ?, '%') OR p.short_description LIKE CONCAT('%', ?, '%'))
                                  ORDER BY p.is_featured DESC, p.name ASC
                                  LIMIT ?");
        if ($stmtKw) {
            $extra = $suggestionLimit + 3;
            $stmtKw->bind_param('ssi', $query, $query, $extra);
            $stmtKw->execute();
            $kwResult = $stmtKw->get_result();
            while ($kw = $kwResult->fetch_assoc()) {
                if (count($suggestions) >= $suggestionLimit) {
                    break;
                }
                $text = (string) $kw['name'];
                $key = 'p:' . mb_strtolower($text, 'UTF-8');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $suggestions[] = [
                    'type' => 'keyword',
                    'text' => $text,
                    'highlight' => null,
                    'url' => 'index.php?view=catalog&q=' . rawurlencode($text),
                ];
            }
            $stmtKw->close();
        }
    }

    return [
        'ok' => true,
        'query' => $query,
        'suggestions' => $suggestions,
        'products' => $products,
        'total' => $total,
        'catalog_url' => 'index.php?view=catalog&q=' . rawurlencode($query),
    ];
}
